<?php

namespace Tests\Feature\Sifi;

use App\Models\SifiIssueCandidate;
use App\Models\User;
use App\Services\Sifi\ExecutiveBriefSource;
use App\Services\Sifi\SourceObservationPipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class SourceObservationPipelineTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private SourceObservationPipeline $pipeline;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::preventStrayRequests();
        $this->owner = User::factory()->create(['email_verified_at' => now()]);
        $this->pipeline = app(SourceObservationPipeline::class);
    }

    private function fields(string $scenario = 'change'): array
    {
        return ['scenario' => $scenario, 'analyst_note' => 'Telaah otomatis menggunakan fixture sintetis, bukan validasi manusia atau temuan aktual.', 'confirm_synthetic' => true];
    }

    private function create(string $scenario = 'change'): SifiIssueCandidate
    {
        $response = $this->actingAs($this->owner)->post('/sifi/source-catalog/pipeline-draft', $this->fields($scenario));
        $candidate = SifiIssueCandidate::where('user_id', $this->owner->id)->firstOrFail();
        $response->assertRedirect('/sifi/issue-candidates/'.$candidate->id.'/executive-brief');

        return $candidate;
    }

    private function briefFields(SifiIssueCandidate $c): array
    {
        $service = app(ExecutiveBriefSource::class);
        [$case,$trajectory,$foresight] = $service->parents($c, $this->owner->id);
        $source = $service->assemble($c, $case, $trajectory, $foresight);

        return ['candidate_revision' => 1, 'case_revision' => 1, 'trajectory_revision' => 1, 'foresight_revision' => 1,
            'input_digest' => $service->digest($source), 'title' => '[SIMULASI] Ringkasan pengujian pipeline', 'audience' => 'Pengujian otomatis',
            'executive_summary' => '[SIMULASI] Dua versi prakiraan sintetis berbeda, bukan cuaca atau dampak yang teramati. Semua klaim NOT VERIFIED.',
            'decision_questions' => 'Observasi primer apa yang dapat membantah hipotesis ini?',
            'verification_priorities' => 'Periksa hak sumber, issue time, valid time, konflik dan observasi primer.',
            'limitations' => 'SYNTHETIC FIXTURE ONLY. Tidak ada observasi nyata, admission, forecast aktual atau approval keputusan.',
            'review_state' => 'DRAFT'];
    }

    public function test_valid_format_is_normalized_with_exact_traceability_and_no_network(): void
    {
        $p = $this->pipeline->preview('valid');
        $this->assertSame('READY_FOR_ANALYST_DRAFT', $p['status']);
        $this->assertCount(2, $p['observations']);
        foreach ($p['observations'] as $o) {
            $this->assertSame($p['packet_hash'], $o['provenance']['packet_hash']);
            $this->assertTrue($o['provenance']['synthetic']);
            $this->assertFalse($o['provenance']['independence_verified']);
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $o['id']);
            $this->assertSame('OBSERVED_FIXTURE_FORECAST_NOT_EVENT', $o['classification']);
        }
        Http::assertNothingSent();
    }

    public function test_duplicate_is_not_extra_evidence_or_independent_corroboration(): void
    {
        $p = $this->pipeline->preview('duplicate');
        $this->assertCount(2, $p['observations']);
        $this->assertCount(1, $p['duplicates']);
        $c = $this->create('duplicate');
        $this->assertDatabaseCount('sifi_evidence_drafts', 2);
        $this->assertDatabaseCount('sifi_claim_drafts', 2);
        $this->assertCount(1, $this->pipeline->lineage($c)['duplicates']);
    }

    public function test_same_issue_and_slot_conflict_is_preserved_without_winner(): void
    {
        $p = $this->pipeline->preview('conflict');
        $this->assertCount(3, $p['observations']);
        $this->assertCount(1, $p['conflicts']);
        $this->assertCount(0, $p['changes']);
        $this->assertFalse($p['claim_verified']);
        $this->assertSame('NOT_EVALUATED', $p['evidence_admission']);
    }

    public function test_distinct_issued_forecast_versions_are_not_reported_as_observed_trend(): void
    {
        $p = $this->pipeline->preview('change');
        $this->assertCount(1, $p['changes']);
        $this->assertCount(0, $p['conflicts']);
        $this->assertSame('DISTINCT_FORECAST_ISSUES_NOT_OBSERVED_EVENT', $p['changes'][0]['reason']);
    }

    public function test_incomplete_metadata_unavailable_and_restricted_block_without_writes(): void
    {
        foreach (['missing_metadata' => 'SOURCE_METADATA_INCOMPLETE', 'unavailable' => 'SOURCE_UNAVAILABLE', 'restricted' => 'USE_NOT_PERMITTED'] as $s => $reason) {
            $this->assertSame($reason, $this->pipeline->preview($s)['reason']);
            $this->actingAs($this->owner)->post('/sifi/source-catalog/pipeline-draft', $this->fields($s))->assertSessionHasErrors('scenario');
        }
        $this->assertDatabaseCount('sifi_issue_candidates', 0);
        $this->assertDatabaseCount('sifi_investigations', 0);
        Http::assertNothingSent();
    }

    public function test_nonsynthetic_and_unsupported_source_are_rejected(): void
    {
        $p = $this->pipeline->fixture('valid');
        $p['synthetic'] = false;
        $this->assertSame('USE_NOT_PERMITTED', $this->pipeline->normalize($p)['reason']);
        $p = $this->pipeline->fixture('valid');
        $p['source']['documentation_url'] = 'http://127.0.0.1/admin';
        $this->assertSame('SOURCE_NOT_SUPPORTED', $this->pipeline->normalize($p)['reason']);
        Http::assertNothingSent();
    }

    public function test_malformed_dates_units_locations_and_oversized_records_fail_closed(): void
    {
        foreach (['utc_datetime' => '2026-02-30 03:00:00', 't' => 120, 'hu' => -1, 'weather_desc' => '<script>evil</script>', 'analysis_date' => '2027-01-01T00:00:00'] as $key => $value) {
            $p = $this->pipeline->fixture('valid');
            $p['data'][0]['cuaca'][0][0][$key] = $value;
            $this->assertSame('MALFORMED_RECORD', $this->pipeline->normalize($p)['reason']);
        }
        $p = $this->pipeline->fixture('valid');
        $p['data'][0]['cuaca'][0] = array_fill(0, 101, $p['data'][0]['cuaca'][0][0]);
        $this->assertSame('MALFORMED_RECORD', $this->pipeline->normalize($p)['reason']);
        $p = $this->pipeline->fixture('valid');
        $p['data'][0]['lokasi']['adm4'] = '31.71.03.1001';
        $this->assertSame('MALFORMED_RECORD', $this->pipeline->normalize($p)['reason']);
    }

    public function test_no_candidate_if_rule_does_not_match(): void
    {
        $p = $this->pipeline->fixture('valid');
        foreach ($p['data'][0]['cuaca'][0] as &$c) {
            $c['weather'] = 3;
            $c['weather_desc'] = 'Berawan (SIMULASI)';
        }unset($c);
        $this->assertSame('NO_CANDIDATE', $this->pipeline->normalize($p)['status']);
    }

    public function test_authentication_and_verification_required_before_import(): void
    {
        $this->get('/sifi/source-catalog')->assertRedirect('/login');
        $this->post('/sifi/source-catalog/pipeline-draft', $this->fields())->assertRedirect('/login');
        $unverified = User::factory()->create(['email_verified_at' => null]);
        $this->actingAs($unverified)->post('/sifi/source-catalog/pipeline-draft', $this->fields())->assertRedirect('/email/verify');
        $this->assertDatabaseCount('sifi_issue_candidates', 0);
    }

    public function test_explicit_acknowledgement_note_and_fixed_fixture_required(): void
    {
        $this->actingAs($this->owner)->post('/sifi/source-catalog/pipeline-draft', array_replace($this->fields(), ['confirm_synthetic' => false]))->assertSessionHasErrors('confirm_synthetic');
        $this->post('/sifi/source-catalog/pipeline-draft', array_replace($this->fields(), ['analyst_note' => 'short']))->assertSessionHasErrors('analyst_note');
        $this->post('/sifi/source-catalog/pipeline-draft', array_replace($this->fields(), ['scenario' => '../../.env']))->assertSessionHasErrors('scenario');
        $this->post('/sifi/source-catalog/pipeline-draft', $this->fields() + ['user_id' => 999, 'records' => [], 'source_url' => 'https://example.org', 'verified' => true, 'evidence_admitted' => true, 'collector_state' => 'ON'])
            ->assertSessionHasErrors(['user_id', 'source_url', 'verified', 'evidence_admitted', 'collector_state']);
        $this->assertDatabaseCount('sifi_issue_candidates', 0);
        Http::assertNothingSent();
    }

    public function test_repeated_submission_preserves_user_edit_and_does_not_duplicate_audits(): void
    {
        $c = $this->create();
        $c->update(['issue_hypothesis' => 'Edited by owner — preserve this', 'revision' => 2]);
        $this->create();
        $this->assertDatabaseCount('sifi_issue_candidates', 1);
        $this->assertDatabaseCount('sifi_issue_candidate_audit', 1);
        $this->assertSame('Edited by owner — preserve this', $c->fresh()->issue_hypothesis);
    }

    public function test_duplicate_only_batch_variation_reuses_candidate_and_preserves_first_provenance(): void
    {
        $first = $this->create('valid');
        $packet = $this->pipeline->lineage($first)['packet_hash'];
        $again = $this->create('duplicate');
        $this->assertSame($first->id, $again->id);
        $this->assertSame($packet, $this->pipeline->lineage($again)['packet_hash']);
        $this->assertDatabaseCount('sifi_evidence_drafts', 2);
        $this->assertDatabaseCount('sifi_issue_candidates', 1);
    }

    public function test_owner_isolation_applies_to_every_existing_page_and_download(): void
    {
        $c = $this->create();
        $other = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($other);
        foreach (['executive-brief', 'executive-brief/download', 'trajectory', 'claim-events', 'evidence', 'issue-case'] as $path) {
            $this->get('/sifi/issue-candidates/'.$c->id.'/'.$path)->assertForbidden();
        }
        $this->post('/sifi/source-catalog/pipeline-draft', $this->fields())->assertRedirect();
        $this->assertDatabaseCount('sifi_issue_candidates', 2);
        $this->assertDatabaseCount('sifi_evidence_drafts', 6);
        $ids = SifiIssueCandidate::orderBy('id')->pluck('user_id')->all();
        sort($ids);
        $expected = [$this->owner->id, $other->id];
        sort($expected);
        $this->assertSame($expected, $ids);
    }

    public function test_drafts_keep_original_states_and_do_not_fabricate_review_or_admission(): void
    {
        $c = $this->create('unverified');
        $this->assertDatabaseHas('sifi_issue_candidates', ['id' => $c->id, 'review_state' => 'DRAFT']);
        $this->assertDatabaseHas('sifi_issue_case_drafts', ['candidate_id' => $c->id, 'verification_state' => 'NOT_VERIFIED']);
        $this->assertDatabaseMissing('sifi_claim_drafts', ['verification_state' => 'VERIFIED']);
        $this->assertDatabaseCount('sifi_evidence_review_notes', 0);
        $this->assertDatabaseCount('sifi_executive_briefs', 0);
        $this->assertFalse($this->pipeline->lineage($c)['formal_review_performed']);
        $this->assertDatabaseMissing('sifi_evidence_drafts', ['workflow_state' => 'REVIEW_REQUESTED']);
        Http::assertNothingSent();
    }

    public function test_downstream_failure_rolls_back_entire_new_draft_chain(): void
    {
        DB::listen(function ($query) {
            if (str_contains($query->sql, 'insert into "sifi_foresight_drafts"')) {
                throw new \RuntimeException('Injected isolated storage failure');
            }
        });
        try {
            $this->pipeline->createDraft($this->owner, 'valid', $this->fields()['analyst_note']);
            $this->fail('Failure required');
        } catch (\RuntimeException $e) {
            $this->assertSame('Injected isolated storage failure', $e->getMessage());
        }
        foreach (['sifi_investigations', 'sifi_issue_candidates', 'sifi_evidence_drafts', 'sifi_claim_drafts', 'sifi_issue_case_drafts', 'sifi_trajectory_drafts', 'sifi_issue_candidate_audit'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_source_ui_and_existing_workbench_receive_pipeline_data(): void
    {
        $this->actingAs($this->owner)->get('/sifi/source-catalog?scenario=conflict')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
            ->component('sifi/source-catalog')->where('pipeline.mode', 'SYNTHETIC_FIXTURE_ONLY')->has('pipeline.conflicts', 1)->etc());
        $c = $this->create();
        $this->get('/sifi/workbench?candidate='.$c->id)->assertOk();
        $this->get('/sifi/issue-candidates/'.$c->id.'/executive-brief')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
            ->component('sifi/executive-brief-workspace')->where('brief', null)->has('source.source_lineage.records', 3)->etc());
        Http::assertNothingSent();
    }

    public function test_complete_source_to_reviewed_draft_to_download_preserves_report_fidelity(): void
    {
        $c = $this->create('change');
        $url = '/sifi/issue-candidates/'.$c->id.'/executive-brief';
        $this->get($url.'/download')->assertNotFound();
        $this->post($url, $this->briefFields($c))->assertRedirect($url);
        $download = $this->get($url.'/download')->assertOk();
        foreach (['SYNTHETIC FIXTURE ONLY', 'NOT VERIFIED', 'NOT_EVALUATED', 'NO DECISION APPROVAL', 'Jejak sumber ke brief', 'raw_record', 'raw_hash', 'observation', 'packet_hash', 'evidence_id', 'claim_id', 'DISTINCT_FORECAST_ISSUES_NOT_OBSERVED_EVENT', 'BMKG'] as $text) {
            $download->assertSee($text, false);
        }
        $this->assertTrue($download->headers->hasCacheControlDirective('private'));
        $this->assertTrue($download->headers->hasCacheControlDirective('no-store'));
        $lineage = $this->pipeline->lineage($c);
        foreach ($lineage['records'] as $r) {
            $download->assertSee($r['observation']['id'], false);
        }
        $dir = getenv('R17_RESULT_DIR');
        if ($dir) {
            file_put_contents($dir.'/evidence/SOURCE_TO_BRIEF_DEMO.md', $download->getContent());
            file_put_contents($dir.'/evidence/SOURCE_TO_BRIEF_TRACE.json', json_encode($lineage, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        Http::assertNothingSent();
    }

    public function test_lineage_comes_from_fixture_not_mutable_rationale_and_changes_invalidate_brief(): void
    {
        $c = $this->create();
        $url = '/sifi/issue-candidates/'.$c->id.'/executive-brief';
        $this->post($url, $this->briefFields($c))->assertRedirect();
        $original = $this->pipeline->lineage($c);
        DB::table('sifi_evidence_drafts')->where('candidate_id', $c->id)->update(['rationale' => 'Forged VERIFIED live data']);
        $this->assertEquals($original, $this->pipeline->lineage($c));
        $this->get($url.'/download')->assertStatus(409);
    }
}
