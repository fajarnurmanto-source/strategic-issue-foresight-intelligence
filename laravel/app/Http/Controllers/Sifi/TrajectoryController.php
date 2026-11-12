<?php

namespace App\Http\Controllers\Sifi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sifi\SaveTrajectoryRequest;
use App\Models\SifiIssueCandidate;
use App\Models\SifiIssueCaseDraft;
use App\Models\SifiTrajectoryDraft;
use App\Services\Sifi\Aq01TrajectoryBridge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/** Analyst-owned hypotheses; no claim of real trajectories or quantified risk. */
final class TrajectoryController extends Controller
{
    private function case(SifiIssueCandidate $c): SifiIssueCaseDraft
    {
        return SifiIssueCaseDraft::query()->where('candidate_id', $c->id)->firstOrFail();
    }

    private function draft(SifiIssueCaseDraft $c): ?SifiTrajectoryDraft
    {
        return SifiTrajectoryDraft::query()->where('case_id', $c->id)->first();
    }

    /** @return array<string, mixed> */
    private function snapshot(SifiTrajectoryDraft $d): array
    {
        return ['id' => $d->id, 'case_id' => $d->case_id, 'revision' => (int) $d->revision,
            'horizon' => $d->horizon, 'trajectory_hypothesis' => $d->trajectory_hypothesis,
            'risk_hypothesis' => $d->risk_hypothesis, 'opportunity_hypothesis' => $d->opportunity_hypothesis,
            'watch_condition' => $d->watch_condition, 'uncertainty_note' => $d->uncertainty_note,
            'verification_state' => 'NOT_VERIFIED'];
    }

    /** @return array<string, mixed> */
    private function input(SifiIssueCandidate $c, SifiIssueCaseDraft $case, SifiTrajectoryDraft $draft): array
    {
        return ['candidate_id' => $c->id, 'candidate_revision' => (int) $c->revision,
            'case' => ['id' => $case->id, 'revision' => (int) $case->revision, 'lifecycle_state' => $case->lifecycle_state,
                'verification_state' => $case->verification_state],
            'draft' => array_intersect_key($this->snapshot($draft), array_fill_keys([
                'id', 'revision', 'horizon', 'trajectory_hypothesis', 'risk_hypothesis', 'opportunity_hypothesis', 'watch_condition', 'uncertainty_note'], true)),
            'observed_metadata' => [
                'claim_count' => DB::table('sifi_claim_drafts')->where('candidate_id', $c->id)->count(),
                'event_hypothesis_count' => DB::table('sifi_event_hypotheses')->where('candidate_id', $c->id)->count(),
                'possible_link_count' => DB::table('sifi_claim_event_links')->where('candidate_id', $c->id)->count(),
                'evidence_candidate_count' => DB::table('sifi_evidence_drafts')->where('candidate_id', $c->id)->count()]];
    }

