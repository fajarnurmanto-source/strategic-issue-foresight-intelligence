<?php

namespace Tests\Feature\Sifi;

use App\Models\SifiMonitoringObject;
use App\Models\User;
use App\Services\Sifi\DiscoveryCatalog;
use App\Services\Sifi\MultiSourceResearchWorkspace;
use App\Services\Sifi\ResearchRetrievalWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class MultiSourceResearchTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private SifiMonitoringObject $object;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::preventStrayRequests();
        $this->owner = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($this->owner);
        $this->object = $this->object($this->owner);
    }

    private function object(User $u): SifiMonitoringObject
    {
        return SifiMonitoringObject::create(['id' => (string) Str::uuid(), 'user_id' => $u->id, 'name' => '[SYNTHETIC] Industri kendaraan listrik', 'description' => 'Konfigurasi pengujian synthetic saja.', 'category' => 'Industri', 'objective' => 'Membandingkan informasi synthetic kendaraan listrik.', 'horizon' => '90 hari', 'geography' => null, 'aliases_json' => ['EV'], 'keywords_json' => ['kendaraan listrik'], 'exclusions_json' => [], 'state' => 'CONFIGURED', 'revision' => 1]);
    }

    private function url(?SifiMonitoringObject $o = null): string
    {
        return '/sifi/monitoring-objects/'.($o ?? $this->object)->id.'/research-multi';
    }

    private function data(array $extra = []): array
    {
        return array_replace(['question' => 'Bagaimana perkembangan kendaraan listrik?', 'object_revision' => 1, 'scenario' => 'normal', 'query_limit' => 2, 'result_limit' => 10, 'submission_key' => (string) Str::uuid()], $extra);
    }

    private function create(array $extra = []): object
    {
        $v = $this->data($extra);
        $this->post($this->url(), $v)->assertSessionHasNoErrors()->assertRedirect();

        return DB::table('sifi_research_sessions')->where('user_id', $this->owner->id)->orderByDesc('rowid')->first();
    }

    private function multi(object $row): array
    {
        return json_decode($row->results_json, true, 32, JSON_THROW_ON_ERROR)['multi_source'];
    }

    private function saveData(object $row, array $extra = []): array
    {
        $m = $this->multi($row);

        return array_replace(['revision' => $row->revision, 'object_revision' => 1, 'envelope_sha256' => $m['envelope_sha256'], 'selected_ids' => array_slice(array_column($m['envelope']['entries'], 'id'), 0, 2), 'analyst_note' => 'Catatan synthetic; bukan kesimpulan faktual.'], $extra);
    }

    private function overrideCatalog(array $entries): void
    {
        $mock = \Mockery::mock(new DiscoveryCatalog);
        $mock->shouldReceive('entries')->andReturn($entries);
        app()->instance(DiscoveryCatalog::class, $mock);
    }

    public function test_ih03_shadow_receipts_are_indexed_only_with_isolated_opt_in(): void
    {
        config(['sifi_ih03.shadow_ingest_enabled' => false]);
        $this->create();
        $this->assertDatabaseCount('sifi_connector_observations', 0);
        config(['sifi_ih03.shadow_ingest_enabled' => true]);
        $row = $this->create();
        $envelope = $this->multi($row)['envelope'];
        $this->assertDatabaseCount('sifi_connector_observations', count($envelope['entries']));
        $indexed = DB::table('sifi_connector_observations')->where('session_id', $row->id)->get();
        $this->assertTrue($indexed->every(fn ($item) => $item->source_mode === 'SYNTHETIC_METADATA_ONLY' && $item->evidence_admission === 'NONE'));
        $this->assertDatabaseCount('sifi_real_source_signals', 0);
        $this->assertDatabaseCount('sifi_issue_candidates', 0);
        $this->assertDatabaseCount('sifi_evidence_drafts', 0);
        Http::assertNothingSent();
    }

    public function test_two_providers_normalize_dedup_and_preserve_lineage(): void
    {
        $row = $this->create();
        $e = $this->multi($row)['envelope'];
        $this->assertSame(['SYNTHETIC_CATALOG', 'SYNTHETIC_BULLETIN'], $e['provider_ids']);
        $this->assertCount(4, $e['calls']);
        $this->assertGreaterThan(1, $e['unique_result_count']);
        $this->assertGreaterThan(0, $e['collapsed_hits']);
        $this->assertSame('sifi.research.insights.v1', $e['insights']['schema_version']);
        $this->assertSame($e['unique_result_count'], $e['insights']['candidate_item_count']);
        $this->assertNull($e['insights']['independent_origin_count']);
        $this->assertNull($e['insights']['claim_confidence']);
        $this->assertSame('NONE', $e['insights']['evidence_admission']);
        $this->assertLessThanOrEqual(10, count($e['entries']));
        $this->assertLessThanOrEqual(65536, $e['usage']['fixture_metadata_bytes']);
        $this->assertSame(0, $e['usage']['network_requests']);
        $this->assertSame(0, $e['usage']['documents_retrieved']);
        $this->assertSame(0, $e['usage']['cost']);
        $both = array_filter($e['entries'], fn ($x) => count(array_unique(array_column($x['lineage'], 'provider'))) === 2);
        $this->assertNotEmpty($both);
        foreach ($e['entries'] as $x) {
            $this->assertStringNotContainsString('utm_', $x['canonical_url']);
            $this->assertFalse($x['retrieval_allowed']);
            $this->assertSame('NONE', $x['evidence_admission']);
            $this->assertNull($x['retrieved_at']);
            $this->assertNull($x['published_at']);
            $this->assertNotContains('synthetic', $x['relevance']['context_hits']);
        }
        Http::assertNothingSent();
        $this->assertDatabaseCount('sifi_research_retrievals', 0);
        $this->assertDatabaseCount('sifi_issue_candidates', 0);
        $this->assertDatabaseCount('sifi_evidence_drafts', 0);
    }

    public function test_comparison_exposes_shared_and_different_words_without_verification(): void
    {
        $e = $this->multi($this->create())['envelope'];
        $this->assertSame('NOT_VERIFIED', $e['independence']);
        $this->assertSame('NOT_PERFORMED_METADATA_ONLY', $e['claim_verification']);
        $this->assertNotEmpty(array_filter($e['comparisons'], fn ($p) => $p['possible_republication']));
        $this->assertNotEmpty(array_filter($e['comparisons'], fn ($p) => $p['left_only_terms'] || $p['right_only_terms']));
        foreach ($e['comparisons'] as $p) {
            $this->assertSame('NOT_VERIFIED', $p['independence']);
        }
    }

    public function test_save_reopen_uses_existing_session_history_and_is_idempotent(): void
    {
        $row = $this->create();
        $data = $this->saveData($row);
        $this->post($this->url().'/'.$row->id.'/save', $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->post($this->url().'/'.$row->id.'/save', $data)->assertSessionHasNoErrors();
        $fresh = DB::table('sifi_research_sessions')->where('id', $row->id)->first();
        $this->assertSame(2, $fresh->revision);
        $this->assertSame('SAVED', $this->multi($fresh)['state']);
        $this->assertSame($data['analyst_note'], $this->multi($fresh)['analyst_note']);
        $this->assertSame(1, DB::table('sifi_research_session_audit')->where('action', 'MULTI_RESEARCH_SAVED')->count());
        $this->get($this->url())->assertOk()->assertInertia(fn (Assert $p) => $p->component('sifi/multi-source-research')->where('history.0.multi.state', 'SAVED')->where('history.0.multi.analyst_note', $data['analyst_note']));
        $this->assertDatabaseCount('sifi_research_sessions', 1);
        $this->assertDatabaseCount('sifi_issue_candidates', 0);
    }

    public function test_empty_analyst_note_is_allowed_and_saved_history_is_immutable(): void
    {
        $row = $this->create();
        $data = $this->saveData($row, ['analyst_note' => '']);
        $this->post($this->url().'/'.$row->id.'/save', $data)->assertSessionHasNoErrors();
        $data['analyst_note'] = 'Attempted replacement';
        $this->post($this->url().'/'.$row->id.'/save', $data)->assertSessionHasErrors('research');
        $this->assertSame('', $this->multi(DB::table('sifi_research_sessions')->first())['analyst_note']);
    }

    public function test_replayed_search_does_not_consume_another_session(): void
    {
        $v = $this->data();
        $this->post($this->url(), $v)->assertSessionHasNoErrors();
        $this->post($this->url(), $v)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('sifi_research_sessions', 1);
        $v['question'] = 'Pertanyaan berbeda tentang kendaraan listrik?';
        $this->post($this->url(), $v)->assertSessionHasErrors('research');
        $this->assertDatabaseCount('sifi_research_sessions', 1);
    }

    public function test_no_results_quota_timeout_and_partial_coverage_are_honest(): void
    {
        foreach (['no_result', 'quota', 'timeout', 'partial'] as $scenario) {
            $row = $this->create(['scenario' => $scenario]);
            $e = $this->multi($row)['envelope'];
            $this->assertSame(0, $e['usage']['network_requests']);
            $this->assertCount(4, $e['calls']);
            if ($scenario !== 'partial') {
                $this->assertSame([], $e['entries']);
            }if ($scenario === 'quota') {
                $this->assertSame(['QUOTA'], array_values(array_unique(array_column($e['calls'], 'state'))));
            }if ($scenario === 'timeout') {
                $this->assertSame(['TIMEOUT'], array_values(array_unique(array_column($e['calls'], 'state'))));
            }if ($scenario === 'partial') {
                $this->assertNotEmpty($e['entries']);
                $this->assertContains('UNAVAILABLE', array_column($e['calls'], 'state'));
            }
        }
    }

    public function test_budgets_limit_calls_results_and_omissions_are_not_dedup(): void
    {
        $e = $this->multi($this->create(['query_limit' => 1, 'result_limit' => 1]))['envelope'];
        $this->assertCount(2, $e['calls']);
        $this->assertCount(1, $e['entries']);
        $this->assertGreaterThan(0, $e['omitted_unique_count']);
        $this->assertSame($e['raw_result_count'] - ($e['unique_result_count'] + $e['omitted_unique_count']), $e['collapsed_hits']);
        $this->assertSame('RESULT_LIMIT', $e['stopping_reason']);
    }

    public function test_provider_and_rights_or_url_override_are_rejected(): void
    {
        foreach (['url' => 'http://127.0.0.1', 'provider' => 'LIVE', 'rights' => 'APPROVED', 'grant' => 'yes', 'user_id' => 7, 'ai' => true, 'request_limit' => 999] as $key => $value) {
            $this->post($this->url(), $this->data([$key => $value]))->assertSessionHasErrors('research');
        }$this->assertDatabaseCount('sifi_research_sessions', 0);
    }

    public function test_owner_isolation_for_pages_search_and_save(): void
    {
        $row = $this->create();
        $other = User::factory()->create(['email_verified_at' => now()]);
        $own = $this->object($other);
        $this->actingAs($other);
        $this->get($this->url())->assertForbidden();
        $this->post($this->url(), $this->data())->assertForbidden();
        $this->post($this->url($own).'/'.$row->id.'/save', $this->saveData($row))->assertNotFound();
        $this->get('/sifi/research')->assertOk()->assertInertia(fn (Assert $p) => $p->has('objectChoices', 1)->where('objectChoices.0.id', $own->id));
    }

    public function test_foreign_session_on_same_owner_other_object_is_rejected(): void
    {
        $row = $this->create();
        $other = $this->object($this->owner);
        $this->post($this->url($other).'/'.$row->id.'/save', $this->saveData($row))->assertNotFound();
    }

    public function test_object_revision_pause_and_session_expiry_block_save(): void
    {
        $row = $this->create();
        $this->object->update(['revision' => 2]);
        $this->post($this->url().'/'.$row->id.'/save', $this->saveData($row))->assertSessionHasErrors('research');
        $this->object->update(['revision' => 1, 'state' => 'PAUSED']);
        $this->post($this->url(), $this->data())->assertSessionHasErrors('research');
        $this->object->update(['state' => 'CONFIGURED']);
        DB::table('sifi_research_sessions')->where('id', $row->id)->update(['expires_at' => now()->subDay()]);
        $this->post($this->url().'/'.$row->id.'/save', $this->saveData($row))->assertSessionHasErrors('research');
        $this->get($this->url())->assertInertia(fn (Assert $p) => $p->where('history.0.question', null)->where('history.0.multi', null));
    }

    public function test_tampered_envelope_and_foreign_selection_are_refused(): void
    {
        $row = $this->create();
        $this->post($this->url().'/'.$row->id.'/save', $this->saveData($row, ['selected_ids' => [str_repeat('f', 64)]]))->assertSessionHasErrors('research');
        $results = json_decode($row->results_json, true);
        $results['multi_source']['envelope']['entries'][0]['title'] = 'tampered';
        DB::table('sifi_research_sessions')->where('id', $row->id)->update(['results_json' => json_encode($results)]);
        $this->post($this->url().'/'.$row->id.'/save', $this->saveData($row))->assertSessionHasErrors('research');
    }

    public function test_safe_url_normalization_and_ssrf_boundaries(): void
    {
        $w = app(MultiSourceResearchWorkspace::class);
        $this->assertSame('https://a.sources.invalid/p?a=2&z=1', $w->canonical('https://A.sources.invalid/p?z=1&utm_source=x&a=2'));
        foreach (['http://a.sources.invalid/a', 'https://127.0.0.1/a', 'https://[::1]/a', 'https://user:pass@a.sources.invalid/a', 'https://a.sources.invalid:444/a', 'https://a.sources.invalid/a#x', 'https://a.sources.invalid.evil.com/a', 'https://localhost/a', 'https://169.254.169.254/a', 'https://a.sources.invalid/%2e%2e'] as $url) {
            try {
                $w->canonical($url);
                $this->fail('Unsafe URL accepted');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_same_canonical_url_with_conflicting_origins_is_ambiguous(): void
    {
        $entries = app(DiscoveryCatalog::class)->entries();
        $a = collect($entries)->firstWhere('key', 'ev-api');
        $a['title'] = '[SYNTHETIC] Kendaraan listrik';
        $a['topics'] = ['kendaraan listrik'];
        $b = $a;
        $b['key'] = 'conflicting-origin';
        $b['origin'] = 'OTHER_DECLARED_ORIGIN';
        $this->overrideCatalog([$a, $b]);
        $e = $this->multi($this->create())['envelope'];
        $ambiguous = array_filter($e['entries'], fn ($x) => $x['origin_state'] === 'AMBIGUOUS');
        $this->assertNotEmpty($ambiguous);
        $this->assertSame('NOT_VERIFIED', $e['independence']);
    }

    public function test_metadata_byte_budget_stops_before_exceeding_limit(): void
    {
        $entries = app(DiscoveryCatalog::class)->entries();
        foreach ($entries as &$entry) {
            $entry['snippet'] = str_repeat('kendaraan listrik synthetic ', 48);
        }unset($entry);
        $this->overrideCatalog($entries);
        $e = $this->multi($this->create())['envelope'];
        $this->assertSame('METADATA_BYTE_LIMIT', $e['stopping_reason']);
        $this->assertLessThanOrEqual(65536, $e['usage']['fixture_metadata_bytes']);
        $this->assertLessThanOrEqual(4, $e['usage']['simulated_calls']);
    }

    public function test_exclusions_and_unrelated_question_do_not_turn_into_answers(): void
    {
        $this->object->update(['exclusions_json' => ['kendaraan listrik']]);
        $e = $this->multi($this->create())['envelope'];
        $this->assertSame([], $e['entries']);
        $this->object->update(['exclusions_json' => []]);
        $e = $this->multi($this->create(['question' => 'Bagaimana fenomena astronomi quasar?']))['envelope'];
        $this->assertSame([], $e['entries']);
    }

    public function test_existing_retrieval_contract_survives_multi_source_session(): void
    {
        $row = $this->create();
        $w = app(ResearchRetrievalWorkspace::class);
        $envelope = $w->search($this->owner, $this->object, $row->id);
        $this->assertSame('LOCAL_SYNTHETIC_HTTP_FAKE', $envelope['provider']);
        $this->assertNotEmpty($envelope['results']);
        $this->assertDatabaseCount('sifi_research_retrievals', 0);
    }

    public function test_geography_and_alias_catalog_metadata_participate_in_matching(): void
    {
        $this->object->update(['name' => '[SYNTHETIC] Isu Yogyakarta', 'geography' => 'Yogyakarta', 'aliases_json' => ['Kota Pelajar']]);
        $e = $this->multi($this->create(['question' => 'Bagaimana perkembangan Yogyakarta?']))['envelope'];
        $this->assertGreaterThanOrEqual(4, $e['unique_result_count']);
        $this->assertSame(['SYNTHETIC_CATALOG', 'SYNTHETIC_BULLETIN'], array_values(array_unique(array_column($e['calls'], 'provider'))));
        $e = $this->multi($this->create(['question' => 'Bagaimana ruang terbuka Kota Pelajar?']))['envelope'];
        $this->assertNotEmpty($e['entries']);
    }
}
