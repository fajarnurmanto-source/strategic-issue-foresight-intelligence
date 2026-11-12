<?php

namespace Tests\Feature\Sifi;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/** Read-only, owner-scoped integration hub tests; no source admission. */
final class SifiWorkbenchWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function user(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    private function candidate(User $user, string $title): array
    {
        $investigation = (string) Str::uuid();
        $candidate = (string) Str::uuid();
        DB::table('sifi_investigations')->insert([
            'id' => $investigation, 'user_id' => $user->id,
            'title' => $title, 'review_state' => 'DRAFT', 'revision' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('sifi_issue_candidates')->insert([
            'id' => $candidate, 'investigation_id' => $investigation,
            'user_id' => $user->id, 'title' => $title,
            'issue_hypothesis' => 'Unverified hypothesis',
            'evidence_gaps' => 'No primary evidence', 'next_checks' => 'Review source',
            'review_state' => 'DRAFT', 'revision' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['candidate' => $candidate, 'investigation' => $investigation];
    }

    public function test_guest_cannot_read_workbench(): void
    {
        $this->get('/sifi/workbench')->assertRedirect('/login');
    }

    public function test_owner_sees_empty_workbench_with_offline_warning(): void
    {
        $this->actingAs($this->user())->get('/sifi/workbench')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('sifi/workbench')
                ->where('total', 0)
                ->has('cases', 0)
                ->where('mode', 'OFFLINE_FIXTURE_ONLY')
                ->where('evidence_state', 'NOT_EVALUATED')
                ->where('production_authorized', false)
                ->etc());
    }

    public function test_owner_only_sees_own_candidate(): void
    {
        $owner = $this->user();
        $this->candidate($owner, 'Mine');
        $this->candidate($this->user(), 'Private for other user');
        $this->actingAs($owner)->get('/sifi/workbench')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('sifi/workbench')
                ->where('total', 1)
                ->has('cases', 1)
                ->where('cases.0.title', 'Mine')
                ->where('cases.0.has_case', false)
                ->where('cases.0.has_trajectory', false)
                ->where('cases.0.has_foresight', false)
                ->where('cases.0.has_brief', false)
                ->etc());
    }

    public function test_existing_foresight_and_brief_are_discoverable(): void
    {
        $owner = $this->user();
        $id = $this->candidate($owner, 'Integrated case')['candidate'];
        $caseId = (string) Str::uuid();
        $trajectoryId = (string) Str::uuid();
        $foresightId = (string) Str::uuid();
        DB::table('sifi_issue_case_drafts')->insert([
            'id' => $caseId, 'candidate_id' => $id, 'owner_user_id' => $owner->id,
            'title' => 'Issue draft', 'issue_domain' => 'environment',
            'geography_label' => 'UNKNOWN', 'scope_note' => 'No real evidence',
            'lifecycle_state' => 'DRAFT', 'verification_state' => 'NOT_VERIFIED', 'revision' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('sifi_trajectory_drafts')->insert([
            'id' => $trajectoryId, 'case_id' => $caseId, 'owner_user_id' => $owner->id,
            'horizon' => 'UNSPECIFIED', 'trajectory_hypothesis' => 'Unknown',
            'risk_hypothesis' => 'Unknown', 'opportunity_hypothesis' => 'Unknown',
            'watch_condition' => 'Need data', 'uncertainty_note' => 'No observations',
            'revision' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('sifi_foresight_drafts')->insert([
            'id' => $foresightId, 'trajectory_id' => $trajectoryId, 'owner_user_id' => $owner->id,
            'baseline_scenario' => 'Unknown baseline', 'alternative_scenario' => 'Alternative',
            'stress_scenario' => 'Stress', 'critical_drivers' => 'Unknown',
            'falsification_checks' => 'Verify first', 'decision_options' => 'Not approved',
            'uncertainty_note' => 'No evidence', 'revision' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('sifi_executive_briefs')->insert([
            'id' => (string) Str::uuid(), 'candidate_id' => $id, 'owner_user_id' => $owner->id,
            'title' => 'Internal draft', 'audience' => 'Internal',
            'executive_summary' => 'NOT VERIFIED', 'decision_questions' => 'What evidence?',
            'verification_priorities' => 'Find originals', 'limitations' => 'Offline',
            'review_state' => 'DRAFT', 'input_digest' => str_repeat('a', 64),
            'input_snapshot_json' => '{}', 'revision' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs($owner)->get('/sifi/workbench')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('sifi/workbench')
                ->where('cases.0.has_case', true)
                ->where('cases.0.has_trajectory', true)
                ->where('cases.0.has_foresight', true)
                ->where('cases.0.has_brief', true)
                ->etc());
    }

    public function test_workbench_is_read_only(): void
    {
        $owner = $this->user();
        $this->candidate($owner, 'Never mutated');
        $this->actingAs($owner)->get('/sifi/workbench')->assertOk();
        $this->assertDatabaseCount('sifi_issue_candidates', 1);
        $this->assertDatabaseCount('sifi_issue_case_drafts', 0);
        $this->assertDatabaseCount('sifi_foresight_drafts', 0);
        $this->assertDatabaseCount('sifi_executive_briefs', 0);
    }
}
