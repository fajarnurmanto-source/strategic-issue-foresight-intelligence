<?php

namespace Tests\Feature\Sifi;

use App\Models\{SifiIssueCandidate, User};
use App\Services\Sifi\ClaimComparisonPreviewInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Http};
use Illuminate\Support\Str;
use Tests\TestCase;

final class SifiWorkbenchContextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::preventStrayRequests();
    }

    private function owner(): User { return User::factory()->create(['email_verified_at' => now()]); }

    private function candidate(User $owner, string $title = 'Synthetic candidate'): SifiIssueCandidate
    {
        $inv = (string) Str::uuid();
        DB::table('sifi_investigations')->insert(['id' => $inv, 'user_id' => $owner->id,
            'title' => $title, 'review_state' => 'DRAFT', 'revision' => 1, 'created_at' => now(), 'updated_at' => now()]);
        return SifiIssueCandidate::create(['id' => (string) Str::uuid(), 'investigation_id' => $inv,
            'user_id' => $owner->id, 'title' => $title, 'issue_hypothesis' => 'Synthetic hypothesis',
            'exposure_notes' => 'Reason written by analyst', 'evidence_gaps' => 'Primary evidence absent',
            'next_checks' => 'Compare original document', 'review_state' => 'DRAFT', 'revision' => 1]);
    }

    private function graph(User $owner, SifiIssueCandidate $candidate): string
    {
        $e = (string) Str::uuid(); $c = (string) Str::uuid(); $event = (string) Str::uuid();
        DB::table('sifi_investigation_signals')->insert(['investigation_id' => $candidate->investigation_id,
            'signal_id' => str_repeat('a', 64), 'added_by_user_id' => $owner->id, 'created_at' => now()]);
        DB::table('sifi_evidence_drafts')->insert(['id' => $e, 'candidate_id' => $candidate->id,
            'owner_user_id' => $owner->id, 'signal_id' => str_repeat('a', 64), 'claim_text' => 'PRIVATE_EVIDENCE_BODY',
            'revision' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('sifi_claim_drafts')->insert(['id' => $c, 'candidate_id' => $candidate->id,
            'evidence_id' => $e, 'owner_user_id' => $owner->id, 'statement' => 'PRIVATE_CLAIM_BODY',
            'attribution' => 'PRIVATE_ATTRIBUTION', 'modality' => 'REPORTED', 'revision' => 1,
            'created_at' => now(), 'updated_at' => now()]);
        DB::table('sifi_event_hypotheses')->insert(['id' => $event, 'candidate_id' => $candidate->id,
            'owner_user_id' => $owner->id, 'summary' => 'PRIVATE_EVENT_BODY', 'revision' => 1,
            'created_at' => now(), 'updated_at' => now()]);
        DB::table('sifi_claim_event_links')->insert(['candidate_id' => $candidate->id, 'claim_id' => $c,
            'event_id' => $event, 'relation' => 'UNKNOWN', 'actor_user_id' => $owner->id, 'created_at' => now()]);
        return $c;
    }

    private function focus(User $owner, ?string $id = null): ?array
    {
        $r = $this->actingAs($owner)->get('/sifi/workbench'.($id ? '?candidate='.$id : ''))->assertOk();
        return $r->viewData('page')['props']['focus'];
    }

    public function test_guest_and_unverified_user_cannot_read_context(): void
    {
        $this->get('/sifi/workbench')->assertRedirect('/login');
        $this->actingAs(User::factory()->unverified()->create())->getJson('/sifi/workbench')->assertForbidden();
    }

    public function test_foreign_selection_is_denied_before_projection(): void
    {
        $owner = $this->owner(); $foreign = $this->candidate($this->owner(), 'Private foreign title');
        $mock = \Mockery::mock(ClaimComparisonPreviewInput::class); $mock->shouldNotReceive('read');
        $this->app->instance(ClaimComparisonPreviewInput::class, $mock);
        $this->actingAs($owner)->getJson('/sifi/workbench?candidate='.$foreign->id)->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_malformed_and_missing_selection_fail_closed(): void
    {
        $this->actingAs($this->owner())->getJson('/sifi/workbench?candidate=not-a-uuid')->assertUnprocessable();
        $this->getJson('/sifi/workbench?candidate='.(string) Str::uuid())->assertNotFound();
    }

    public function test_empty_workbench_has_no_invented_context(): void
    {
        $this->assertNull($this->focus($this->owner()));
    }

    public function test_owner_context_reuses_real_diagnostics_and_preview_fingerprint(): void
    {
        $owner = $this->owner(); $c = $this->candidate($owner); $this->graph($owner, $c);
        $f = $this->focus($owner, $c->id);
        $input = app(ClaimComparisonPreviewInput::class)->read($c);
        $this->assertSame(hash('sha256', json_encode($input, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), $f['claim_input_digest']);
        $this->assertSame($c->id, $f['candidate']['id']);
        $this->assertSame(1, $f['signal_reference_count']);
        $this->assertSame(1, $f['diagnostics']['claim_count']);
        $this->assertSame(1, $f['diagnostics']['event_count']);
        $this->assertSame(1, $f['diagnostics']['link_count']);
        $this->assertFalse($f['diagnostics']['comparison_eligible']);
        $this->assertFalse($f['diagnostics']['review_authorized']);
        $this->assertSame('NOT_ATTESTED', $f['diagnostics']['historical_custody']);
        $this->assertContains('NON_ASSERTED_DRAFT_MODALITY', $f['diagnostics']['claims'][0]['reason_codes']);
        foreach (['PRIVATE_CLAIM_BODY','PRIVATE_EVENT_BODY','PRIVATE_EVIDENCE_BODY','PRIVATE_ATTRIBUTION'] as $private) {
            $this->assertStringNotContainsString($private, json_encode($f));
        }
        Http::assertNothingSent();
    }

    public function test_claim_change_without_candidate_revision_refreshes_digest(): void
    {
        $owner = $this->owner(); $c = $this->candidate($owner); $claim = $this->graph($owner, $c);
        $before = $this->focus($owner, $c->id);
        DB::table('sifi_claim_drafts')->where('id', $claim)->update(['revision' => 2]);
        $after = $this->focus($owner, $c->id);
        $this->assertSame($before['candidate']['revision'], $after['candidate']['revision']);
        $this->assertNotSame($before['claim_input_digest'], $after['claim_input_digest']);
        $this->assertSame(2, $after['diagnostics']['claims'][0]['claim_revision']);
    }

    public function test_selection_outside_latest_twelve_does_not_widen_ownership(): void
    {
        $owner = $this->owner(); $old = $this->candidate($owner, 'Older selected candidate');
        DB::table('sifi_issue_candidates')->where('id', $old->id)->update(['updated_at' => now()->subDay()]);
        for ($i = 0; $i < 13; $i++) $this->candidate($owner, 'New candidate '.$i);
        $this->candidate($this->owner(), 'Other owner');
        $r = $this->actingAs($owner)->get('/sifi/workbench?candidate='.$old->id)->assertOk();
        $props = $r->viewData('page')['props'];
        $this->assertCount(12, $props['cases']); $this->assertSame(14, $props['total']);
        $this->assertSame($old->id, $props['focus']['candidate']['id']);
        $this->assertNotContains('Other owner', array_column($props['cases'], 'title'));
    }

    public function test_get_context_is_read_only_without_inference_calls(): void
    {
        $owner = $this->owner(); $c = $this->candidate($owner); $this->graph($owner, $c);
        $before = DB::table('sifi_issue_candidates')->get()->toJson();
        $queries = []; DB::listen(function ($q) use (&$queries) { $queries[] = $q->sql; });
        $this->focus($owner, $c->id);
        $this->assertSame($before, DB::table('sifi_issue_candidates')->get()->toJson());
        $this->assertDatabaseCount('sifi_issue_candidate_audit', 0);
        $this->assertDatabaseCount('sifi_intelligence_assessments', 0);
        $this->assertDatabaseCount('sifi_issue_case_drafts', 0);
        foreach ($queries as $sql) $this->assertDoesNotMatchRegularExpression('/^\s*(insert|update|delete|replace)\b/i', $sql);
        Http::assertNothingSent();
    }

    public function test_native_next_checks_save_preserves_fields_and_appends_audit(): void
    {
        $owner = $this->owner(); $c = $this->candidate($owner); $f = $this->focus($owner, $c->id);
        $payload = $f['candidate']; unset($payload['id']); $payload['next_checks'] = 'New analyst follow-up';
        $this->patch('/sifi/issue-candidates/'.$c->id, $payload)->assertRedirect();
        $after = $this->focus($owner, $c->id);
        $this->assertSame(2, $after['candidate']['revision']);
        $this->assertSame('New analyst follow-up', $after['candidate']['next_checks']);
        foreach (['title','issue_hypothesis','exposure_notes','evidence_gaps','review_state'] as $key) {
            $this->assertSame($f['candidate'][$key], $after['candidate'][$key]);
        }
        $this->assertDatabaseHas('sifi_issue_candidate_audit', ['candidate_id' => $c->id, 'revision' => 2, 'action' => 'CANDIDATE_EDITED']);
        $this->assertDatabaseCount('sifi_issue_candidate_audit', 1);
    }

    public function test_stale_followup_cannot_overwrite_a_newer_session(): void
    {
        $owner = $this->owner(); $c = $this->candidate($owner); $payload = $this->focus($owner, $c->id)['candidate']; unset($payload['id']);
        $payload['next_checks'] = 'First session wins';
        $this->patch('/sifi/issue-candidates/'.$c->id, $payload)->assertRedirect();
        $payload['next_checks'] = 'Stale overwrite attempt';
        $this->patchJson('/sifi/issue-candidates/'.$c->id, $payload)->assertUnprocessable()->assertJsonValidationErrors('revision');
        $this->assertSame('First session wins', $c->fresh()->next_checks);
        $this->assertDatabaseCount('sifi_issue_candidate_audit', 1);
    }

    public function test_corrupt_projection_does_not_claim_readiness(): void
    {
        $owner = $this->owner(); $c = $this->candidate($owner);
        $mock = \Mockery::mock(ClaimComparisonPreviewInput::class); $mock->shouldReceive('read')->once()->andReturn([]);
        $this->app->instance(ClaimComparisonPreviewInput::class, $mock);
        $this->actingAs($owner)->getJson('/sifi/workbench?candidate='.$c->id)->assertServiceUnavailable();
        $this->assertSame(1, $c->fresh()->revision);
    }
}
