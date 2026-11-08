<?php

namespace App\Http\Controllers\Sifi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sifi\RunIntelligenceRequest;
use App\Models\SifiIssueCandidate;
use App\Services\Sifi\Aq01IntelligenceBridge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Human-requested snapshot, not evidence qualification or Issue Object creation. */
final class IntelligenceAssessmentController extends Controller
{
    public function store(RunIntelligenceRequest $request, SifiIssueCandidate $candidate,
                          Aq01IntelligenceBridge $bridge): RedirectResponse
    {
        $revision = (int) $request->validated('revision');
        if ($revision !== (int) $candidate->revision) {
            throw ValidationException::withMessages([
                'revision' => 'Kandidat telah berubah. Muat ulang halaman sebelum menjalankan analisis.',
            ]);
        }
        $hypothesis = trim((string) $candidate->issue_hypothesis);
        if ($hypothesis === '') {
            throw ValidationException::withMessages([
                'intelligence' => 'Simpan hipotesis isu terlebih dahulu sebelum menganalisis.',
            ]);
        }
        $ids = DB::table('sifi_investigation_signals')
            ->where('investigation_id', $candidate->investigation_id)
            ->orderBy('signal_id')->pluck('signal_id')->all();
        if (count($ids) < 1 || count($ids) > 10 || count(array_unique($ids)) !== count($ids)) {
            throw ValidationException::withMessages([
                'intelligence' => 'Referensi sinyal tidak tersedia atau jumlahnya tidak valid.',
            ]);
        }
        $input = [
            'candidate_id' => $candidate->id,
            'candidate_revision' => $revision,
            'issue_hypothesis' => $hypothesis,
            'evidence_gaps' => (string) ($candidate->evidence_gaps ?? ''),
            'next_checks' => (string) ($candidate->next_checks ?? ''),
            'signal_ids' => $ids,
        ];
        $digest = hash('sha256', json_encode($input, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $existing = DB::table('sifi_intelligence_assessments')->where('candidate_id', $candidate->id)
            ->where('input_digest', $digest)->exists();
        if ($existing) {
            return redirect()->route('sifi.issue-candidates.show', $candidate->id);
        }
        try {
            $advisory = $bridge->assess($input);
        } catch (RuntimeException $e) {
            // A backend outage or untrusted reply MUST NOT mutate local workflow.
            throw ValidationException::withMessages([
                'intelligence' => 'Analisis belum tersedia atau jawaban Python tidak lolos validasi. Tidak ada data diubah.',
            ]);
        }
        DB::transaction(function () use ($candidate, $revision, $input, $digest, $advisory, $request): void {
            $row = SifiIssueCandidate::query()->whereKey($candidate->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $row->user_id === (int) $request->user()->id, 403);
            if ((int) $row->revision !== $revision ||
                trim((string) $row->issue_hypothesis) !== $input['issue_hypothesis'] ||
                (string) ($row->evidence_gaps ?? '') !== $input['evidence_gaps'] ||
                (string) ($row->next_checks ?? '') !== $input['next_checks']) {
                throw ValidationException::withMessages([
                    'revision' => 'Kandidat diperbarui saat analisis berjalan. Hasil lama dibatalkan.',
                ]);
            }
            // Signal references may have changed without changing candidate revision.
            $current = DB::table('sifi_investigation_signals')
                ->where('investigation_id', $row->investigation_id)
                ->orderBy('signal_id')->pluck('signal_id')->all();
            if ($current !== $input['signal_ids']) {
                throw ValidationException::withMessages([
                    'intelligence' => 'Referensi sinyal berubah saat analisis berjalan. Hasil dibatalkan.',
                ]);
            }
            if (DB::table('sifi_intelligence_assessments')->where('candidate_id', $candidate->id)
                ->where('input_digest', $digest)->exists()) {
                return;
            }
            DB::table('sifi_intelligence_assessments')->insert([
                'candidate_id' => $candidate->id,
                'actor_user_id' => (int) $request->user()->id,
                'candidate_revision' => $revision,
                'input_digest' => $digest,
                'status' => 'ADVISORY_ONLY',
                'result_json' => json_encode($advisory, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
            ]);
        });
        return redirect()->route('sifi.issue-candidates.show', $candidate->id);
    }
}
