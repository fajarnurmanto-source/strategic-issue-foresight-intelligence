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

final class SifiIssueCaseWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private string $candidate;

    private string $investigation;

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
        $this->investigation = (string) Str::uuid();
        $this->url = '/sifi/issue-candidates/'.$this->candidate.'/issue-case';
        DB::table('sifi_investigations')->insert(['id' => $this->investigation, 'user_id' => $this->owner->id,
            'title' => 'Investigation', 'review_state' => 'DRAFT', 'revision' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('sifi_issue_candidates')->insert(['id' => $this->candidate, 'investigation_id' => $this->investigation,
            'user_id' => $this->owner->id, 'title' => 'Candidate', 'review_state' => 'DRAFT', 'revision' => 3,
            'created_at' => now(), 'updated_at' => now()]);
    }

    private function data(array $overrides = []): array
    {
        return array_merge(['candidate_revision' => 3, 'title' => 'Issue Case contoh', 'issue_domain' => 'lingkungan',
            'geography_label' => 'Kota Yogyakarta', 'scope_note' => 'Masih perlu pemeriksaan primer',
            'lifecycle_state' => 'DRAFT', 'coverage_profile_id' => null], $overrides);
    }

    private function save(array $overrides = []): string
    {
        $this->actingAs($this->owner)->post($this->url, $this->data($overrides))->assertRedirect($this->url);

        return (string) DB::table('sifi_issue_case_drafts')->where('candidate_id', $this->candidate)->value('id');
    }

    private function fake(array $overrides = []): void
    {
        Http::fake(function (Outbound $request) use ($overrides) {
            $p = $request->data();

            return Http::response(array_merge([
                'schema_version' => 'sifi.issue_scope.advisory.v1', 'mode' => 'OFFLINE_FIXTURE_ONLY',
                'kind' => 'SCOPE_LABEL_REVIEW_AID_ONLY', 'status' => 'NOT_VERIFIED',
                'relevance_verified' => false, 'issue_object_created' => false, 'evidence_admission' => 'NOT_EVALUATED',
                'candidate_id' => $p['candidate_id'], 'candidate_revision' => $p['candidate_revision'],
                'case_id' => $p['case']['id'], 'case_revision' => $p['case']['revision'],
                'request_nonce' => $p['request_nonce'], 'claim_count' => $p['claim_count'],
                'event_count' => $p['event_count'], 'link_count' => $p['link_count'],
                'scope_marker' => 'NO_PROFILE', 'domain_label_match' => false, 'region_label_match' => false,
                'verification_checks' => ['Periksa sumber primer', 'Jangan anggap label bukti relevansi',
                    'Periksa lokasi kejadian', 'Periksa independensi sumber']], $overrides));
        });
    }

    public function test_guest_cannot_view_or_save(): void
    {
        $this->get($this->url)->assertRedirect('/login');
        $this->post($this->url, [])->assertRedirect('/login');
    }

    public function test_owner_can_create_only_unverified_draft(): void
    {
        $id = $this->save();
        $this->assertDatabaseHas('sifi_issue_case_drafts', ['id' => $id, 'lifecycle_state' => 'DRAFT', 'verification_state' => 'NOT_VERIFIED']);
        $this->assertDatabaseCount('sifi_issue_case_audit', 1);
        $this->get($this->url)->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->component('sifi/issue-case-workspace')->where('case.id', $id)->etc());
    }

    public function test_outsider_denied_before_invalid_payload(): void
    {
        $other = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($other)->get($this->url)->assertForbidden();
        $this->post($this->url, ['verified' => true])->assertForbidden();
        $this->post($this->url.'/assess', [])->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_watchlist_not_verification_and_revision(): void
    {
        $id = $this->save();
        $this->post($this->url, $this->data(['case_revision' => 1, 'lifecycle_state' => 'WATCHLIST']))->assertRedirect($this->url);
        $this->assertDatabaseHas('sifi_issue_case_drafts', ['id' => $id, 'revision' => 2, 'lifecycle_state' => 'WATCHLIST', 'verification_state' => 'NOT_VERIFIED']);
        $this->assertDatabaseCount('sifi_issue_case_audit', 2);
    }

    public function test_stale_revision_cannot_overwrite(): void
    {
        $this->save();
        $this->post($this->url, $this->data(['case_revision' => 999]))->assertSessionHasErrors('case_revision');
        $this->assertDatabaseCount('sifi_issue_case_audit', 1);
    }

    public function test_unknown_profile_and_foreign_profile_rejected(): void
    {
        $other = User::factory()->create(['email_verified_at' => now()]);
        $profile = (string) Str::uuid();
        DB::table('sifi_coverage_profiles')->insert(['id' => $profile, 'user_id' => $other->id, 'name' => 'Other', 'level' => 'CITY',
            'region_name' => 'Kota Lain', 'domains_json' => '["lingkungan"]', 'profile_state' => 'DRAFT', 'collector_state' => 'OFF',
            'revision' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($this->owner)->post($this->url, $this->data(['coverage_profile_id' => $profile]))
            ->assertSessionHasErrors('coverage_profile_id');
        $this->assertDatabaseCount('sifi_issue_case_drafts', 0);
    }

    public function test_claim_of_approved_evidence_prohibited(): void
    {
        $this->actingAs($this->owner)->post($this->url, $this->data(['verified' => true]))->assertSessionHasErrors('verified');
        $this->assertDatabaseCount('sifi_issue_case_drafts', 0);
    }

    public function test_snapshot_is_read_only_and_idempotent(): void
    {
        $this->save();
        $this->fake();
        $this->post($this->url.'/assess', ['candidate_revision' => 3, 'case_revision' => 1])->assertRedirect($this->url);
        $this->post($this->url.'/assess', ['candidate_revision' => 3, 'case_revision' => 1])->assertRedirect($this->url);
        $this->assertDatabaseCount('sifi_issue_case_advisories', 1);
        Http::assertSentCount(1);
        $this->assertDatabaseHas('sifi_issue_case_advisories', ['status' => 'ADVISORY_ONLY']);
        $this->assertDatabaseHas('sifi_issue_case_drafts', ['verification_state' => 'NOT_VERIFIED']);
    }

    public function test_forged_python_admission_fails_closed(): void
    {
        $this->save();
        $this->fake(['relevance_verified' => true]);
        $this->post($this->url.'/assess', ['candidate_revision' => 3, 'case_revision' => 1])->assertSessionHasErrors('analysis');
        $this->assertDatabaseCount('sifi_issue_case_advisories', 0);
    }

    public function test_python_offline_fails_closed(): void
    {
        $this->save();
        Http::fake(['http://127.0.0.1:8766/*' => Http::response(['error' => 'offline'], 503)]);
        $this->post($this->url.'/assess', ['candidate_revision' => 3, 'case_revision' => 1])->assertSessionHasErrors('analysis');
        $this->assertDatabaseCount('sifi_issue_case_advisories', 0);
    }

    public function test_changed_case_during_python_discarded(): void
    {
        $this->save();
        Http::fake(function (Outbound $r) {
            DB::table('sifi_issue_case_drafts')->where('candidate_id', $this->candidate)->update(['scope_note' => 'Changed concurrently', 'revision' => 2]);
            $p = $r->data();

            return Http::response([
                'schema_version' => 'sifi.issue_scope.advisory.v1', 'mode' => 'OFFLINE_FIXTURE_ONLY',
                'kind' => 'SCOPE_LABEL_REVIEW_AID_ONLY', 'status' => 'NOT_VERIFIED',
                'relevance_verified' => false, 'issue_object_created' => false, 'evidence_admission' => 'NOT_EVALUATED',
                'candidate_id' => $p['candidate_id'], 'candidate_revision' => $p['candidate_revision'],
                'case_id' => $p['case']['id'], 'case_revision' => $p['case']['revision'], 'request_nonce' => $p['request_nonce'],
                'claim_count' => 0, 'event_count' => 0, 'link_count' => 0, 'scope_marker' => 'NO_PROFILE',
                'domain_label_match' => false, 'region_label_match' => false, 'verification_checks' => ['a', 'b', 'c', 'd']]);
        });
        $this->post($this->url.'/assess', ['candidate_revision' => 3, 'case_revision' => 1])->assertSessionHasErrors('analysis');
        $this->assertDatabaseCount('sifi_issue_case_advisories', 0);
    }

    public function test_no_python_issue_or_admitted_evidence_created(): void
    {
        $this->save();
        $this->assertFalse(DB::getSchemaBuilder()->hasTable('sifi_python_issues'));
        $this->assertFalse(DB::getSchemaBuilder()->hasTable('sifi_qualified_evidence'));
    }
}
