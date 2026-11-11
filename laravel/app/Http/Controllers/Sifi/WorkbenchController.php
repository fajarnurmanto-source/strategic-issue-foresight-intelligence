<?php

namespace App\Http\Controllers\Sifi;

use App\Http\Controllers\Controller;
use App\Models\SifiIssueCandidate;
use App\Services\Sifi\ClaimComparisonPreviewInput;
use App\Services\Sifi\ClaimComparisonInputDiagnostics;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** Read-only local workflow directory. No claim/evidence promotion or external I/O. */
final class WorkbenchController extends Controller
{
    public function index(Request $request, ClaimComparisonPreviewInput $source, ClaimComparisonInputDiagnostics $diagnostics): Response
    {
        $selection = $request->validate(['candidate' => ['nullable', 'uuid'],'object'=>['nullable','uuid']]);
        $ownerId = $request->user()->getAuthIdentifier();
        if(isset($selection['object'])) {
            $object=\App\Models\SifiMonitoringObject::query()->whereKey($selection['object'])->firstOrFail();Gate::authorize('view',$object);
            abort_if(isset($selection['candidate']),422,'Candidate is not linked to this monitoring object.');
            $objectSourceIds=DB::table('sifi_monitoring_object_sources')->where('object_id',$object->id)->pluck('source_id')->all();
            return Inertia::render('sifi/workbench',['focus'=>null,'cases'=>[],'total'=>0,'showing'=>0,'mode'=>'OFFLINE_FIXTURE_ONLY','evidence_state'=>'NOT_EVALUATED','production_authorized'=>false,
                'monitoringObject'=>$object->only(['id','name','category','geography','objective','horizon','state','revision','aliases_json','keywords_json','exclusions_json']),
                'objectSources'=>array_values(array_filter(app(\App\Services\Sifi\MonitoringObjectSources::class)->choices($request->user()),fn($s)=>in_array($s['id'],$objectSourceIds,true))),
                'objectSourceIds'=>$objectSourceIds,'collectorObservations'=>app(\App\Services\Sifi\CollectorObservations::class)->forObject($request->user(),$object)]);
        }

        // Source of authority: the user-owned issue candidate. Downstream IDs are
        // revealed only through this anchored owner-filtered relationship.
        $base = DB::table('sifi_issue_candidates as c')
            ->where('c.user_id', $ownerId)
            ->leftJoin('sifi_issue_case_drafts as ic', 'ic.candidate_id', '=', 'c.id')
            ->leftJoin('sifi_trajectory_drafts as tr', 'tr.case_id', '=', 'ic.id')
            ->leftJoin('sifi_foresight_drafts as fs', 'fs.trajectory_id', '=', 'tr.id')
            ->leftJoin('sifi_executive_briefs as br', 'br.candidate_id', '=', 'c.id');

        $rows = (clone $base)
            ->select([
                'c.id as candidate_id', 'c.title', 'c.review_state', 'c.revision',
                'ic.id as case_id', 'tr.id as trajectory_id',
                'fs.id as foresight_id', 'br.id as brief_id', 'c.updated_at',
            ])
            ->orderByDesc('c.updated_at')->limit(12)->get();

        $cases = $rows->map(static fn ($r): array => [
            'candidate_id' => $r->candidate_id,
            'title' => $r->title,
            'review_state' => $r->review_state,
            'revision' => (int) $r->revision,
            'has_case' => $r->case_id !== null,
            'has_trajectory' => $r->trajectory_id !== null,
            'has_foresight' => $r->foresight_id !== null,
            'has_brief' => $r->brief_id !== null,
            'updated_at' => $r->updated_at,
        ])->all();

        $total = (int) DB::table('sifi_issue_candidates')->where('user_id', $ownerId)->count();
        $selectedId = $selection['candidate'] ?? ($cases[0]['candidate_id'] ?? null);
        $focus = $selectedId ? $this->context($selectedId, $source, $diagnostics) : null;
        return Inertia::render('sifi/workbench', [
            'focus' => $focus,
            'cases' => $cases,
            'total' => $total,
            'showing' => count($cases),
            'mode' => $focus['source_mode'] ?? 'OFFLINE_FIXTURE_ONLY',
            'evidence_state' => 'NOT_EVALUATED',
            'production_authorized' => false,
        ]);
    }

    /**
     * One owner-authorized, transaction-consistent projection; no remote calls or writes.
     * @return array<string, mixed>
     */
    private function context(string $id, ClaimComparisonPreviewInput $source, ClaimComparisonInputDiagnostics $diagnostics): array
    {
        return DB::transaction(function () use ($id, $source, $diagnostics): array {
            $candidate = SifiIssueCandidate::query()->findOrFail($id);
            Gate::authorize('view', $candidate);
            try {
                $input = $source->read($candidate);
                $details = $diagnostics->summarize($input);
            } catch (\InvalidArgumentException | \Illuminate\Database\QueryException) {
                abort(503, 'Workbench context unavailable.');
            }
            $origin=DB::table('sifi_issue_candidate_audit')->where('candidate_id',$candidate->id)->where('actor_user_id',$candidate->user_id)
                ->where('revision',1)->whereIn('action',['SOURCE_SNAPSHOT_IMPORTED','SYNTHETIC_TEMPLATE_CREATED'])->value('snapshot_json');
            $tag=$origin?(json_decode($origin,true)['origin']??null):null;
            $isArchive=in_array($tag,['AE09_REAL_PUBLIC_ARCHIVE','AE10_REAL_ACQUISITION'],true);
            $isMock=$tag==='AE10_MOCK_ACQUISITION';
            // Same fingerprint as Preview; describes claim input, not a new authority ledger.
            $digest = hash('sha256', json_encode($input, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return [
                'source_mode'=>$isMock?'MOCK_ACQUISITION_ONLY':($isArchive?'REAL_PUBLIC_ARCHIVE':'OFFLINE_FIXTURE_ONLY'),
                'candidate' => [
                    'id' => $candidate->id, 'revision' => (int) $candidate->revision,
                    'title' => $candidate->title, 'issue_hypothesis' => $candidate->issue_hypothesis,
                    'exposure_notes' => $candidate->exposure_notes, 'evidence_gaps' => $candidate->evidence_gaps,
                    'next_checks' => $candidate->next_checks, 'review_state' => $candidate->review_state,
                ],
                'diagnostics' => $details,
                'claim_input_digest' => $digest,
                'signal_reference_count' => (int) DB::table('sifi_investigation_signals')
                    ->where('investigation_id', $candidate->investigation_id)->count(),
            ];
        });
    }
}
