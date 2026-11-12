<?php

namespace App\Http\Controllers\Sifi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sifi\SaveForesightRequest;
use App\Models\SifiForesightDraft;
use App\Models\SifiIssueCandidate;
use App\Models\SifiIssueCaseDraft;
use App\Models\SifiTrajectoryDraft;
use App\Services\Sifi\Aq01ForesightBridge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/** Analyst-only foresight scenario drafts. NOT a forecast or SIFI Foresight Object. */
final class ForesightController extends Controller
{
    private const FIELDS = ['baseline_scenario', 'alternative_scenario', 'stress_scenario',
        'critical_drivers', 'falsification_checks', 'decision_options', 'uncertainty_note'];

    /** @return array{SifiIssueCaseDraft, SifiTrajectoryDraft} */
    private function parents(SifiIssueCandidate $c): array
    {
        $case = SifiIssueCaseDraft::query()->where('candidate_id', $c->id)->firstOrFail();
        $trajectory = SifiTrajectoryDraft::query()->where('case_id', $case->id)->firstOrFail();
        abort_unless($case->verification_state === 'NOT_VERIFIED', 409);

        return [$case, $trajectory];
    }

    private function draft(SifiTrajectoryDraft $t): ?SifiForesightDraft
    {
        return SifiForesightDraft::query()->where('trajectory_id', $t->id)->first();
    }

    /** @return array<string, string|int> */
    private function snapshot(SifiForesightDraft $draft): array
    {
        $out = ['id' => $draft->id, 'trajectory_id' => $draft->trajectory_id, 'revision' => (int) $draft->revision,
            'verification_state' => 'NOT_VERIFIED'];
        foreach (self::FIELDS as $f) {
            $out[$f] = $draft->$f;
        }

        return $out;
    }

    /** @return array<string, int> */
    private function counts(SifiIssueCandidate $c): array
    {
        return [
            'claim_count' => DB::table('sifi_claim_drafts')->where('candidate_id', $c->id)->count(),
            'event_hypothesis_count' => DB::table('sifi_event_hypotheses')->where('candidate_id', $c->id)->count(),
            'possible_link_count' => DB::table('sifi_claim_event_links')->where('candidate_id', $c->id)->count(),
            'evidence_candidate_count' => DB::table('sifi_evidence_drafts')->where('candidate_id', $c->id)->count()];
    }

    /** @return array<string, mixed> */
    private function input(SifiIssueCandidate $c, SifiIssueCaseDraft $case, SifiTrajectoryDraft $t, SifiForesightDraft $d): array
    {
        $fields = ['id' => $d->id, 'revision' => (int) $d->revision];
        foreach (self::FIELDS as $f) {
            $fields[$f] = $d->$f;
        }

        return ['candidate_id' => $c->id, 'candidate_revision' => (int) $c->revision,
            'case' => ['id' => $case->id, 'revision' => (int) $case->revision, 'lifecycle_state' => $case->lifecycle_state,
                'verification_state' => $case->verification_state],
            'trajectory' => ['id' => $t->id, 'revision' => (int) $t->revision, 'horizon' => $t->horizon, 'verification_state' => 'NOT_VERIFIED'],
            'draft' => $fields, 'observed_metadata' => $this->counts($c)];
    }

