<?php
namespace App\Http\Controllers\Sifi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sifi\RunEvidenceDiagnosticsRequest;
use App\Models\SifiIssueCandidate;
use App\Services\Sifi\Aq01EvidenceDiagnosticsBridge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/** Snapshot-only readiness diagnostic, never qualifies/adopts an Evidence Item. */
final class EvidenceDiagnosticsController extends Controller
{
    private function drafts(SifiIssueCandidate $candidate): array
    {
        return DB::table('sifi_evidence_drafts')->where('candidate_id', $candidate->id)
            ->orderBy('id')->get(['id','signal_id','revision','relationship'])
            ->map(fn ($e) => [
                'evidence_id' => $e->id, 'signal_id' => $e->signal_id,
                'revision' => (int) $e->revision, 'relationship' => $e->relationship,
            ])->all();
    }

    public function show(Request $request, SifiIssueCandidate $candidate): Response
    {
        Gate::authorize('view', $candidate);
        $drafts = $this->drafts($candidate);
        $rows = DB::table('sifi_evidence_diagnostics')->where('candidate_id', $candidate->id)
            ->orderByDesc('id')->limit(15)->get(['id','candidate_revision','input_digest','result_json','created_at']);
        $currentDigest = $this->digest($candidate, $drafts);
        return Inertia::render('sifi/evidence-diagnostics', [
            'candidate' => ['id'=>$candidate->id, 'title'=>$candidate->title, 'revision'=>(int)$candidate->revision],
            'drafts' => $drafts,
            'currentDigest' => $currentDigest,
            'diagnostics' => $rows->map(fn ($row) => [
                'id' => $row->id,
                'candidate_revision' => (int) $row->candidate_revision,
                'is_current' => hash_equals($row->input_digest, $currentDigest),
                'result' => json_decode($row->result_json, true, 32, JSON_THROW_ON_ERROR),
                'created_at' => $row->created_at,
            ])->all(),
            'scope' => 'OFFLINE_READINESS_ONLY_NOT_QUALIFIED',
        ]);
    }

    public function store(RunEvidenceDiagnosticsRequest $request, SifiIssueCandidate $candidate,
                          Aq01EvidenceDiagnosticsBridge $bridge): RedirectResponse
    {
        $revision = (int) $request->validated('revision');
        $drafts = $this->drafts($candidate);
        if ($revision !== (int)$candidate->revision) {
            throw ValidationException::withMessages(['revision'=>'Revisi kandidat berubah. Muat ulang.']);
        }
        if (count($drafts) < 1 || count($drafts) > 10) {
            throw ValidationException::withMessages(['diagnostics'=>'Butuhkan 1–10 kandidat bukti lokal.']);
        }
        $linked = DB::table('sifi_investigation_signals')
            ->where('investigation_id', $candidate->investigation_id)->pluck('signal_id')->all();
        foreach ($drafts as $draft) {
            if (!in_array($draft['signal_id'], $linked, true)) {
                throw ValidationException::withMessages(['diagnostics'=>'Referensi sinyal tidak lagi terlampir.']);
            }
        }
        $digest = $this->digest($candidate, $drafts);
        if (DB::table('sifi_evidence_diagnostics')->where('candidate_id',$candidate->id)
            ->where('input_digest',$digest)->exists()) {
            return redirect()->route('sifi.evidence-diagnostics.show',$candidate->id);
        }
        $input = ['candidate_id'=>$candidate->id, 'candidate_revision'=>$revision,'drafts'=>$drafts];
        try {
            $result = $bridge->diagnose($input);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['diagnostics'=>'Python tidak tersedia atau hasil ditolak. Tidak ada perubahan tersimpan.']);
        }
        DB::transaction(function () use ($candidate,$drafts,$revision,$digest,$result,$request):void {
            $locked = SifiIssueCandidate::query()->whereKey($candidate->id)->lockForUpdate()->firstOrFail();
            abort_unless((int)$locked->user_id===(int)$request->user()->id,403);
            if ((int)$locked->revision!==$revision ||
                $this->drafts($locked)!==$drafts || $this->digest($locked,$drafts)!==$digest) {
                throw ValidationException::withMessages(['diagnostics'=>'Kandidat atau bukti berubah selama analisis. Hasil dibatalkan.']);
            }
            $linkedNow = DB::table('sifi_investigation_signals')->where('investigation_id',$locked->investigation_id)
                ->pluck('signal_id')->all();
            foreach ($drafts as $draft) {
                if (!in_array($draft['signal_id'],$linkedNow,true)) {
                    throw ValidationException::withMessages(['diagnostics'=>'Lampiran investigasi berubah. Hasil dibatalkan.']);
                }
            }
            if (DB::table('sifi_evidence_diagnostics')->where('candidate_id',$locked->id)
                ->where('input_digest',$digest)->exists()) return;
            DB::table('sifi_evidence_diagnostics')->insert([
                'candidate_id'=>$locked->id,'actor_user_id'=>(int)$request->user()->id,
                'candidate_revision'=>$revision,'input_digest'=>$digest,
                'status'=>'ADVISORY_ONLY',
                'result_json'=>json_encode($result,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),
                'created_at'=>now(),
            ]);
        });
        return redirect()->route('sifi.evidence-diagnostics.show',$candidate->id);
    }

    private function digest(SifiIssueCandidate $candidate,array $drafts):string
    {
        return hash('sha256',json_encode([
            $candidate->id,(int)$candidate->revision,$candidate->issue_hypothesis,
            $candidate->evidence_gaps,$drafts
        ],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
    }
}
