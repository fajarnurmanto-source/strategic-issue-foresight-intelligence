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

final class SifiForesightWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private string $candidate;

    private string $case;

    private string $trajectory;

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
        $this->trajectory = (string) Str::uuid();
        $inv = (string) Str::uuid();
        $this->url = '/sifi/issue-candidates/'.$this->candidate.'/foresight';
        DB::table('sifi_investigations')->insert(['id' => $inv, 'user_id' => $this->owner->id, 'title' => 'Demo', 'review_state' => 'DRAFT', 'revision' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('sifi_issue_candidates')->insert(['id' => $this->candidate, 'investigation_id' => $inv, 'user_id' => $this->owner->id, 'title' => 'Demo', 'review_state' => 'DRAFT', 'revision' => 3, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('sifi_issue_case_drafts')->insert(['id' => $this->case, 'candidate_id' => $this->candidate, 'owner_user_id' => $this->owner->id, 'title' => 'Case', 'issue_domain' => 'lingkungan', 'geography_label' => 'Yogyakarta', 'scope_note' => 'Belum teruji', 'lifecycle_state' => 'DRAFT', 'verification_state' => 'NOT_VERIFIED', 'revision' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('sifi_trajectory_drafts')->insert(['id' => $this->trajectory, 'case_id' => $this->case, 'owner_user_id' => $this->owner->id, 'horizon' => 'UNSPECIFIED', 'trajectory_hypothesis' => 'Belum ada tren', 'risk_hypothesis' => 'Perlu data', 'opportunity_hypothesis' => 'Perlu bukti', 'watch_condition' => 'Indikator belum tersedia', 'uncertainty_note' => 'Belum ada evidence', 'revision' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function data(array $update = []): array
    {
        return array_merge(['candidate_revision' => 3, 'case_revision' => 1, 'trajectory_revision' => 1,
            'baseline_scenario' => 'Skenario baseline masih hipotesis', 'alternative_scenario' => 'Alternatif masih hipotetis',
            'stress_scenario' => 'Stress masih hipotetis', 'critical_drivers' => 'Indikator belum teruji',
            'falsification_checks' => 'Cek bukti pembatal skenario', 'decision_options' => 'Bandingkan opsi tindakan',
            'uncertainty_note' => 'Tidak ada deret waktu verified'], $update);
    }

    private function save(): string
    {
        $this->actingAs($this->owner)->post($this->url, $this->data())->assertRedirect($this->url);

        return (string) DB::table('sifi_foresight_drafts')->where('trajectory_id', $this->trajectory)->value('id');
    }

    private function fake(array $changes = []): void
    {
        Http::fake(function (Outbound $r) use ($changes) {
            $p = $r->data();

            return Http::response(array_merge([
                'schema_version' => 'sifi.foresight.advisory.v1', 'mode' => 'OFFLINE_FIXTURE_ONLY', 'kind' => 'SCENARIO_STRUCTURE_DRAFT_AID_ONLY',
                'candidate_id' => $p['candidate_id'], 'candidate_revision' => $p['candidate_revision'],
                'case_id' => $p['case']['id'], 'case_revision' => $p['case']['revision'],
                'trajectory_id' => $p['trajectory']['id'], 'trajectory_revision' => $p['trajectory']['revision'],
                'draft_id' => $p['draft']['id'], 'draft_revision' => $p['draft']['revision'], 'request_nonce' => $p['request_nonce'],
                'observed_metadata' => $p['observed_metadata'], 'verification_state' => 'NOT_VERIFIED', 'evidence_admission' => 'NOT_EVALUATED',
                'forecast_available' => false, 'scenario_probability_available' => false, 'decision_authorized' => false, 'issue_object_created' => false,
                'temporal_observations_verified' => 0, 'foresight_marker' => 'SCENARIO_HYPOTHESES_ONLY_NO_FORECAST_BASIS',
                'scenario_labels' => ['BASELINE', 'ALTERNATIVE', 'STRESS'],
                'verification_checks' => ['Periksa bukti', 'Tentukan indikator', 'Uji pembatal skenario', 'Jangan jadikan prediksi'],
            ], $changes));
        });
    }

    public function test_guests_cannot_read_or_write(): void
    {
        $this->get($this->url)->assertRedirect('/login');
        $this->post($this->url, [])->assertRedirect('/login');
    }

    public function test_saves_three_scenarios_and_audit(): void
    {
        $id = $this->save();
        $this->assertNotEmpty($id);
        $this->assertDatabaseHas('sifi_foresight_drafts', ['id' => $id, 'revision' => 1]);
        $this->assertDatabaseCount('sifi_foresight_audit', 1);
        $this->get($this->url)->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->component('sifi/foresight-workspace')->where('draft.id', $id)->etc());
    }

    public function test_foreign_user_denied_before_payload_validation(): void
    {
        $this->save();
        $other = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($other);
        $this->get($this->url)->assertForbidden();
        $this->post($this->url, ['verified' => true])->assertForbidden();
        $this->post($this->url.'/assess', ['forecast_probability' => 1])->assertForbidden();
    }

    public function test_revision_and_stale_conflict(): void
    {
        $this->save();
        $this->post($this->url, $this->data(['draft_revision' => 1, 'stress_scenario' => 'Skenario stress berubah karena hipotesis baru']))->assertRedirect($this->url);
        $this->assertDatabaseHas('sifi_foresight_drafts', ['trajectory_id' => $this->trajectory, 'revision' => 2]);
        $this->post($this->url, $this->data(['draft_revision' => 1]))->assertSessionHasErrors('draft_revision');
        $this->assertDatabaseCount('sifi_foresight_audit', 2);
    }

    public function test_prohibits_probabilities_and_verified_flags(): void
    {
        $this->actingAs($this->owner)->post($this->url, $this->data(['forecast_probability' => 0.92]))->assertSessionHasErrors('forecast_probability');
        $this->assertDatabaseCount('sifi_foresight_drafts', 0);
    }

    public function test_assess_idempotent_and_no_promotion(): void
    {
        $this->save();
        $this->fake();
        $v = ['candidate_revision' => 3, 'case_revision' => 1, 'trajectory_revision' => 1, 'draft_revision' => 1];
        $this->post($this->url.'/assess', $v)->assertRedirect($this->url);
        $this->post($this->url.'/assess', $v)->assertRedirect($this->url);
        $this->assertDatabaseCount('sifi_foresight_advisories', 1);
        Http::assertSentCount(1);
        $this->assertDatabaseHas('sifi_issue_case_drafts', ['id' => $this->case, 'verification_state' => 'NOT_VERIFIED']);
    }

    public function test_rejects_forged_python_forecast(): void
    {
        $this->save();
        $this->fake(['forecast_available' => true]);
        $this->post($this->url.'/assess', ['candidate_revision' => 3, 'case_revision' => 1, 'trajectory_revision' => 1, 'draft_revision' => 1])->assertSessionHasErrors('analysis');
        $this->assertDatabaseCount('sifi_foresight_advisories', 0);
    }

    public function test_python_offline_fails_closed(): void
    {
        $this->save();
        Http::fake(['http://127.0.0.1:8766/*' => Http::response(['error' => 'offline'], 503)]);
        $this->post($this->url.'/assess', ['candidate_revision' => 3, 'case_revision' => 1, 'trajectory_revision' => 1, 'draft_revision' => 1])->assertSessionHasErrors('analysis');
        $this->assertDatabaseCount('sifi_foresight_advisories', 0);
    }

    public function test_changed_trajectory_invalidates_snapshot(): void
    {
        $this->save();
        Http::fake(function (Outbound $r) {
            DB::table('sifi_trajectory_drafts')->where('id', $this->trajectory)->update(['revision' => 2]);

            return Http::response(['error' => 'changed'], 503);
        });
        $this->post($this->url.'/assess', ['candidate_revision' => 3, 'case_revision' => 1, 'trajectory_revision' => 1, 'draft_revision' => 1])->assertSessionHasErrors('analysis');
        $this->assertDatabaseCount('sifi_foresight_advisories',0);
    }
}
