<?php

namespace Tests\Feature\Sifi;

use App\Models\SifiMonitoringObject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class CoreV2ShadowPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_handoff_is_idempotent_and_shadow_confirms_only_owned_lineage(): void
    {
        $this->withoutVite();
        Http::preventStrayRequests();
        Storage::fake('local');
        config(['sifi_core_v2.shadow_workbench' => true]);
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $object = SifiMonitoringObject::create([
            'id' => (string) Str::uuid(), 'user_id' => $owner->id,
            'name' => 'Objek fixture', 'description' => 'Objek integrasi.',
            'category' => 'Wilayah', 'aliases_json' => [], 'keywords_json' => [],
            'exclusions_json' => [], 'geography' => 'Indonesia', 'objective' => 'Uji alur',
            'horizon' => '90 hari', 'state' => 'CONFIGURED', 'revision' => 1,
        ]);
        $research = "/sifi/monitoring-objects/{$object->id}/research";
        $pilot = "/sifi/monitoring-objects/{$object->id}/e2e01";
        $this->get($pilot)->assertRedirect(route('login'));
        $this->actingAs($owner)->post($research, [
            'question' => 'Sumber publik apa yang tersedia untuk objek ini?',
            'object_revision' => 1, 'query_limit' => 2, 'result_limit' => 2,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $session = DB::table('sifi_research_sessions')->first();
        $text = 'Kutipan fixture yang cukup panjang.';
        $brief = 'Draf analisis fixture';
        $sourceHash = hash('sha256', $text);
        $imageHash = hash('sha256', 'image fixture');
        $imagePath = "sifi/e2e02/{$owner->id}/{$session->id}/fixture.png";
        Storage::disk('local')->put($imagePath, 'image fixture');

        DB::table('sifi_research_sessions')->where('id', $session->id)->update([
            'results_json' => json_encode([
                'e2e01_pilot' => [
                    'state' => 'RETRIEVED_EXTRACTED', 'source_url' => 'https://example.invalid/source',
                    'body_sha256' => $sourceHash, 'extraction' => ['text' => $text],
                ],
                'e2e02_image' => ['sha256' => $imageHash, 'private_path' => $imagePath],
                'e2e02_review_packet' => [
                    'state' => 'CANDIDATE_REVIEW_REQUIRED', 'source_sha256' => $sourceHash,
                    'image_sha256' => $imageHash, 'claim_hypothesis' => 'Hipotesis fixture untuk review lokal.',
                    'quote' => ['text' => $text, 'character_offset' => 0, 'character_length' => mb_strlen($text)],
                ],
                'e2e03_intelligence' => [
                    'state' => 'ANALYST_DRAFT_NOT_EVIDENCE', 'source_sha256' => $sourceHash,
                    'image_sha256' => $imageHash, 'brief' => $brief, 'brief_sha256' => hash('sha256', $brief),
                    'issue_label' => 'Kandidat isu fixture',
                ],
            ], JSON_THROW_ON_ERROR),
        ]);

        $input = ['session_id' => $session->id, 'object_revision' => 1, 'consent' => true];
        $handoff = $this->post($pilot.'/real-signal-handoff', $input)->assertSessionHasNoErrors()->assertRedirect();
        $this->post($pilot.'/real-signal-handoff', $input)->assertSessionHasNoErrors()
            ->assertRedirect($handoff->headers->get('Location'));
        foreach (['sifi_real_source_signals', 'sifi_investigations', 'sifi_issue_candidates', 'sifi_evidence_drafts'] as $table) {
            $this->assertDatabaseCount($table, 1);
        }
        $this->assertDatabaseHas('sifi_evidence_drafts', ['workflow_state' => 'DRAFT']);
        $this->assertDatabaseHas('sifi_real_source_signals', ['review_state' => 'NOT_EVALUATED']);
        $this->get($pilot)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('sifi/e2e01-pilot')
            ->where('sessions.0.shadow_timeline.stages.issue_handoff', 'LOCAL_DRAFT_LINKED')
            ->where('sessions.0.shadow_timeline.stages.admitted_evidence', 'NOT_ESTABLISHED')
            ->where('sessions.0.shadow_timeline.stages.intelligence_brief', 'DRAFT_RECORDED')
            ->where('sessions.0.shadow_timeline.source_sha256', $sourceHash)
            ->where('sessions.0.shadow_timeline.review_state', 'DRAFT')
            ->where('sessions.0.shadow_timeline.brief_url', null)
            ->where('sessions.0.shadow_timeline.candidate_url', route('sifi.issue-candidates.show', DB::table('sifi_issue_candidates')->value('id'), false))
            ->where('sessions.0.shadow_timeline.investigation_url', route('sifi.investigations.show', DB::table('sifi_investigations')->value('id'), false))
            ->has('sessions.0.shadow_timeline.warnings', 0));
        $candidate = DB::table('sifi_issue_candidates')->first();
        $caseUrl = route('sifi.issue-case.show', $candidate->id, false);
        $this->get($pilot)->assertInertia(fn (Assert $page) => $page
            ->where('sessions.0.shadow_timeline.issue_case_state', 'MISSING')
            ->where('sessions.0.shadow_timeline.issue_case_url', $caseUrl)
            ->where('sessions.0.shadow_timeline.brief_readiness', 'ISSUE_CASE_REQUIRED'));
        $this->get($caseUrl)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('sifi/issue-case-workspace')->where('case', null));
        $this->post($caseUrl, [
            'candidate_revision' => $candidate->revision, 'title' => 'Kasus QA',
            'issue_domain' => 'lingkungan', 'geography_label' => 'Wilayah QA',
            'scope_note' => 'Batas cakupan QA', 'lifecycle_state' => 'DRAFT',
        ])->assertSessionHasNoErrors()->assertRedirect($caseUrl);
        $this->get($pilot)->assertInertia(fn (Assert $page) => $page
            ->where('sessions.0.shadow_timeline.issue_case_state', 'DRAFT_AVAILABLE')
            ->where('sessions.0.shadow_timeline.brief_readiness', 'TRAJECTORY_REQUIRED')
            ->where('sessions.0.shadow_timeline.next_context_url', route('sifi.trajectory.show', $candidate->id, false))
            ->where('sessions.0.shadow_timeline.brief_url', null));
        $this->assertSame(1, DB::table('sifi_research_session_audit')->where('action', 'E2E04_REAL_SIGNAL_HANDOFF')->count());

        $foreign = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($foreign)->get($caseUrl)->assertForbidden();
        $this->actingAs($owner);
        DB::table('sifi_issue_case_drafts')->update(['owner_user_id' => $foreign->id]);
        $this->get($caseUrl)->assertForbidden();
        $this->post($caseUrl, [
            'candidate_revision' => $candidate->revision, 'title' => 'Kasus QA',
            'issue_domain' => 'lingkungan', 'geography_label' => 'Wilayah QA',
            'scope_note' => 'Batas cakupan QA', 'lifecycle_state' => 'DRAFT',
        ])->assertForbidden();
        $this->get($pilot)->assertInertia(fn (Assert $page) => $page
            ->where('sessions.0.shadow_timeline.issue_case_url', null)
            ->where('sessions.0.shadow_timeline.brief_readiness', 'CONTEXT_UNAVAILABLE')
            ->where('sessions.0.shadow_timeline.next_context_url', null)
            ->where('sessions.0.shadow_timeline.brief_url', null));
        DB::table('sifi_issue_case_drafts')->update(['owner_user_id' => $owner->id]);
        DB::table('sifi_real_source_signals')->update(['owner_user_id' => $foreign->id]);
        $this->get($pilot)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('sessions.0.shadow_timeline.stages.issue_handoff', 'NOT_CONFIRMED')
            ->where('sessions.0.shadow_timeline.review_url', null));
        DB::table('sifi_real_source_signals')->update(['owner_user_id' => $owner->id]);
        $evidence = DB::table('sifi_evidence_drafts')->first();
        DB::table('sifi_evidence_drafts')->where('id', $evidence->id)->update(['owner_user_id' => $foreign->id]);
        $this->get($pilot)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('sessions.0.shadow_timeline.stages.issue_handoff', 'NOT_CONFIRMED')
            ->where('sessions.0.shadow_timeline.review_url', null));
        DB::table('sifi_evidence_drafts')->where('id', $evidence->id)->update(['owner_user_id' => $owner->id]);
        DB::table('sifi_real_source_signals')->update(['source_sha256' => str_repeat('a', 64)]);
        $this->get($pilot)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('sessions.0.shadow_timeline.stages.issue_handoff', 'NOT_CONFIRMED')
            ->where('sessions.0.shadow_timeline.review_url', null)
            ->where('sessions.0.shadow_timeline.investigation_url', null)
            ->where('sessions.0.shadow_timeline.candidate_url', null)
            ->where('sessions.0.shadow_timeline.brief_url', null)
            ->where('sessions.0.shadow_timeline.warnings.0', 'HANDOFF_LINEAGE_NOT_CONFIRMED'));
        config(['sifi_core_v2.shadow_workbench' => false]);
        $this->get($pilot)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('sessions.0.shadow_timeline', null));
        $this->actingAs(User::factory()->create(['email_verified_at' => now()]))->get($pilot)->assertForbidden();
        $this->actingAs($owner);
        DB::table('sifi_research_sessions')->where('id', $session->id)->update(['expires_at' => now()->subMinute()]);
        $this->get($pilot)->assertOk()->assertInertia(fn (Assert $page) => $page->has('sessions', 0));
        DB::table('sifi_research_sessions')->where('id', $session->id)->update(['expires_at' => now()->addDay(), 'object_revision' => 2]);
        $this->get($pilot)->assertOk()->assertInertia(fn (Assert $page) => $page->has('sessions', 0));
        $this->assertDatabaseCount('jobs', 0);
        Http::assertNothingSent();
    }
}