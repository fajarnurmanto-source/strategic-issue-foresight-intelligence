<?php

namespace Tests\Feature\Sifi;

use App\Models\SifiMonitoringObject;
use App\Models\User;
use App\Services\Sifi\ApprovedCorpusSearch;
use App\Services\Sifi\BoundedSearchTransport;
use App\Services\Sifi\CorpusSearchProvider;
use App\Services\Sifi\OneShotSearchTransport;
use App\Services\Sifi\ResearchSessionWorkspace;
use App\Services\Sifi\SearchProviderRegistry;
use App\Services\Sifi\SearchWireFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class RealSearchAdapterTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private SifiMonitoringObject $object;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['sifi.real_search_fixture' => true]);
        Http::preventStrayRequests();
        $this->owner = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($this->owner);
        $this->object = $this->makeObject($this->owner);
    }

    private function makeObject(User $u): SifiMonitoringObject
    {
        return SifiMonitoringObject::create(['id' => (string) Str::uuid(), 'user_id' => $u->id, 'name' => '[SYNTHETIC] Kendaraan listrik', 'description' => 'Synthetic API fixture.', 'category' => 'Industri', 'objective' => 'Membandingkan informasi synthetic kendaraan listrik.', 'horizon' => '90 hari', 'geography' => null, 'aliases_json' => ['EV'], 'keywords_json' => [], 'exclusions_json' => [], 'state' => 'CONFIGURED', 'revision' => 1]);
    }

    private function url(): string
    {
        return '/sifi/monitoring-objects/'.$this->object->id.'/research-multi';
    }

    private function data(array $v = []): array
    {
        return array_replace(['question' => 'Bagaimana perkembangan kendaraan listrik?', 'object_revision' => 1, 'scenario' => 'api_fixture', 'query_limit' => 2, 'result_limit' => 10, 'submission_key' => (string) Str::uuid()], $v);
    }

    private function row(): object
    {
        return DB::table('sifi_research_sessions')->where('user_id', $this->owner->id)->orderByDesc('rowid')->first();
    }

    private function envelope(): array
    {
        return json_decode($this->row()->results_json, true)['multi_source']['envelope'];
    }

    private function context(): array
    {
        return ['mode' => 'HTTP_FAKE', 'query' => 'kendaraan listrik', 'query_sha256' => hash('sha256', 'kendaraan listrik'), 'limit' => 5, 'binding' => ['owner_id' => $this->owner->id, 'object_id' => $this->object->id, 'session_id' => (string) Str::uuid(), 'object_revision' => 1, 'plan_sha256' => str_repeat('a', 64)]];
    }

    public function test_two_wire_apis_merge_save_and_reopen_existing_history(): void
    {
        $this->post($this->url(), $this->data())->assertSessionHasNoErrors()->assertRedirect();
        $row = $this->row();
        $m = json_decode($row->results_json, true)['multi_source'];
        $e = $m['envelope'];
        $this->assertSame(SearchProviderRegistry::IDS, $e['provider_ids']);
        $this->assertCount(4, $e['calls']);
        $this->assertCount(4, $e['entries']);
        $this->assertSame(8, $e['raw_result_count']);
        $this->assertSame(4, $e['collapsed_hits']);
        foreach ($e['entries'] as $entry) {
            $this->assertCount(2, $entry['lineage']);
            $this->assertStringContainsString('.sources.invalid', $entry['canonical_url']);
            $this->assertFalse($entry['retrieval_allowed']);
            $this->assertSame('NONE', $entry['evidence_admission']);
            $this->assertNull($entry['retrieved_at']);
            $this->assertStringStartsWith('[SYNTHETIC', $entry['title']);
        }
        $this->post($this->url().'/'.$row->id.'/save', ['revision' => 1, 'object_revision' => 1, 'envelope_sha256' => $m['envelope_sha256'], 'selected_ids' => array_slice(array_column($e['entries'], 'id'), 0, 2), 'analyst_note' => 'SYNTHETIC perbandingan API, bukan fakta.'])->assertSessionHasNoErrors();
        $this->get($this->url())->assertOk()->assertInertia(fn (Assert $p) => $p->where('history.0.multi.state', 'SAVED')->where('history.0.multi.envelope.mode', 'SYNTHETIC_HTTP_FAKE_API_ADAPTER'));
        $this->assertDatabaseCount('sifi_research_sessions', 1);
        $this->assertDatabaseCount('sifi_research_retrievals', 0);
        $this->assertDatabaseCount('sifi_evidence_drafts', 0);
        $this->assertSame(0, $e['usage']['network_requests']);
        Http::assertSentCount(4);
    }

    public function test_exact_request_options_and_query_are_not_client_controlled(): void
    {
        SearchWireFixture::install();
        $context = $this->context();
        $packet = (new CorpusSearchProvider('CROSSREF'))->search($context);
        $this->assertSame('OK', $packet['state']);
        Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://api.crossref.org/works?') && $r['query'] === $context['query'] && $r['rows'] === 5 && ! str_contains($r->url(), 'abstract'));
        $this->assertSame($context['query_sha256'], $packet['query_sha256']);
        $this->assertSame(hash('sha256', json_encode($context['binding'], JSON_THROW_ON_ERROR)), $packet['binding_sha256']);
    }

    public function test_publication_precision_and_no_abstract_or_fake_retrieval_date(): void
    {
        SearchWireFixture::install();
        $p = (new CorpusSearchProvider('CROSSREF'))->search($this->context());
        $this->assertSame('2024', $p['results'][0]['published_at']);
        $this->assertSame('YEAR', $p['results'][0]['publication_precision']);
        $this->assertSame('2025-06-12', $p['results'][1]['published_at']);
        $this->assertSame('DAY', $p['results'][1]['publication_precision']);
        $this->assertSame('BIBLIOGRAPHIC_LABEL_NO_ABSTRACT', $p['results'][0]['snippet_kind']);
        $this->assertNull($p['retrieved_at']);
        $this->assertNotNull($p['fixture_loaded_at']);
        $p = (new CorpusSearchProvider('WIKIPEDIA_ID'))->search($this->context());
        $this->assertNull($p['results'][0]['published_at']);
        $this->assertStringNotContainsString('<span', $p['results'][0]['snippet']);
        $this->assertSame('CC_BY_SA_4_0_ATTRIBUTION_SHAREALIKE', $p['results'][0]['rights_state']);
    }

    public function test_quota_timeout_partial_and_no_result_keep_uncertainty(): void
    {
        foreach (['api_quota', 'api_timeout', 'api_partial', 'api_no_result'] as $scenario) {
            $this->post($this->url(), $this->data(['scenario' => $scenario]))->assertSessionHasNoErrors();
            $e = $this->envelope();
            $this->assertCount(4, $e['calls']);
            if ($scenario === 'api_partial') {
                $this->assertCount(2, $e['entries']);
                $this->assertSame('PARTIAL_COVERAGE', $e['stopping_reason']);
            } else {
                $this->assertSame([], $e['entries']);
                $this->assertSame($scenario === 'api_no_result' ? 'NO_RESULTS' : 'NO_RESULTS_PROVIDER_ERRORS', $e['stopping_reason']);
            }if ($scenario === 'api_quota') {
                $this->assertSame(60, $e['calls'][0]['retry_after_seconds']);
            }
        }
    }

    public function test_replay_reuses_receipt_without_another_http_request(): void
    {
        $v = $this->data();
        $this->post($this->url(), $v)->assertSessionHasNoErrors();
        Http::assertSentCount(4);
        $this->post($this->url(), $v)->assertSessionHasNoErrors();
        Http::assertSentCount(4);
        $this->assertDatabaseCount('sifi_research_sessions', 1);
        $v['question'] = 'Pertanyaan lain tentang kendaraan listrik?';
        $this->post($this->url(), $v)->assertSessionHasErrors('research');
        Http::assertSentCount(4);
    }

    public function test_live_mode_and_missing_fake_gate_fail_before_http(): void
    {
        $context = $this->context();
        $context['mode'] = 'LIVE';
        try {
            app(BoundedSearchTransport::class)->get('CROSSREF', $context);
            $this->fail('Live gate missing');
        } catch (\RuntimeException $e) {
            $this->assertSame('LIVE_SEARCH_LOCKED', $e->getMessage());
        }Http::assertNothingSent();
        config(['sifi.real_search_fixture' => false]);
        $this->post($this->url(), $this->data())->assertSessionHasErrors('scenario');
        $this->assertDatabaseCount('sifi_research_sessions', 0);
        Http::assertNothingSent();
    }

    public function test_http_redirect_oversize_and_malformed_json_are_not_followed_or_stored(): void
    {
        foreach ([[302, 'redirect', ['Location' => 'https://127.0.0.1/private']], [200, str_repeat('x', 32769), []], [200, 'not json', []], [200, '{}', []]] as [$status,$body,$headers]) {
            Http::swap(new Factory(app('events')));
            Http::preventStrayRequests();
            Http::fake(['api.crossref.org/*' => Http::response($body, $status, $headers + ['Content-Type' => 'application/json'])]);
            $p = (new CorpusSearchProvider('CROSSREF'))->search($this->context());
            $this->assertNotSame('OK', $p['state']);
            $this->assertSame([], $p['results']);
            $this->assertArrayNotHasKey('body', $p);
        }
    }

    public function test_owner_object_revision_and_overposted_provider_do_not_leak_or_dispatch(): void
    {
        $other = User::factory()->create();
        $foreign = $this->makeObject($other);
        $this->post('/sifi/monitoring-objects/'.$foreign->id.'/research-multi', $this->data())->assertForbidden();
        Http::assertNothingSent();
        foreach ([['provider' => 'CUSTOM'], ['endpoint' => 'https://127.0.0.1'], ['owner_id' => $other->id], ['object_revision' => 999]] as $v) {
            $this->post($this->url(), $this->data($v))->assertSessionHasErrors();
            Http::assertNothingSent();
        }
        $this->post($this->url(), $this->data())->assertSessionHasNoErrors();
        $id = $this->row()->id;
        $this->actingAs($other)->get($this->url().'?research='.$id)->assertForbidden();
    }

    public function test_incorrect_query_binding_and_unknown_provider_fail_before_dispatch(): void
    {
        foreach (['query', 'provider'] as $case) {
            $c = $this->context();
            if ($case === 'query') {
                $c['query'] = 'different';
            }try {
                app(BoundedSearchTransport::class)->get($case === 'provider' ? 'CUSTOM' : 'CROSSREF', $c);
                $this->fail('Bad context accepted');
            } catch (\RuntimeException) {
                $this->assertTrue(true);
            }
        }Http::assertNothingSent();
    }

    public function test_budget_exclusions_and_comparison_remain_metadata_only(): void
    {
        $this->post($this->url(), $this->data(['query_limit' => 1, 'result_limit' => 1]))->assertSessionHasNoErrors();
        $e = $this->envelope();
        $this->assertCount(2, $e['calls']);
        $this->assertCount(1, $e['entries']);
        $this->assertSame(0, $e['collapsed_hits']);
        $this->assertGreaterThan(0, $e['omitted_unique_count']);
        $this->assertSame('NOT_VERIFIED', $e['independence']);
        $this->object->update(['exclusions_json' => ['SYNTHETIC']]);
        $this->post($this->url(), $this->data())->assertSessionHasNoErrors();
        $this->assertSame([], $this->envelope()['entries']);
    }

    public function test_reference_export_is_saved_only_owner_bound_and_not_evidence(): void
    {
        $this->post($this->url(), $this->data())->assertSessionHasNoErrors();
        $row = $this->row();
        $m = json_decode($row->results_json, true)['multi_source'];
        $path = $this->url().'/'.$row->id.'/references';
        $this->get($path)->assertStatus(409);
        $this->post($this->url().'/'.$row->id.'/save', ['revision' => 1, 'object_revision' => 1, 'envelope_sha256' => $m['envelope_sha256'], 'selected_ids' => [$m['envelope']['entries'][0]['id']], 'analyst_note' => 'SYNTHETIC references only'])->assertSessionHasNoErrors();
        $this->get($path)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertJsonPath('evidence_admission', 'NONE')->assertJsonPath('format', 'sifi-references-1')->assertJsonCount(1, 'references');
        $other = User::factory()->create();
        $this->actingAs($other)->get($path)->assertForbidden();
        $this->actingAs($this->owner);
        DB::table('sifi_research_sessions')->where('id', $row->id)->update(['expires_at' => now()->subSecond()]);
        $this->get($path)->assertSessionHasErrors();
    }

    public function test_server_approved_execution_and_recovery_use_the_existing_session(): void
    {
        $id = app(ResearchSessionWorkspace::class)->create($this->owner, $this->object, $this->data());
        config(['sifi.real_search_live' => true]);
        $wire = new class extends OneShotSearchTransport
        {
            public int $calls = 0;

            public int $recoveries = 0;

            private function packet(string $p, array $c): array
            {
                return ['provider' => $p, 'query' => $c['query'], 'query_sha256' => $c['query_sha256'], 'binding_sha256' => hash('sha256', json_encode($c['binding'], JSON_THROW_ON_ERROR)), 'state' => 'OK', 'mode' => 'LIVE', 'body' => ['message' => ['items' => [['DOI' => '10.99999/synthetic-one', 'title' => ['SYNTHETIC mock native contract only'], 'publisher' => 'SYNTHETIC publisher', 'type' => 'report']]]], 'results' => [], 'retrieved_at' => '2026-10-29T00:00:00Z', 'fixture_loaded_at' => null, 'response_sha256' => str_repeat('e', 64), 'response_bytes' => 400, 'retry_after_seconds' => null, 'network_requests' => 1, 'cost' => 0];
            }

            public function get(string $p, array $c): array
            {
                $this->calls++;

                return $this->packet($p, $c);
            }

            public function recover(string $p, array $c): array
            {
                $this->recoveries++;

                return $this->packet($p, $c);
            }
        };
        app()->instance(OneShotSearchTransport::class, $wire);
        $service = app(ApprovedCorpusSearch::class);
        $service->run($this->owner, $this->object, $id, 'CROSSREF', 0, true);
        $e = $this->envelope();
        $this->assertSame(0, $wire->calls);
        $this->assertSame(1, $wire->recoveries);
        $this->assertSame('APPROVED_LIVE_CORPUS_METADATA', $e['mode']);
        $this->assertCount(1, $e['entries']);
        $this->assertSame('https://doi.org/10.99999/synthetic-one', $e['entries'][0]['canonical_url']);
        $this->assertSame('2026-10-29T00:00:00Z', $e['entries'][0]['retrieved_at']);
        $this->assertNull($e['entries'][0]['fixture_loaded_at']);
        $this->assertCount(1, $e['calls']);
        $this->assertDatabaseCount('sifi_research_sessions', 1);
        $this->assertDatabaseCount('sifi_research_retrievals', 0);
        Http::assertNothingSent();
    }

    public function test_web_2025_scenario_is_available_and_runs_through_existing_ui_contract(): void
    {
        $this->get($this->url())->assertInertia(fn (Assert $p) => $p->where('scenarios', fn ($s) => in_array('api_web_2025', $s->all(), true)));
        $this->object->update(['name' => '[SYNTHETIC] Kota Yogyakarta', 'geography' => 'Yogyakarta']);
        $this->post($this->url(), $this->data(['question' => 'Citra Negatif Apa Saja yang Menyerang Kota Yogyakarta sepanjang Tahun 2025', 'scenario' => 'api_web_2025']))->assertSessionHasNoErrors();
        $e = $this->envelope();
        $this->assertSame(['TAVILY_KEYLESS', 'WIKIPEDIA_ID'], $e['provider_ids']);
        $this->assertCount(4, $e['calls']);
        $this->assertCount(4, $e['entries']);
        $this->assertSame(0, $e['usage']['network_requests']);
        $this->assertSame('SYNTHETIC_HTTP_FAKE_API_ADAPTER', $e['mode']);
    }
}