    /** @param array<string, mixed> $input */
    private function digest(array $input): string
    {
        return hash('sha256', json_encode($input, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    public function show(Request $request, SifiIssueCandidate $candidate): Response
    {
        Gate::authorize('view', $candidate);
        [$case,$trajectory] = $this->parents($candidate);
        abort_unless((int) $case->owner_user_id === (int) $request->user()->id &&
           (int) $trajectory->owner_user_id === (int) $request->user()->id, 403);
        $draft = $this->draft($trajectory);
        $input = $draft ? $this->input($candidate, $case, $trajectory, $draft) : null;
        $digest = $input ? $this->digest($input) : null;
        $results = $draft ? DB::table('sifi_foresight_advisories')->where('draft_id', $draft->id)->orderByDesc('id')->limit(12)->get() : collect();
        $audit = $draft ? DB::table('sifi_foresight_audit')->where('draft_id', $draft->id)->orderByDesc('id')->limit(20)->get(['revision', 'action', 'created_at']) : collect();

        return Inertia::render('sifi/foresight-workspace', [
            'candidate' => ['id' => $candidate->id, 'title' => $candidate->title, 'revision' => (int) $candidate->revision],
            'issueCase' => ['id' => $case->id, 'title' => $case->title, 'revision' => (int) $case->revision,
                'issue_domain' => $case->issue_domain, 'geography_label' => $case->geography_label],
            'trajectory' => ['id' => $trajectory->id, 'revision' => (int) $trajectory->revision, 'horizon' => $trajectory->horizon],
            'draft' => $draft ? $this->snapshot($draft) : null,
            'counts' => $this->counts($candidate),
            'advisories' => $results->map(fn ($r) => ['id' => $r->id, 'created_at' => $r->created_at,
                'is_current' => $digest !== null && hash_equals($digest, $r->input_digest),
                'result' => json_decode($r->result_json, true, 32, JSON_THROW_ON_ERROR)])->all(),
            'audit' => $audit->all(), 'mode' => 'LOCAL_SCENARIO_DRAFTS_ONLY']);
    }

    public function save(SaveForesightRequest $request, SifiIssueCandidate $candidate): RedirectResponse
    {
        $v = $request->validated();
        DB::transaction(function () use ($request, $candidate, $v): void {
            $c = SifiIssueCandidate::query()->whereKey($candidate->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $c->user_id === (int) $request->user()->id, 403);
            if ((int) $c->revision !== (int) $v['candidate_revision']) {
                throw ValidationException::withMessages(['candidate_revision' => 'Kandidat telah berubah; muat ulang halaman.']);
            }
            $case = SifiIssueCaseDraft::query()->where('candidate_id', $c->id)->lockForUpdate()->firstOrFail();
            $t = SifiTrajectoryDraft::query()->where('case_id', $case->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $case->owner_user_id === (int) $request->user()->id &&
              (int) $t->owner_user_id === (int) $request->user()->id, 403);
            if ((int) $case->revision !== (int) $v['case_revision'] || (int) $t->revision !== (int) $v['trajectory_revision'] ||
              $case->verification_state !== 'NOT_VERIFIED') {
                throw ValidationException::withMessages(['trajectory_revision' => 'Issue Case atau Trajectory berubah. Muat ulang.']);
            }
            $d = SifiForesightDraft::query()->where('trajectory_id', $t->id)->lockForUpdate()->first();
            if (($d === null && isset($v['draft_revision'])) || ($d !== null && (! isset($v['draft_revision']) || (int) $d->revision !== (int) $v['draft_revision']))) {
                throw ValidationException::withMessages(['draft_revision' => 'Draf berubah. Muat ulang.']);
            }
            $fields = [];
            foreach (self::FIELDS as $f) {
                $fields[$f] = trim($v[$f]);
            }
            if ($d === null) {
                $d = SifiForesightDraft::query()->create($fields + ['id' => (string) Str::uuid(),
                    'trajectory_id' => $t->id, 'owner_user_id' => (int) $request->user()->id, 'revision' => 1]);
                $action = 'FORESIGHT_DRAFT_CREATED';
            } else {
                $d->fill($fields);
                if (! $d->isDirty()) {
                    return;
                }
                $d->revision++;
                $d->save();
                $action = 'FORESIGHT_DRAFT_UPDATED';
            }
            DB::table('sifi_foresight_audit')->insert(['draft_id' => $d->id, 'actor_user_id' => (int) $request->user()->id,
                'revision' => $d->revision, 'action' => $action,
                'snapshot_json' => json_encode($this->snapshot($d), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'created_at' => now()]);
        });

        return redirect()->route('sifi.foresight.show', $candidate->id);
    }

    public function assess(SaveForesightRequest $request, SifiIssueCandidate $candidate, Aq01ForesightBridge $bridge): RedirectResponse
    {
        $v = $request->validated();
        [$case,$trajectory] = $this->parents($candidate);
        $draft = $this->draft($trajectory);
        abort_unless((int) $case->owner_user_id === (int) $request->user()->id &&
          (int) $trajectory->owner_user_id === (int) $request->user()->id, 403);
        if (! $draft || ! isset($v['draft_revision']) || (int) $draft->revision !== (int) $v['draft_revision'] ||
          (int) $trajectory->revision !== (int) $v['trajectory_revision'] ||
          (int) $case->revision !== (int) $v['case_revision'] || (int) $candidate->revision !== (int) $v['candidate_revision']) {
            throw ValidationException::withMessages(['analysis' => 'Simpan perubahan dahulu dan muat ulang.']);
        }
        $input = $this->input($candidate, $case, $trajectory, $draft);
        $digest = $this->digest($input);
        if (DB::table('sifi_foresight_advisories')->where('draft_id', $draft->id)->where('input_digest', $digest)->exists()) {
            return redirect()->route('sifi.foresight.show', $candidate->id);
        }
        try {
            $out = $bridge->assess($input);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['analysis' => 'Python tidak tersedia atau melanggar kontrak. Tidak ada hasil yang disimpan.']);
        }
        DB::transaction(function () use ($request, $candidate, $case, $trajectory, $draft, $digest, $out): void {
            $c = SifiIssueCandidate::query()->whereKey($candidate->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $c->user_id === (int) $request->user()->id, 403);
            $liveCase = SifiIssueCaseDraft::query()->whereKey($case->id)->lockForUpdate()->firstOrFail();
            $liveTrajectory = SifiTrajectoryDraft::query()->whereKey($trajectory->id)->lockForUpdate()->firstOrFail();
            $liveDraft = SifiForesightDraft::query()->whereKey($draft->id)->lockForUpdate()->firstOrFail();
            if ($this->digest($this->input($c, $liveCase, $liveTrajectory, $liveDraft)) !== $digest) {
                throw ValidationException::withMessages(['analysis' => 'Data berubah saat analisis. Snapshot dibatalkan.']);
            }
            if (DB::table('sifi_foresight_advisories')->where('draft_id', $draft->id)->where('input_digest', $digest)->exists()) {
                return;
            }
            DB::table('sifi_foresight_advisories')->insert(['draft_id' => $draft->id, 'actor_user_id' => (int) $request->user()->id,
                'draft_revision' => $draft->revision, 'input_digest' => $digest, 'status' => 'ADVISORY_ONLY',
                'result_json' => json_encode($out,JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'created_at' => now()]);
        });

        return redirect()->route('sifi.foresight.show',$candidate->id);
    }
}
