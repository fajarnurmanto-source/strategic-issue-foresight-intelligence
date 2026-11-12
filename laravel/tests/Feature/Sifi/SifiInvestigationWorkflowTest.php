<?php

namespace Tests\Feature\Sifi;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class SifiInvestigationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private const SIGNAL_A = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const SIGNAL_B = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config()->set('sifi_aq01.base_url', 'http://127.0.0.1:8766');
        config()->set('sifi_aq01.token', str_repeat('t', 48));
    }

    private function offlineFixture(string $id): void
    {
        Http::fake([
            'http://127.0.0.1:8766/v1/signals/*/history' => Http::response([
                'mode' => 'OFFLINE_FIXTURE_ONLY',
                'signal_id' => $id,
                'versions' => [[
                    'signal_id' => $id, 'title' => 'Drainase kota dievaluasi',
                    'source_id' => 'SRC-MEDIA-ALPHA', 'publisher_or_owner' => 'Synthetic publisher',
                    'provenance_mode' => 'OFFLINE_FIXTURE', 'evidence_admission' => 'NOT_EVALUATED',
                    'evidence_ids' => [], 'issue_ids' => [], 'published_at' => '2026-10-19T08:00:00+00:00',
                ]],
            ], 200),
        ]);
    }

    private function analyst(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    public function test_guests_cannot_create_or_view_investigations(): void
    {
        $this->get('/sifi/investigations')->assertRedirect('/login');
        $this->post('/sifi/investigations', ['signal_id' => self::SIGNAL_A])->assertRedirect('/login');
    }

    public function test_creates_a_personal_draft_but_no_evidence_or_issue(): void
    {
        $this->offlineFixture(self::SIGNAL_A);
        $this->actingAs($this->analyst())
            ->post('/sifi/investigations', ['signal_id' => self::SIGNAL_A])
            ->assertRedirect();
        $this->assertDatabaseCount('sifi_investigations', 1);
        $this->assertDatabaseHas('sifi_investigations', ['review_state' => 'DRAFT', 'revision' => 1]);
        $this->assertDatabaseHas('sifi_investigation_signals', ['signal_id' => self::SIGNAL_A]);
        $this->assertDatabaseHas('sifi_investigation_audit', ['action' => 'CREATED', 'revision' => 1]);
        $this->assertDatabaseCount('sifi_investigation_audit', 1);
    }

    public function test_rejects_missing_or_wrongly_qualified_fixture(): void
    {
        Http::fake(['*' => Http::response(['error' => 'not_found'], 404)]);
        $this->actingAs($this->analyst())
            ->post('/sifi/investigations', ['signal_id' => self::SIGNAL_A])
            ->assertSessionHasErrors('signal_id');
        $this->assertDatabaseCount('sifi_investigations', 0);
    }

    public function test_two_users_cannot_access_each_others_drafts(): void
    {
        $this->offlineFixture(self::SIGNAL_A);
        $owner = $this->analyst();
        $this->actingAs($owner)->post('/sifi/investigations', ['signal_id' => self::SIGNAL_A]);
        $id = DB::table('sifi_investigations')->value('id');
        $outsider = $this->analyst();
        $this->actingAs($outsider)->get('/sifi/investigations/'.$id)->assertNotFound();
        $this->actingAs($outsider)->patch('/sifi/investigations/'.$id, [
            'title' => 'Unauthorized change', 'review_state' => 'DRAFT', 'revision' => 1,
        ])->assertNotFound();
        $this->actingAs($outsider)->post('/sifi/investigations/'.$id.'/signals', [
            'signal_id' => self::SIGNAL_A,
        ])->assertNotFound();
    }

    public function test_assessment_updates_append_audit_and_prevent_stale_overwrite(): void
    {
        $this->offlineFixture(self::SIGNAL_A);
        $this->actingAs($this->analyst())->post('/sifi/investigations', ['signal_id' => self::SIGNAL_A]);
        $id = DB::table('sifi_investigations')->value('id');
        $assessment = [
            'title' => 'Kandidat drainase wilayah',
            'claim_hypothesis' => 'Hipotesis belum diverifikasi',
            'evidence_gaps' => 'Belum ada sumber primer independen',
            'next_steps' => 'Periksa data resmi dan lapangan',
            'review_state' => 'REVIEW_REQUESTED', 'revision' => 1,
        ];
        $this->patch('/sifi/investigations/'.$id, $assessment)->assertRedirect();
        $this->assertDatabaseHas('sifi_investigations', ['id' => $id, 'revision' => 2, 'review_state' => 'REVIEW_REQUESTED']);
        $this->assertDatabaseHas('sifi_investigation_audit', ['investigation_id' => $id, 'revision' => 2, 'action' => 'ASSESSMENT_EDITED']);
        $this->patch('/sifi/investigations/'.$id, $assessment)->assertSessionHasErrors('revision');
        $this->assertDatabaseCount('sifi_investigation_audit', 2);
    }

    public function test_rejects_fake_review_approval_states(): void
    {
        $this->offlineFixture(self::SIGNAL_A);
        $this->actingAs($this->analyst())->post('/sifi/investigations', ['signal_id' => self::SIGNAL_A]);
        $id = DB::table('sifi_investigations')->value('id');
        $this->patch('/sifi/investigations/'.$id, [
            'title' => 'Valid title', 'review_state' => 'EVIDENCE_ADMITTED', 'revision' => 1,
        ])->assertSessionHasErrors('review_state');
        $this->assertDatabaseCount('sifi_investigation_audit', 1);
    }

    public function test_attach_references_only_and_idempotent_on_duplicate(): void
    {
        $this->offlineFixture(self::SIGNAL_A);
        $this->actingAs($this->analyst())->post('/sifi/investigations', ['signal_id' => self::SIGNAL_A]);
        $id = DB::table('sifi_investigations')->value('id');
        // A request repeating an existing Signal ID must be a no-op.
        $this->post('/sifi/investigations/'.$id.'/signals', ['signal_id' => self::SIGNAL_A])->assertRedirect();
        $this->assertDatabaseCount('sifi_investigation_signals', 1);
        $this->assertDatabaseCount('sifi_investigation_audit', 1);
    }

    public function test_rejects_fixture_that_pretends_evidence_is_admitted(): void
    {
        Http::fake(['*' => Http::response([
            'mode' => 'OFFLINE_FIXTURE_ONLY',
            'versions' => [[
                'signal_id' => self::SIGNAL_A, 'title' => 'Untrusted item',
                'source_id' => 'SRC-MEDIA-ALPHA', 'provenance_mode' => 'LIVE',
                'evidence_admission' => 'ADMITTED', 'evidence_ids' => ['FAKE'], 'issue_ids' => [],
            ]],
        ], 200)]);
        $this->actingAs($this->analyst())
            ->post('/sifi/investigations', ['signal_id' => self::SIGNAL_A])
            ->assertSessionHasErrors('signal_id');
        $this->assertDatabaseCount('sifi_investigations', 0);
    }

    public function test_attaching_new_offline_signal_increments_revision(): void
    {
        // Single dynamic offline stub handles both ids; no source networking.
        Http::fake(function (Request $request) {
            preg_match('#/v1/signals/([0-9a-f]{64})/history$#D', $request->url(), $match);
            $id = $match[1] ?? '';

            return Http::response([
                'mode' => 'OFFLINE_FIXTURE_ONLY',
                'versions' => [[
                    'signal_id' => $id, 'title' => 'Offline fixture',
                    'source_id' => 'SRC-MEDIA-ALPHA',
                    'provenance_mode' => 'OFFLINE_FIXTURE', 'evidence_admission' => 'NOT_EVALUATED',
                    'evidence_ids' => [], 'issue_ids' => [],
                ]],
            ], 200);
        });
        $this->actingAs($this->analyst())->post('/sifi/investigations', ['signal_id' => self::SIGNAL_A]);
        $id = DB::table('sifi_investigations')->value('id');
        $this->post('/sifi/investigations/'.$id.'/signals', ['signal_id' => self::SIGNAL_B])
            ->assertRedirect();
        $this->assertDatabaseCount('sifi_investigation_signals', 2);
        $this->assertDatabaseHas('sifi_investigation_audit', ['investigation_id' => $id, 'revision' => 2, 'action' => 'SIGNAL_ATTACHED']);
    }

    public function test_personal_draft_can_be_read_when_python_api_is_offline(): void
    {
        $this->offlineFixture(self::SIGNAL_A);
        $this->actingAs($this->analyst())->post('/sifi/investigations', ['signal_id' => self::SIGNAL_A]);
        $id = DB::table('sifi_investigations')->value('id');
        Http::fake(['*' => Http::response(['error' => 'offline'], 503)]);
        $this->get('/sifi/investigations/'.$id)->assertOk();
    }
}
