<?php

namespace Tests\Feature\Sifi;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as OutboundRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class SifiIntelligenceBridgeTest extends TestCase
{
    use RefreshDatabase;

    private string $endpoint = '';

    private string $candidateId = '';

    private string $investigationId = '';

    private User $owner;

    private string $sid;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config()->set('sifi_aq01.base_url', 'http://127.0.0.1:8766');
        config()->set('sifi_aq01.token', str_repeat('x', 40));
        $this->owner = User::factory()->create(['email_verified_at' => now()]);
        $this->candidateId = (string) Str::uuid();
        $this->investigationId = (string) Str::uuid();
        $this->sid = str_repeat('a', 64);
        $this->endpoint = '/sifi/issue-candidates/'.$this->candidateId.'/intelligence';
        DB::table('sifi_investigations')->insert([
            'id' => $this->investigationId, 'user_id' => $this->owner->id,
            'title' => 'Fixture Investigation', 'revision' => 1,
            'review_state' => 'DRAFT', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('sifi_investigation_signals')->insert([
            'investigation_id' => $this->investigationId,
            'signal_id' => $this->sid, 'added_by_user_id' => $this->owner->id, 'created_at' => now(),
        ]);
        DB::table('sifi_issue_candidates')->insert([
            'id' => $this->candidateId, 'investigation_id' => $this->investigationId,
            'user_id' => $this->owner->id, 'title' => 'Dugaan Fixture',
            'issue_hypothesis' => 'Dugaan perlu diverifikasi',
            'evidence_gaps' => 'Data primer belum tersedia',
            'next_checks' => 'Periksa dokumen asli',
            'revision' => 2, 'review_state' => 'DRAFT', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function fakeAdvisory(): void
    {
        Http::fake(function (OutboundRequest $req) {
            $payload = $req->data();

            return Http::response([
                'schema_version' => 'sifi.intelligence.advisory.v1',
                'mode' => 'OFFLINE_FIXTURE_ONLY', 'kind' => 'REVIEW_AID_ONLY',
                'status' => 'NOT_VERIFIED', 'evidence_admission' => 'NOT_EVALUATED',
                'independence_verified' => false, 'python_issue_created' => false,
                'candidate_id' => $payload['candidate_id'],
                'candidate_revision' => $payload['candidate_revision'],
                'request_nonce' => $payload['request_nonce'],
                'signal_ids' => $payload['signal_ids'], 'signal_count' => count($payload['signal_ids']),
                'distinct_source_count' => 1, 'possible_syndication' => false,
                'observations' => ['Hanya fixture; belum diverifikasi'],
                'verification_checks' => ['Periksa dokumen primer'],
            ]);
        });
    }

    public function test_guest_cannot_request_analysis(): void
    {
        Http::preventStrayRequests();
        $this->post($this->endpoint, ['revision' => 2])->assertRedirect('/login');
        $this->assertDatabaseCount('sifi_intelligence_assessments', 0);
    }

    public function test_owner_runs_advisory_and_snapshot_is_append_only(): void
    {
        $this->fakeAdvisory();
        $this->actingAs($this->owner)->post($this->endpoint, ['revision' => 2])
            ->assertRedirect('/sifi/issue-candidates/'.$this->candidateId);
        $this->assertDatabaseHas('sifi_intelligence_assessments', [
            'candidate_id' => $this->candidateId, 'candidate_revision' => 2,
            'status' => 'ADVISORY_ONLY',
        ]);
        $this->assertDatabaseCount('sifi_intelligence_assessments', 1);
        $this->assertDatabaseHas('sifi_issue_candidates', [
            'id' => $this->candidateId, 'revision' => 2, 'review_state' => 'DRAFT',
        ]);
        $this->assertDatabaseMissing('sifi_issue_candidates', ['review_state' => 'VERIFIED']);
        $this->actingAs($this->owner)->get('/sifi/issue-candidates/'.$this->candidateId)
            ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('sifi/issue-candidate-detail')
            ->where('intelligence.0.result.evidence_admission', 'NOT_EVALUATED')
            ->where('intelligence.0.result.status', 'NOT_VERIFIED')->etc());
        Http::assertSentCount(1);
    }

    public function test_duplicate_run_is_idempotent(): void
    {
        $this->fakeAdvisory();
        $this->actingAs($this->owner)->post($this->endpoint, ['revision' => 2])->assertRedirect();
        $this->post($this->endpoint, ['revision' => 2])->assertRedirect();
        $this->assertDatabaseCount('sifi_intelligence_assessments', 1);
        Http::assertSentCount(1);
    }

    public function test_outsider_cannot_run_analysis(): void
    {
        Http::preventStrayRequests();
        $outsider = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($outsider)->post($this->endpoint, ['revision' => 2])->assertForbidden();
        $this->assertDatabaseCount('sifi_intelligence_assessments', 0);
    }

    public function test_stale_revision_rejected_without_contacting_python(): void
    {
        Http::preventStrayRequests();
        $this->actingAs($this->owner)->post($this->endpoint, ['revision' => 1])
            ->assertSessionHasErrors('revision');
        $this->assertDatabaseCount('sifi_intelligence_assessments', 0);
    }

    public function test_empty_hypothesis_rejected_before_python(): void
    {
        Http::preventStrayRequests();
        DB::table('sifi_issue_candidates')->where('id', $this->candidateId)
            ->update(['issue_hypothesis' => '']);
        $this->actingAs($this->owner)->post($this->endpoint, ['revision' => 2])
            ->assertSessionHasErrors('intelligence');
        $this->assertDatabaseCount('sifi_intelligence_assessments', 0);
    }

    public function test_python_outage_does_not_write_or_approve(): void
    {
        Http::fake(['http://127.0.0.1:8766/*' => Http::response(['error' => 'offline'], 503)]);
        $this->actingAs($this->owner)->post($this->endpoint, ['revision' => 2])
            ->assertSessionHasErrors('intelligence');
        $this->assertDatabaseCount('sifi_intelligence_assessments', 0);
        $this->assertDatabaseHas('sifi_issue_candidates', ['id' => $this->candidateId, 'revision' => 2]);
    }

    public function test_forged_admission_is_rejected(): void
    {
        Http::fake(function (OutboundRequest $req) {
            $p = $req->data();

            return Http::response([
                'schema_version' => 'sifi.intelligence.advisory.v1',
                'mode' => 'OFFLINE_FIXTURE_ONLY', 'kind' => 'REVIEW_AID_ONLY',
                'status' => 'VERIFIED', 'evidence_admission' => 'ADMITTED',
                'independence_verified' => true, 'python_issue_created' => true,
                'candidate_id' => $p['candidate_id'], 'candidate_revision' => $p['candidate_revision'],
                'request_nonce' => $p['request_nonce'], 'signal_ids' => $p['signal_ids'],
                'signal_count' => 1, 'distinct_source_count' => 1, 'possible_syndication' => false,
                'observations' => ['promoted'], 'verification_checks' => ['none'],
            ]);
        });
        $this->actingAs($this->owner)->post($this->endpoint, ['revision' => 2])
            ->assertSessionHasErrors('intelligence');
        $this->assertDatabaseCount('sifi_intelligence_assessments', 0);
    }

    public function test_candidate_change_during_http_request_cancels_result(): void
    {
        Http::fake(function (OutboundRequest $req) {
            DB::table('sifi_issue_candidates')->where('id', $this->candidateId)
                ->update(['revision' => 3]);
            $p = $req->data();

            return Http::response([
                'schema_version' => 'sifi.intelligence.advisory.v1',
                'mode' => 'OFFLINE_FIXTURE_ONLY', 'kind' => 'REVIEW_AID_ONLY',
                'status' => 'NOT_VERIFIED', 'evidence_admission' => 'NOT_EVALUATED',
                'independence_verified' => false, 'python_issue_created' => false,
                'candidate_id' => $p['candidate_id'], 'candidate_revision' => $p['candidate_revision'],
                'request_nonce' => $p['request_nonce'], 'signal_ids' => $p['signal_ids'],
                'signal_count' => 1, 'distinct_source_count' => 1, 'possible_syndication' => false,
                'observations' => ['fixture'], 'verification_checks' => ['verify'],
            ]);
        });
        $this->actingAs($this->owner)->post($this->endpoint, ['revision' => 2])
            ->assertSessionHasErrors('revision');
        $this->assertDatabaseCount('sifi_intelligence_assessments', 0);
        $this->assertDatabaseHas('sifi_issue_candidates', ['id' => $this->candidateId, 'revision' => 3]);
    }

    public function test_python_not_contacted_without_signal_references(): void
    {
        Http::preventStrayRequests();
        DB::table('sifi_investigation_signals')->delete();
        $this->actingAs($this->owner)->post($this->endpoint, ['revision' => 2])
            ->assertSessionHasErrors('intelligence');
        $this->assertDatabaseCount('sifi_intelligence_assessments', 0);
    }
}