    /** @param array<string, mixed> $data */
    private function digest(array $data): string
    {
        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    /** @param array<string, mixed> $input
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function freshness(array $input, string $digest, string $storedDigest, array $result): array
    {
        $changes = [];
        $compare = function (string $code, string $label, mixed $snapshot, mixed $current) use (&$changes): void {
            if ($snapshot !== $current) {
                $changes[] = ['code' => $code, 'label' => $label, 'snapshot' => $snapshot, 'current' => $current];
            }
        };
        $compare('CANDIDATE_REVISION_CHANGED', 'Revisi kandidat', $result['candidate_revision'] ?? null, $input['candidate_revision']);
        $compare('CASE_REVISION_CHANGED', 'Revisi Issue Case', $result['case_revision'] ?? null, $input['case']['revision']);
        $compare('DRAFT_REVISION_CHANGED', 'Revisi draf Trajectory', $result['draft_revision'] ?? null, $input['draft']['revision']);
        $snapshotMetadata = is_array($result['observed_metadata'] ?? null) ? $result['observed_metadata'] : [];
        foreach ([
            'claim_count' => ['CLAIM_COUNT_CHANGED', 'Claim drafts'],
            'event_hypothesis_count' => ['EVENT_HYPOTHESIS_COUNT_CHANGED', 'Event hypotheses'],
            'possible_link_count' => ['POSSIBLE_LINK_COUNT_CHANGED', 'Possible links'],
            'evidence_candidate_count' => ['EVIDENCE_CANDIDATE_COUNT_CHANGED', 'Evidence drafts'],
        ] as $key => [$code,$label]) {
            $compare($code, $label, $snapshotMetadata[$key] ?? null, $input['observed_metadata'][$key]);
        }
        $digestMatches = hash_equals($digest, $storedDigest);
        if ($digestMatches && $changes === []) {
            return ['status' => 'CURRENT', 'reason_codes' => [], 'changes' => []];
        }
        if ($changes === []) {
            $changes[] = ['code' => 'OTHER_INPUT_CHANGED', 'label' => 'Input lain pada snapshot', 'snapshot' => null, 'current' => null];
        }

        return ['status' => 'STALE', 'reason_codes' => array_column($changes, 'code'), 'changes' => $changes];
    }

    public function show(Request $request, SifiIssueCandidate $candidate): Response
    {
        Gate::authorize('view', $candidate);
        $case = $this->case($candidate);
        abort_unless((int) $case->owner_user_id === (int) $request->user()->id, 403);
        $draft = $this->draft($case);
        $input = $draft ? $this->input($candidate, $case, $draft) : null;
        $digest = $input ? $this->digest($input) : null;
        $results = $draft ? DB::table('sifi_trajectory_advisories')->where('draft_id', $draft->id)->orderByDesc('id')->limit(15)->get() : collect();
        $audit = $draft ? DB::table('sifi_trajectory_audit')->where('draft_id', $draft->id)->orderByDesc('id')->limit(20)->get(['revision', 'action', 'created_at']) : collect();

        return Inertia::render('sifi/trajectory-workspace', [
            'candidate' => ['id' => $candidate->id, 'title' => $candidate->title, 'revision' => (int) $candidate->revision],
            'issueCase' => ['id' => $case->id, 'title' => $case->title, 'revision' => (int) $case->revision,
                'issue_domain' => $case->issue_domain, 'geography_label' => $case->geography_label, 'lifecycle_state' => $case->lifecycle_state],
            'draft' => $draft ? $this->snapshot($draft) : null,
            'counts' => $input['observed_metadata'] ?? ['claim_count' => 0, 'event_hypothesis_count' => 0, 'possible_link_count' => 0, 'evidence_candidate_count' => 0],
            'advisories' => $results->map(function ($r) use ($input, $digest): array {
                $result = json_decode($r->result_json, true, 32, JSON_THROW_ON_ERROR);
                $freshness = $this->freshness($input, $digest, $r->input_digest, $result);

                return ['id' => $r->id, 'created_at' => $r->created_at, 'is_current' => $freshness['status'] === 'CURRENT',
                    'freshness' => $freshness, 'result' => $result];
            })->all(),
            'audit' => $audit->all(), 'mode' => 'LOCAL_HYPOTHESES_ONLY']);
    }

    public function save(SaveTrajectoryRequest $request, SifiIssueCandidate $candidate): RedirectResponse
    {
        $v = $request->validated();
        DB::transaction(function () use ($request, $candidate, $v): void {
            $c = SifiIssueCandidate::query()->whereKey($candidate->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $c->user_id === (int) $request->user()->id, 403);
            if ((int) $c->revision !== (int) $v['candidate_revision']) {
                throw ValidationException::withMessages(['candidate_revision' => 'Revisi kandidat berubah. Muat ulang.']);
            }
            $case = SifiIssueCaseDraft::query()->where('candidate_id', $c->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $case->owner_user_id === (int) $request->user()->id, 403);
            if ((int) $case->revision !== (int) $v['case_revision'] || $case->verification_state !== 'NOT_VERIFIED') {
                throw ValidationException::withMessages(['case_revision' => 'Issue Case berubah atau bukan draf.']);
            }
            $draft = SifiTrajectoryDraft::query()->where('case_id', $case->id)->lockForUpdate()->first();
            if (($draft === null && isset($v['draft_revision'])) || ($draft !== null && (! isset($v['draft_revision']) || (int) $draft->revision !== (int) $v['draft_revision']))) {
                throw ValidationException::withMessages(['draft_revision' => 'Draf berubah. Muat ulang.']);
            }
            $fields = [];
            foreach (['horizon', 'trajectory_hypothesis', 'risk_hypothesis', 'opportunity_hypothesis', 'watch_condition', 'uncertainty_note'] as $key) {
                $fields[$key] = trim($v[$key]);
            }
            if ($draft === null) {
                $draft = SifiTrajectoryDraft::query()->create($fields + [
                    'id' => (string) Str::uuid(), 'case_id' => $case->id, 'owner_user_id' => (int) $request->user()->id, 'revision' => 1]);
                $action = 'TRAJECTORY_DRAFT_CREATED';
            } else {
                $draft->fill($fields);
                if (! $draft->isDirty()) {
                    return;
                }
                $draft->revision++;
                $draft->save();
                $action = 'TRAJECTORY_DRAFT_UPDATED';
            }
            DB::table('sifi_trajectory_audit')->insert(['draft_id' => $draft->id, 'actor_user_id' => (int) $request->user()->id,
                'revision' => $draft->revision, 'action' => $action,
                'snapshot_json' => json_encode($this->snapshot($draft), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'created_at' => now()]);
        });

        return redirect()->route('sifi.trajectory.show', $candidate->id);
    }

    public function assess(SaveTrajectoryRequest $request, SifiIssueCandidate $candidate, Aq01TrajectoryBridge $bridge): RedirectResponse
    {
        $v = $request->validated();
        $case = $this->case($candidate);
        $draft = $this->draft($case);
        if (! $draft || ! isset($v['draft_revision']) || (int) $draft->revision !== (int) $v['draft_revision'] ||
          (int) $case->revision !== (int) $v['case_revision'] || (int) $candidate->revision !== (int) $v['candidate_revision']) {
            throw ValidationException::withMessages(['analysis' => 'Simpan perubahan dahulu dan muat ulang.']);
        }
        $input = $this->input($candidate, $case, $draft);
        $digest = $this->digest($input);
        if (DB::table('sifi_trajectory_advisories')->where('draft_id', $draft->id)->where('input_digest', $digest)->exists()) {
            return redirect()->route('sifi.trajectory.show', $candidate->id);
        }
        try {
            $out = $bridge->assess($input);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['analysis' => 'Python offline atau respons di luar kontrak; hasil tidak disimpan.']);
        }
        DB::transaction(function () use ($request, $candidate, $case, $draft, $input, $digest, $out): void {
            $locked = SifiIssueCandidate::query()->whereKey($candidate->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $locked->user_id === (int) $request->user()->id, 403);
            $liveCase = SifiIssueCaseDraft::query()->whereKey($case->id)->lockForUpdate()->firstOrFail();
            $liveDraft = SifiTrajectoryDraft::query()->whereKey($draft->id)->lockForUpdate()->firstOrFail();
            if ($this->input($locked, $liveCase, $liveDraft) !== $input || $this->digest($this->input($locked, $liveCase, $liveDraft)) !== $digest) {
                throw ValidationException::withMessages(['analysis' => 'Input berubah selama analisis. Hasil dibuang.']);
            }
            if (DB::table('sifi_trajectory_advisories')->where('draft_id', $draft->id)->where('input_digest', $digest)->exists()) {
                return;
            }
            DB::table('sifi_trajectory_advisories')->insert(['draft_id' => $draft->id, 'actor_user_id' => (int) $request->user()->id,
                'draft_revision' => $draft->revision, 'input_digest' => $digest, 'status' => 'ADVISORY_ONLY',
                'result_json' => json_encode($out,JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'created_at' => now()]);
        });

        return redirect()->route('sifi.trajectory.show',$candidate->id);
    }
}
