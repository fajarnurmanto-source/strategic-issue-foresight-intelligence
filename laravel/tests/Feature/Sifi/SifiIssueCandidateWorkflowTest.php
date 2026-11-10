<?php

namespace Tests\Feature\Sifi;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class SifiIssueCandidateWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function investigation(User $owner): string
    {
        $id = (string) Str::uuid();
        DB::table('sifi_investigations')->insert([
            'id' => $id, 'user_id' => $owner->id, 'title' => 'Investigasi Fixture',
            'review_state' => 'DRAFT', 'revision' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        return $id;
    }

    private function owner(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    private function create(User $owner, string $investigationId): string
    {
        $this->actingAs($owner)->post('/sifi/issue-candidates', ['investigation_id' => $investigationId])->assertRedirect();
        return (string) DB::table('sifi_issue_candidates')->where('investigation_id', $investigationId)->value('id');
    }

    private function payload(int $revision = 1, string $state = 'DRAFT'): array
    {
        return [
            'title' => 'Dugaan isu drainase wilayah',
            'issue_hypothesis' => 'Perubahan pelaporan drainase mungkin terkait kondisi infrastruktur',
            'exposure_notes' => 'Cakupan wilayah UNKNOWN',
            'evidence_gaps' => 'Belum ada sumber primer atau pemeriksaan independen',
            'next_checks' => 'Bandingkan sumber resmi dan data teknis',
            'review_state' => $state,
            'revision' => $revision,
        ];
    }

    public function test_guests_must_sign_in(): void
    {
        $this->get('/sifi/issue-candidates')->assertRedirect('/login');
        $this->post('/sifi/issue-candidates', ['investigation_id' => (string) Str::uuid()])->assertRedirect('/login');
    }

    public function test_owner_creates_a_single_draft_and_append_only_creation_audit(): void
    {
        $user = $this->owner(); $investigation = $this->investigation($user);
        $id = $this->create($user, $investigation);
        $this->assertNotEmpty($id);
        $this->assertDatabaseHas('sifi_issue_candidates', ['id' => $id, 'investigation_id' => $investigation, 'user_id' => $user->id, 'review_state' => 'DRAFT', 'revision' => 1]);
        $this->assertDatabaseHas('sifi_issue_candidate_audit', ['candidate_id' => $id, 'revision' => 1, 'action' => 'CREATED']);
        $this->assertDatabaseCount('sifi_issue_candidate_audit', 1);
        $this->get('/sifi/issue-candidates/' . $id)->assertOk();
    }

    public function test_repeated_creation_is_idempotent_and_does_not_append_audit(): void
    {
        $user = $this->owner(); $investigation = $this->investigation($user);
        $id = $this->create($user, $investigation);
        $this->post('/sifi/issue-candidates', ['investigation_id' => $investigation])
            ->assertRedirect('/sifi/issue-candidates/' . $id);
        $this->assertDatabaseCount('sifi_issue_candidates', 1);
        $this->assertDatabaseCount('sifi_issue_candidate_audit', 1);
    }

    public function test_policy_denies_access_and_mutation_by_another_user(): void
    {
        $owner = $this->owner(); $investigation = $this->investigation($owner);
        $id = $this->create($owner, $investigation);
        $outsider = $this->owner();
        $this->actingAs($outsider)->post('/sifi/issue-candidates', ['investigation_id' => $investigation])->assertForbidden();
        $this->get('/sifi/issue-candidates/' . $id)->assertForbidden();
        $this->patch('/sifi/issue-candidates/' . $id, $this->payload())->assertForbidden();
        $this->assertDatabaseCount('sifi_issue_candidate_audit', 1);
    }

    public function test_update_creates_revision_and_preserves_previous_snapshot(): void
    {
        $user = $this->owner(); $id = $this->create($user, $this->investigation($user));
        $this->patch('/sifi/issue-candidates/' . $id, $this->payload())->assertRedirect();
        $this->assertDatabaseHas('sifi_issue_candidates', ['id' => $id, 'revision' => 2, 'review_state' => 'DRAFT']);
        $this->assertDatabaseHas('sifi_issue_candidate_audit', ['candidate_id' => $id, 'revision' => 2, 'action' => 'CANDIDATE_EDITED']);
        $this->assertDatabaseCount('sifi_issue_candidate_audit', 2);
    }

    public function test_stale_revision_cannot_overwrite_saved_assessment(): void
    {
        $user = $this->owner(); $id = $this->create($user, $this->investigation($user));
        $this->patch('/sifi/issue-candidates/' . $id, $this->payload())->assertRedirect();
        $this->patch('/sifi/issue-candidates/' . $id, $this->payload())->assertSessionHasErrors('revision');
        $this->assertDatabaseCount('sifi_issue_candidate_audit', 2);
    }

    public function test_review_requested_requires_hypothesis_gaps_and_next_checks(): void
    {
        $user = $this->owner(); $id = $this->create($user, $this->investigation($user));
        $p = $this->payload(1, 'REVIEW_REQUESTED');
        $p['issue_hypothesis'] = '  '; $p['evidence_gaps'] = ''; $p['next_checks'] = '';
        $this->patch('/sifi/issue-candidates/' . $id, $p)
            ->assertSessionHasErrors(['issue_hypothesis', 'evidence_gaps', 'next_checks']);
        $this->assertDatabaseHas('sifi_issue_candidates', ['id' => $id, 'revision' => 1]);
        $this->patch('/sifi/issue-candidates/' . $id, $this->payload(1, 'REVIEW_REQUESTED'))->assertRedirect();
        $this->assertDatabaseHas('sifi_issue_candidates', ['id' => $id, 'review_state' => 'REVIEW_REQUESTED', 'revision' => 2]);
    }

    public function test_unapproved_states_are_not_allowed(): void
    {
        $user = $this->owner(); $id = $this->create($user, $this->investigation($user));
        $this->patch('/sifi/issue-candidates/' . $id, $this->payload(1, 'EVIDENCE_ADMITTED'))->assertSessionHasErrors('review_state');
        $this->assertDatabaseCount('sifi_issue_candidate_audit', 1);
    }

    public function test_index_only_lists_current_users_proposals(): void
    {
        $user = $this->owner(); $id = $this->create($user, $this->investigation($user));
        $outsider = $this->owner();
        $this->actingAs($outsider)->get('/sifi/issue-candidates')->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) =>
                $page->component('sifi/issue-candidates-index')->where('candidates.total', 0)->etc());
        $this->actingAs($user)->get('/sifi/issue-candidates')->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) =>
                $page->component('sifi/issue-candidates-index')->where('candidates.total', 1)->etc());
    }

    public function test_no_evidence_or_python_issue_table_created_by_candidate(): void
    {
        $user = $this->owner(); $this->create($user, $this->investigation($user));
        $schema = DB::getSchemaBuilder();
        $this->assertTrue($schema->hasTable('sifi_issue_candidates'));
        $this->assertFalse($schema->hasTable('sifi_python_issues'));
        $this->assertFalse($schema->hasTable('sifi_qualified_evidence'));
    }

    public function test_invalid_investigation_uuid_is_rejected(): void
    {
        $this->actingAs($this->owner())->post('/sifi/issue-candidates', ['investigation_id' => 'hello'])
            ->assertSessionHasErrors('investigation_id');
    }
}
