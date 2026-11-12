<?php

declare(strict_types=1);

namespace App\Sifi\CoreV2;

use App\Models\SifiMonitoringObject;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class ShadowPipeline
{
    /**
     * @param  array<array-key, array{id: string, object_revision: int|string, results: array<string, mixed>}>  $sessions
     * @return array<string, array<string, mixed>>
     */
    public function forSessions(User $user, SifiMonitoringObject $object, array $sessions): array
    {
        Gate::forUser($user)->authorize('view', $object);
        if ($sessions === []) {
            return [];
        }

        $signals = DB::table('sifi_real_source_signals')
            ->where('owner_user_id', $user->id)
            ->where('object_id', $object->id)
            ->where('object_revision', $object->revision)
            ->where('provenance_mode', 'REAL_SOURCE_SNAPSHOT')
            ->whereIn('session_id', array_column($sessions, 'id'))
            ->get()->keyBy('session_id');

        $links = DB::table('sifi_evidence_drafts as evidence')
            ->join('sifi_issue_candidates as candidate', 'candidate.id', '=', 'evidence.candidate_id')
            ->join('sifi_investigations as investigation', 'investigation.id', '=', 'candidate.investigation_id')
            ->join('sifi_investigation_signals as association', function ($join) {
                $join->on('association.investigation_id', '=', 'investigation.id')
                    ->on('association.signal_id', '=', 'evidence.signal_id');
            })
            ->leftJoin('sifi_issue_case_drafts as issue_case', 'issue_case.candidate_id', '=', 'candidate.id')
            ->leftJoin('sifi_trajectory_drafts as trajectory', 'trajectory.case_id', '=', 'issue_case.id')
            ->leftJoin('sifi_foresight_drafts as foresight', 'foresight.trajectory_id', '=', 'trajectory.id')
            ->where('evidence.owner_user_id', $user->id)
            ->where('candidate.user_id', $user->id)
            ->where('investigation.user_id', $user->id)
            ->whereIn('evidence.signal_id', $signals->pluck('id'))
            ->get([
                'evidence.id', 'evidence.signal_id', 'evidence.workflow_state',
                'candidate.id as candidate_id', 'investigation.id as investigation_id',
                'issue_case.owner_user_id as case_owner', 'issue_case.verification_state as case_verification',
                'trajectory.owner_user_id as trajectory_owner', 'foresight.owner_user_id as foresight_owner',
            ])
            ->keyBy('id');

        $result = [];
        foreach ($sessions as $session) {
            $data = $session['results'];
            $signal = $signals->get($session['id']);
            $timeline = PipelineReadModel::build((object) [
                'id' => $session['id'],
                'object_id' => $object->id,
                'object_revision' => $session['object_revision'],
                'results_json' => json_encode($data, JSON_THROW_ON_ERROR),
            ], $object, $signal);
            $handoff = $data['e2e04_handoff'] ?? [];
            $evidenceId = $handoff['evidence_draft_id'] ?? null;
            $link = is_string($evidenceId) ? $links->get($evidenceId) : null;

            $confirmed = $timeline['stages']['issue_handoff'] === 'LOCAL_DRAFT_LINKED'
                && $timeline['warnings'] === []
                && $signal->source_url === $timeline['source_url']
                && $signal->source_sha256 === $timeline['source_sha256']
                && $signal->image_sha256 === $timeline['image_sha256']
                && $signal->brief_sha256 === $timeline['brief_sha256']
                && ($handoff['source_sha256'] ?? null) === $timeline['source_sha256']
                && $link !== null
                && $link->signal_id === $signal->id
                && $link->candidate_id === ($handoff['issue_candidate_id'] ?? null)
                && $link->investigation_id === ($handoff['investigation_id'] ?? null);

            if ($handoff !== [] && ! $confirmed) {
                $timeline['warnings'][] = 'HANDOFF_LINEAGE_NOT_CONFIRMED';
                $timeline['stages']['issue_handoff'] = 'NOT_CONFIRMED';
            }

            $issueCase = null;
            $briefReadiness = null;
            $nextContext = null;
            if ($confirmed) {
                $ownerId = (int) $user->id;
                $issueCase = match (true) {
                    $link->case_owner === null => 'MISSING',
                    (int) $link->case_owner === $ownerId && $link->case_verification === 'NOT_VERIFIED' => 'DRAFT_AVAILABLE',
                    default => 'UNAVAILABLE',
                };
                // Match the prerequisites enforced by ExecutiveBriefSource::parents.
                $briefReadiness = match (true) {
                    $issueCase === 'MISSING' => 'ISSUE_CASE_REQUIRED',
                    $issueCase === 'UNAVAILABLE' => 'CONTEXT_UNAVAILABLE',
                    $link->trajectory_owner === null => 'TRAJECTORY_REQUIRED',
                    (int) $link->trajectory_owner !== $ownerId => 'CONTEXT_UNAVAILABLE',
                    $link->foresight_owner === null => 'FORESIGHT_REQUIRED',
                    (int) $link->foresight_owner !== $ownerId => 'CONTEXT_UNAVAILABLE',
                    default => 'READY',
                };
                $nextContext = match ($briefReadiness) {
                    'TRAJECTORY_REQUIRED' => route('sifi.trajectory.show', $link->candidate_id, false),
                    'FORESIGHT_REQUIRED' => route('sifi.foresight.show', $link->candidate_id, false),
                    default => null,
                };
            }

            $result[$session['id']] = [
                'stages' => $timeline['stages'],
                'warnings' => $timeline['warnings'],
                'source_sha256' => $timeline['source_sha256'],
                'review_state' => $confirmed ? $link->workflow_state : null,
                'investigation_url' => $confirmed ? route('sifi.investigations.show', $link->investigation_id, false) : null,
                'candidate_url' => $confirmed ? route('sifi.issue-candidates.show', $link->candidate_id, false) : null,
                'issue_case_state' => $issueCase,
                'issue_case_url' => $confirmed && $issueCase !== 'UNAVAILABLE'
                    ? route('sifi.issue-case.show', $link->candidate_id, false) : null,
                'brief_readiness' => $briefReadiness,
                'next_context_url' => $nextContext,
                'brief_url' => $briefReadiness === 'READY'
                    ? route('sifi.executive-brief.show', $link->candidate_id, false) : null,
                'review_url' => $confirmed ? route('sifi.evidence.index', $link->candidate_id, false) : null,
            ];
        }

        return $result;
    }
}
