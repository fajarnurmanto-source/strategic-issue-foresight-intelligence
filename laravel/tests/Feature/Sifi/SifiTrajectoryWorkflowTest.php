<?php

namespace Tests\Feature\Sifi;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as Outbound;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class SifiTrajectoryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private string $candidate;

    private string $case;

    private string $url;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::preventStrayRequests();
        config()->set('sifi_aq01.base_url', 'http://127.0.0.1:8766');
        config()->set('sifi_aq01.token', str_repeat('a', 40));
        $this->owner = User::factory()->create(['email_verified_at' => now()]);
        $this->candidate = (string) Str::uuid();
        $this->case = (string) Str::uuid();
        $inv = (string) Str::uuid();
        $this->url = '/sifi/issue-candidates/'.$this->candidate.'/trajectory';
        DB::table('sifi_investigations')->insert(['id' => $inv, 'user_id' => $this->owner->id, 'title' => 'Demo', 'review_state' => 'DRAFT', 'revision' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('sifi_issue_candidates')->insert(['id' => $this->candidate, 'investigation_id' => $inv, 'user_id' => $this->owner->id, 'title' => 'Demo', 'review_state' => 'DRAFT', 'revision' => 3, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('sifi_issue_case_drafts')->insert(['id' => $this->case, 'candidate_id' => $this->candidate, 'owner_user_id' => $this->owner->id,
            'title' => 'Issue Case', 'issue_domain' => 'lingkungan', 'geography_label' => 'Kota Yogyakarta', 'scope_note' => 'Belum diverifikasi',
            'lifecycle_state' => 'DRAFT', 'verification_state' => 'NOT_VERIFIED', 'revision' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function data(array $override = []): array
    {
        return array_merge(['candidate_revision' => 3, 'case_revision' => 1, 'horizon' => 'UNSPECIFIED',
            'trajectory_hypothesis' => 'Belum ada tren yang terbukti', 'risk_hypothesis' => 'Hipotesis risiko perlu bukti',
            'opportunity_hypothesis' => 'Hipotesis peluang perlu diuji', 'watch_condition' => 'Perlu indikator pemantauan yang jelas',
            'uncertainty_note' => 'Belum ada bukti primer'], $override);
    }

    private function save(): string
    {
        $this->actingAs($this->owner)->post($this->url, $this->data())->assertRedirect($this->url);

        return (string) DB::table('sifi_trajectory_drafts')->where('case_id', $this->case)->value('id');
    }

    private function fake(array $override = []): void
    {
        Http::fake(function (Outbound $request) use ($override) {
            $p = $request->data();

            return Http::response(array_merge([
                'schema_version' => 'sifi.trajectory.advisory.v1', 'mode' => 'OFFLINE_FIXTURE_ONLY', 'kind' => 'TRAJECTORY_RISK_OPPORTUNITY_DRAFT_AID_ONLY',
                'verification_state' => 'NOT_VERIFIED', 'evidence_admission' => 'NOT_EVALUATED',
                'issue_object_created' => false, 'trajectory_verified' => false, 'risk_score_available' => false,
                'risk_evaluated' => false, 'opportunity_evaluated' => false,
                'candidate_id' => $p['candidate_id'], 'candidate_revision' => $p['candidate_revision'],
                'case_id' => $p['case']['id'], 'case_revision' => $p['case']['revision'],
                'draft_id' => $p['draft']['id'], 'draft_revision' => $p['draft']['revision'],
                'request_nonce' => $p['request_nonce'], 'observed_metadata' => $p['observed_metadata'],
                'temporal_observations_verified' => 0, 'trajectory_marker' => 'NO_VERIFIED_TEMPORAL_SERIES',
                'risk_marker' => 'HYPOTHESIS_ONLY', 'opportunity_marker' => 'HYPOTHESIS_ONLY', 'watch_marker' => 'CONDITION_DEFINED_NOT_MONITORED',
                'verification_checks' => ['Periksa waktu dan bukti', 'Periksa asal sumber', 'Jangan buat skor', 'Tidak ada observasi temporal'],
            ], $override));
        });
    }

    public function test_guests_denied(): void
    {
        $this->get($this->url)->assertRedirect('/login');
        $this->post($this->url, [])->assertRedirect('/login');
    }

    public function test_saves_hypotheses_without_verified_states(): void
    {
        $id = $this->save();
        $this->assertDatabaseHas('sifi_trajectory_drafts', ['id' => $id, 'revision' => 1]);
        $this->assertDatabaseCount('sifi_trajectory_audit', 1);
        $this->get($this->url)->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->component('sifi/trajectory-workspace')->where('draft.id', $id)->etc());
    }

    public function test_foreign_user_denied_before_validation(): void
    {
        $this->save();
        $other = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($other)->get($this->url)->assertForbidden();
        $this->post($this->url, ['verified' => true])->assertForbidden();
        $this->post($this->url.'/assess', ['risk_score' => 999])->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_update_revisions_and_stale_reject(): void
    {
        $this->save();
        $this->post($this->url, $this->data(['draft_revision' => 1, 'risk_hypothesis' => 'Perlu konfirmasi dampak potensial']))->assertRedirect($this->url);
        $this->assertDatabaseHas('sifi_trajectory_drafts', ['case_id' => $this->case, 'revision' => 2]);
        $this->post($this->url, $this->data(['draft_revision' => 1]))->assertSessionHasErrors('draft_revision');
        $this->assertDatabaseCount('sifi_trajectory_audit', 2);
    }

    public function test_cannot_submit_numeric_risk_score(): void
    {
        $this->actingAs($this->owner)->post($this->url, $this->data(['risk_score' => 99]))->assertSessionHasErrors('risk_score');
        $this->assertDatabaseCount('sifi_trajectory_drafts', 0);
    }

    public function test_assessment_is_idempotent_and_read_only(): void
    {
        $this->save();
        $this->fake();
        $v = ['candidate_revision' => 3, 'case_revision' => 1, 'draft_revision' => 1];
        $this->post($this->url.'/assess', $v)->assertRedirect($this->url);
        $this->post($this->url.'/assess', $v)->assertRedirect($this->url);
        $this->assertDatabaseCount('sifi_trajectory_advisories', 1);
        Http::assertSentCount(1);
        $this->assertDatabaseHas('sifi_issue_case_drafts', ['id' => $this->case, 'verification_state' => 'NOT_VERIFIED']);
    }

    public function test_get_explains_which_snapshot_inputs_changed_without_calling_python(): void
    {
        $draft = $this->save();
        $this->fake();
        $v = ['candidate_revision' => 3, 'case_revision' => 1, 'draft_revision' => 1];
        $this->post($this->url.'/assess', $v)->assertRedirect($this->url);
        $this->get($this->url)->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
            ->where('advisories.0.is_current', true)
            ->where('advisories.0.freshness.status', 'CURRENT')
            ->where('advisories.0.freshness.reason_codes', [])
            ->where('advisories.0.freshness.changes', [])->etc());

        $evidence = (string) Str::uuid();
        $claim = (string) Str::uuid();
        DB::table('sifi_evidence_drafts')->insert(['id' => $evidence, 'candidate_id' => $this->candidate,
            'owner_user_id' => $this->owner->id, 'signal_id' => str_repeat('b', 64), 'claim_text' => 'Synthetic evidence body',
            'revision' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('sifi_claim_drafts')->insert(['id' => $claim, 'candidate_id' => $this->candidate,
            'evidence_id' => $evidence, 'owner_user_id' => $this->owner->id, 'statement' => 'Synthetic claim body',
            'attribution' => 'Synthetic fixture', 'modality' => 'REPORTED', 'revision' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->get($this->url)->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
            ->where('advisories.0.is_current', false)
            ->where('advisories.0.freshness.status', 'STALE')
            ->where('advisories.0.freshness.reason_codes', ['CLAIM_COUNT_CHANGED', 'EVIDENCE_CANDIDATE_COUNT_CHANGED'])
            ->where('advisories.0.freshness.changes.0.snapshot', 0)
            ->where('advisories.0.freshness.changes.0.current', 1)
            ->where('advisories.0.freshness.changes.1.snapshot', 0)
            ->where('advisories.0.freshness.changes.1.current', 1)->etc());

        DB::table('sifi_issue_candidates')->where('id', $this->candidate)->update(['revision' => 4]);
        DB::table('sifi_issue_case_drafts')->where('id', $this->case)->update(['revision' => 2]);
        DB::table('sifi_trajectory_drafts')->where('id', $draft)->update(['revision' => 2]);
        $this->get($this->url)->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
            ->where('advisories.0.freshness.reason_codes', [
                'CANDIDATE_REVISION_CHANGED', 'CASE_REVISION_CHANGED', 'DRAFT_REVISION_CHANGED',
                'CLAIM_COUNT_CHANGED', 'EVIDENCE_CANDIDATE_COUNT_CHANGED'])
            ->where('advisories.0.freshness.changes.0.snapshot', 3)
            ->where('advisories.0.freshness.changes.0.current', 4)->etc());
        Http::assertSentCount(1);
    }

    public function test_fake_python_qualified_evidence_is_rejected(): void
    {
        $this->save();
        $this->fake(['evidence_admission' => 'ADMITTED']);
        $this->post($this->url.'/assess', ['candidate_revision' => 3, 'case_revision' => 1, 'draft_revision' => 1])->assertSessionHasErrors('analysis');
        $this->assertDatabaseCount('sifi_trajectory_advisories', 0);
    }

    public function test_forged_trajectory_trend_is_rejected(): void
    {
        $this->save();
        $this->fake(['trajectory_marker' => 'FAST_ESCALATING']);
        $this->post($this->url.'/assess', ['candidate_revision' => 3, 'case_revision' => 1, 'draft_revision' => 1])->assertSessionHasErrors('analysis');
    }

    public function test_offline_python_does_not_persist_snapshot(): void
    {
        $this->save();
        Http::fake(['http://127.0.0.1:8766/*' => Http::response(['error' => 'offline'], 503)]);
        $this->post($this->url.'/assess', ['candidate_revision' => 3, 'case_revision' => 1, 'draft_revision' => 1])->assertSessionHasErrors('analysis');
        $this->assertDatabaseCount('sifi_trajectory_advisories', 0);
    }

    public function test_changed_case_during_python_is_discarded(): void
    {
        $this->save();
        Http::fake(function (Outbound $r) {
            DB::table('sifi_issue_case_drafts')->where('id', $this->case)->update(['revision' => 2]);
            $p = $r->data();

            return Http::response([
                'schema_version' => 'sifi.trajectory.advisory.v1', 'mode' => 'OFFLINE_FIXTURE_ONLY', 'kind' => 'TRAJECTORY_RISK_OPPORTUNITY_DRAFT_AID_ONLY',
                'verification_state' => 'NOT_VERIFIED', 'evidence_admission' => 'NOT_EVALUATED', 'issue_object_created' => false,
                'trajectory_verified' => false, 'risk_score_available' => false, 'risk_evaluated' => false, 'opportunity_evaluated' => false,
                'candidate_id' => $p['candidate_id'], 'candidate_revision' => $p['candidate_revision'],
                'case_id' => $p['case']['id'], 'case_revision' => $p['case']['revision'], 'draft_id' => $p['draft']['id'],
                'draft_revision' => $p['draft']['revision'], 'request_nonce' => $p['request_nonce'], 'observed_metadata' => $p['observed_metadata'],
                'temporal_observations_verified' => 0, 'trajectory_marker' => 'NO_VERIFIED_TEMPORAL_SERIES',
                'risk_marker' => 'HYPOTHESIS_ONLY', 'opportunity_marker' => 'HYPOTHESIS_ONLY', 'watch_marker' => 'CONDITION_DEFINED_NOT_MONITORED',
                'verification_checks' => ['Periksa waktu', 'Periksa asal', 'Jangan buat skor', 'Tidak ada observasi']]);
        });
        $this->post($this->url.'/assess',['candidate_revision' => 3, 'case_revision' => 1, 'draft_revision' => 1])->assertSessionHasErrors('analysis');
        $this->assertDatabaseCount('sifi_trajectory_advisories',0);
    }
}
