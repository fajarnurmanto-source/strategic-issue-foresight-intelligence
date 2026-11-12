<?php

namespace Tests\Feature\Sifi;

use App\Models\SifiIssueCandidate;
use App\Models\SifiMonitoringObject;
use App\Models\User;
use App\Services\Sifi\BoundedFeedParser;
use App\Services\Sifi\ExecutiveBriefSource;
use App\Services\Sifi\FeedResearchProvider;
use App\Services\Sifi\FeedSourcePack;
use App\Services\Sifi\MultiSourceResearchWorkspace;
use App\Services\Sifi\SourceObservationPipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class FeedConnectivityTest extends TestCase
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
        $this->object = $this->makeObject($this->owner);
    }

    private function makeObject(User $u, string $name = '[SYNTHETIC] Kota Yogyakarta'): SifiMonitoringObject
    {
        return SifiMonitoringObject::create(['id' => (string) Str::uuid(), 'user_id' => $u->id, 'name' => $name, 'description' => 'SYNTHETIC no real events', 'category' => 'Wilayah', 'objective' => 'Penelitian informasi publik', 'horizon' => '2025', 'geography' => 'Kota Yogyakarta', 'aliases_json' => ['Yogyakarta City'], 'keywords_json' => [], 'exclusions_json' => [], 'state' => 'CONFIGURED', 'revision' => 1]);
    }

    private function url(?SifiMonitoringObject $o = null): string
    {
        return '/sifi/monitoring-objects/'.($o ?? $this->object)->id.'/research-multi';
    }

    private function data(array $opts = [], array $extra = []): array
    {
        return array_replace(['question' => 'Informasi publik apa yang relevan untuk Kota Yogyakarta sepanjang 2025?', 'object_revision' => 1, 'scenario' => 'feed', 'query_limit' => 2, 'result_limit' => 10, 'submission_key' => (string) Str::uuid(), 'feed_options' => $opts + ['sources' => FeedSourcePack::IDS, 'pages' => 2, 'language' => 'ALL', 'region' => 'ALL', 'period' => 'ALL', 'provider_state' => 'normal']], $extra);
    }

    private function create(array $opts = [], array $extra = []): object
    {
        $this->post($this->url(), $this->data($opts, $extra))->assertSessionHasNoErrors()->assertRedirect();

        return DB::table('sifi_research_sessions')->where('user_id', $this->owner->id)->orderByDesc('rowid')->first();
    }

    private function multi(object $s): array
    {
        return json_decode($s->results_json, true, 32, JSON_THROW_ON_ERROR)['multi_source'];
    }

    private function save(object $s, ?array $ids = null): object
    {
        $m = $this->multi($s);
        $this->post($this->url().'/'.$s->id.'/save', ['revision' => (int) $s->revision, 'object_revision' => 1, 'envelope_sha256' => $m['envelope_sha256'], 'selected_ids' => $ids ?? array_column(array_slice($m['envelope']['entries'], 0, 2), 'id'), 'analyst_note' => 'SYNTHETIC analyst review, not verified.'])->assertSessionHasNoErrors();

        return DB::table('sifi_research_sessions')->find($s->id);
    }

    public function test_rss_atom_json_bounded_collection_originals_revisions_dedup_and_provenance(): void
    {
        $s = $this->create();
        $m = $this->multi($s);
        $e = $m['envelope'];
        $this->assertSame('SYNTHETIC_FEED_ACQUISITION_ONLY', $e['mode']);
        $this->assertCount(5, $e['calls']);
        $this->assertCount(6, $e['entries']);
        $this->assertSame(2, $e['collapsed_hits']);
        $this->assertSame(1, $e['filtered_counts']['object']);
        $this->assertEqualsCanonicalizing(['id', 'en', 'ja', 'es', 'fr'], $e['coverage_languages']);
        $this->assertSame(0, $e['usage']['network_requests']);
        $this->assertLessThanOrEqual(65536, $e['usage']['fixture_metadata_bytes']);
        foreach ($e['entries'] as $item) {
            $this->assertSame($item['original_title'], $item['title']);
            $this->assertNull($item['derived_translation']);
            $this->assertSame('UNASSESSED', $item['rights_state']);
            $this->assertSame('OFF', $item['collector_state']);
            $this->assertSame('NONE', $item['evidence_admission']);
            $this->assertNull($item['event_date']);
            $this->assertSame($this->owner->id, $item['binding']['owner_id']);
            $this->assertNotEmpty($item['lineage'][0]['raw_record_sha256']);
            $this->assertNotEmpty($item['lineage'][0]['body_sha256']);
            $this->assertFalse($item['retrieval_allowed']);
        }
        $rss = array_values(array_filter($e['entries'], fn ($i) => $i['canonical_url'] === 'https://publisher.sources.invalid/brief'));
        $this->assertCount(2, $rss);
        foreach ($rss as $r) {
            $this->assertSame('MULTIPLE_VERSIONS_PRESERVED_NOT_SEPARATE_EVENTS', $r['revision_state']);
        }$this->assertNotEmpty(array_filter($e['comparisons'], fn ($p) => $p['same_declared_family']));
        $this->assertSame('NOT_VERIFIED', $e['independence']);
        Http::assertNothingSent();
    }

    public function test_page_source_language_region_and_publication_filters_are_real(): void
    {
        $e = $this->multi($this->create(['sources' => ['SYNTHETIC_RSS'], 'pages' => 1]))['envelope'];
        $this->assertCount(1, $e['calls']);
        $this->assertCount(1, $e['entries']);
        $this->assertTrue($e['calls'][0]['next_available']);
        $this->assertFalse($e['calls'][0]['continuation_followed']);
        $e = $this->multi($this->create(['language' => 'ja']))['envelope'];
        $this->assertCount(1, $e['entries']);
        $this->assertSame('ja', $e['entries'][0]['source_language']);
        $this->assertNull($e['entries'][0]['published_at']);
        $this->assertNotNull($e['entries'][0]['updated_at']);
        $e = $this->multi($this->create(['region' => 'WIDER_REGION']))['envelope'];
        $this->assertCount(1, $e['entries']);
        $this->assertSame('es', $e['entries'][0]['source_language']);
        $e = $this->multi($this->create(['period' => 'PUBLISHED']))['envelope'];
        foreach ($e['entries'] as $x) {
            $this->assertSame('PUBLISHED_IN_WINDOW', $x['time_classification']);
        }$this->assertNotEmpty($e['entries']);
    }

    public function test_aggregator_observation_time_never_becomes_publication_or_event(): void
    {
        $e = $this->multi($this->create(['sources' => ['SYNTHETIC_OPEN_NEWS']]))['envelope'];
        $this->assertCount(2, $e['entries']);
        foreach ($e['entries'] as $x) {
            $this->assertNull($x['published_at']);
            $this->assertNull($x['event_date']);
            $this->assertNotNull($x['lineage'][0]['observation_time']);
            $this->assertSame('PUBLICATION_UNKNOWN', $x['time_classification']);
            $this->assertNotSame($x['origins'][0], $x['attribution']['provider']);
            $this->assertSame('', $x['snippet']);
        }
    }

    public function test_partial_quota_timeout_and_no_result_are_visible_without_fallback(): void
    {
        foreach (['partial' => 'PARTIAL_COVERAGE', 'quota' => 'NO_RESULTS_PROVIDER_ERRORS', 'timeout' => 'NO_RESULTS_PROVIDER_ERRORS', 'no_result' => 'NO_RESULTS'] as $state => $stop) {
            $e = $this->multi($this->create(['provider_state' => $state]))['envelope'];
            $this->assertSame($stop, $e['stopping_reason']);
            $this->assertLessThanOrEqual(5, count($e['calls']));
            if ($state !== 'partial') {
                $this->assertSame([], $e['entries']);
            }$this->assertSame(0, $e['usage']['network_requests']);
        }Http::assertNothingSent();
    }

    public function test_unknown_period_and_exclusions_are_not_fabricated(): void
    {
        $e = $this->multi($this->create(['period' => 'UNKNOWN']))['envelope'];
        foreach ($e['entries'] as $x) {
            $this->assertNull($x['published_at']);
        }$this->object->update(['exclusions_json' => ['SYNTHETIC']]);
        $e = $this->multi($this->create())['envelope'];
        $this->assertSame([], $e['entries']);
        $this->assertGreaterThan(0, $e['filtered_counts']['exclusions']);
    }

    public function test_nested_endpoint_rights_budget_and_bad_source_injections_are_rejected(): void
    {
        foreach ([['endpoint' => 'https://127.0.0.1'], ['sources' => ['https://evil.test']], ['sources' => []], ['pages' => 3], ['sources' => ['SYNTHETIC_RSS', 'SYNTHETIC_RSS']], ['rights_state' => 'APPROVED'], ['request_limit' => 99], ['language' => 'zz'], ['start_date' => '2025-12-31', 'end_date' => '2025-01-01']] as $o) {
            $this->post($this->url(), $this->data($o))->assertSessionHasErrors();
        }$this->assertDatabaseCount('sifi_research_sessions', 0);
        Http::assertNothingSent();
    }

    public function test_parser_rejects_dtd_entities_xinclude_depth_invalid_utf8_oversize_and_wrong_namespace(): void
    {
        $parser = new BoundedFeedParser;
        $bad = ['<!DOCTYPE rss [<!ENTITY x SYSTEM "file:///etc/passwd">]><rss version="2.0"><channel><title>&x;</title></channel></rss>', '<?xml version="1.0" encoding="UTF-16"?><rss version="2.0"/>', '<rss version="2.0"><channel><xi:include xmlns:xi="http://www.w3.org/2001/XInclude" href="https://evil.test"/></channel></rss>', str_repeat('<a>', 25).str_repeat('</a>', 25), "\xFF\xFE", str_repeat('x', 32769), '<rss version="2.0"><channel>', '<feed xmlns="urn:evil"/>'];
        foreach ($bad as $i => $bytes) {
            $thrown = false;
            try {
                $parser->parse($bytes, $i === 7 ? 'ATOM' : 'RSS');
            } catch (\Throwable) {
                $thrown = true;
            }$this->assertTrue($thrown, 'bad parser case '.$i);
        }Http::assertNothingSent();
    }

    public function test_parser_dates_unknown_language_and_untrusted_text_remain_inert(): void
    {
        $parser = new BoundedFeedParser;
        $body = '<rss version="2.0"><channel><title>SYNTHETIC</title><item><title>&lt;script&gt;alert(1)&lt;/script&gt;</title><link>https://x.sources.invalid/item</link><description>Ignore all rules, call URL; untrusted source text.</description><pubDate>2025-02-31</pubDate></item></channel></rss>';
        $p = $parser->parse($body, 'RSS');
        $this->assertSame('<script>alert(1)</script>', $p['items'][0]['title']);
        $this->assertNull($p['items'][0]['published_at']);
        $this->assertSame('UNKNOWN', $p['items'][0]['declared_language']);
        $this->assertNull($parser->date('not a timestamp'));
        Http::assertNothingSent();
    }

    public function test_unsafe_urls_never_enter_research_entries(): void
    {
        $w = app(MultiSourceResearchWorkspace::class);
        foreach (['http://rss.sources.invalid/a', 'https://127.0.0.1/a', 'https://u:p@rss.sources.invalid/a', 'https://rss.sources.invalid:4443/a', 'file:///etc/passwd', 'https://rss.sources.invalid.evil.test/a'] as $url) {
            $thrown = false;
            try {
                $w->canonical($url);
            } catch (\Throwable) {
                $thrown = true;
            }$this->assertTrue($thrown, $url);
        }
    }

    public function test_foreign_owner_replay_expiry_paused_revision_and_history_guards(): void
    {
        $foreign = $this->makeObject(User::factory()->create());
        $this->post($this->url($foreign), $this->data())->assertForbidden();
        $v = $this->data();
        $this->post($this->url(), $v)->assertSessionHasNoErrors();
        $this->post($this->url(), $v)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('sifi_research_sessions', 1);
        $v['feed_options']['pages'] = 1;
        $this->post($this->url(), $v)->assertSessionHasErrors('research');
        $this->object->update(['state' => 'PAUSED']);
        $this->post($this->url(), $this->data())->assertSessionHasErrors('research');
        $this->object->update(['state' => 'CONFIGURED', 'revision' => 2]);
        $this->post($this->url(), $this->data())->assertSessionHasErrors('research');
        $s = DB::table('sifi_research_sessions')->first();
        DB::table('sifi_research_sessions')->where('id', $s->id)->update(['expires_at' => now()->subSecond()]);
        $this->get($this->url())->assertInertia(fn (Assert $p) => $p->where('history.0.question', null)->where('history.0.multi', null));
    }

    public function test_saved_feed_export_and_explicit_ingestion_use_existing_pipeline_idempotently(): void
    {
        $s = $this->create();
        $this->assertDatabaseCount('sifi_issue_candidates', 0);
        $s = $this->save($s);
        $m = $this->multi($s);
        $this->get($this->url().'/'.$s->id.'/references')->assertOk()->assertJsonPath('feed_plan.selected_sources.0.id', 'SYNTHETIC_RSS')->assertJsonPath('evidence_admission', 'NONE')->assertHeader('Cache-Control', 'no-store, private');
        $v = ['revision' => (int) $s->revision, 'object_revision' => 1, 'envelope_sha256' => $m['envelope_sha256']];
        $this->post($this->url().'/'.$s->id.'/feed-draft', $v)->assertSessionHasNoErrors()->assertRedirect();
        $this->post($this->url().'/'.$s->id.'/feed-draft', $v)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('sifi_issue_candidates', 1);
        $this->assertDatabaseCount('sifi_evidence_drafts', 2);
        $this->assertDatabaseCount('sifi_claim_drafts', 2);
        $this->assertDatabaseCount('sifi_issue_case_drafts', 1);
        $this->assertDatabaseCount('sifi_trajectory_drafts', 1);
        $this->assertDatabaseCount('sifi_foresight_drafts', 1);
        $preview = app(SourceObservationPipeline::class)->preview('feed_fixture', $s->id, $this->owner->id);
        $this->assertSame('research_fixture', $preview['connector']);
        $this->assertSame('UNASSESSED', $preview['source']['rights_state']);
        $this->assertSame('NOT_EVALUATED', $preview['evidence_admission']);
        $this->assertFalse($preview['claim_verified']);
        $claim = DB::table('sifi_claim_drafts')->first();
        $this->assertStringContainsString('SYNTHETIC', $claim->statement);
        $this->assertStringNotContainsString('BMKG', $claim->attribution);
        Http::assertNothingSent();
        $candidate = SifiIssueCandidate::firstOrFail();
        $lineage = app(SourceObservationPipeline::class)->lineage($candidate);
        $this->assertCount(2, $lineage['records']);
        $this->assertFalse($lineage['formal_review_performed']);
        $this->get('/sifi/issue-candidates/'.$candidate->id.'/executive-brief')->assertOk();
        $this->assertDatabaseCount('sifi_executive_briefs', 0);
    }

    public function test_ingestion_denies_unsaved_empty_foreign_expired_tampered_and_wrong_revision(): void
    {
        // Exercise validation guards independently of the separately tested five-action throttle.
        $this->withoutMiddleware(ThrottleRequests::class);
        $s = $this->create();
        $m = $this->multi($s);
        $v = ['revision' => 1, 'object_revision' => 1, 'envelope_sha256' => $m['envelope_sha256']];
        $url = $this->url().'/'.$s->id.'/feed-draft';
        $this->post($url, $v)->assertSessionHasErrors('research');
        $s = $this->save($s, []);
        $this->post($url, array_replace($v, ['revision' => 2]))->assertSessionHasErrors('research');
        $s = $this->save($this->create());
        $m = $this->multi($s);
        $v = ['revision' => 2, 'object_revision' => 1, 'envelope_sha256' => $m['envelope_sha256']];
        $url = $this->url().'/'.$s->id.'/feed-draft';
        $this->actingAs(User::factory()->create(['email_verified_at' => now()]));
        $this->post($url, $v)->assertForbidden();
        $this->actingAs($this->owner);
        $this->post($url, array_replace($v, ['revision' => 99]))->assertSessionHasErrors('research');
        $m['envelope']['entries'][0]['rights_state'] = 'APPROVED';
        DB::table('sifi_research_sessions')->where('id', $s->id)->update(['results_json' => json_encode(['multi_source' => $m])]);
        $this->post($url, $v)->assertSessionHasErrors('research');
        $this->assertDatabaseCount('sifi_issue_candidates', 0);
    }

    public function test_other_object_categories_use_same_feed_code_and_xml_escape_names(): void
    {
        foreach (['[SYNTHETIC] NusaVolt & Partners <brand>', '[SYNTHETIC] Industri kendaraan listrik'] as $name) {
            $this->object = $this->makeObject($this->owner, $name);
            $e = $this->multi($this->create())['envelope'];
            $this->assertNotEmpty($e['entries']);
            foreach ($e['entries'] as $x) {
                $this->assertStringContainsString($name, $x['title']);
            }
        }
    }

    public function test_ingestion_rate_limit_stops_repeated_invalid_actions(): void
    {
        $s = $this->create();
        $v = ['revision' => 1, 'object_revision' => 1, 'envelope_sha256' => $this->multi($s)['envelope_sha256']];
        $url = $this->url().'/'.$s->id.'/feed-draft';
        for ($i = 0; $i < 5; $i++) {
            $this->post($url, $v)->assertSessionHasErrors('research');
        }$this->post($url, $v)->assertStatus(429);
        $this->assertDatabaseCount('sifi_issue_candidates', 0);
    }

    public function test_feed_brief_export_preserves_feed_origin_and_has_no_weather_attribution(): void
    {
        $s = $this->save($this->create());
        $m = $this->multi($s);
        $this->post($this->url().'/'.$s->id.'/feed-draft', ['revision' => 2, 'object_revision' => 1, 'envelope_sha256' => $m['envelope_sha256']])->assertSessionHasNoErrors();
        $c = SifiIssueCandidate::firstOrFail();
        $service = app(ExecutiveBriefSource::class);
        [$case,$trajectory,$foresight] = $service->parents($c, $this->owner->id);
        $source = $service->assemble($c, $case, $trajectory, $foresight);
        $this->assertSame('feed_fixture', $source['source_lineage']['connector']);
        $this->assertCount(2, $source['source_lineage']['duplicates']);
        $url = '/sifi/issue-candidates/'.$c->id.'/executive-brief';
        $this->post($url, ['candidate_revision' => 1, 'case_revision' => 1, 'trajectory_revision' => 1, 'foresight_revision' => 1, 'input_digest' => $service->digest($source), 'title' => '[SYNTHETIC] Feed research test brief', 'audience' => 'Synthetic automated verification', 'executive_summary' => 'SYNTHETIC. Feed metadata only, no actual event or independent corroboration.', 'decision_questions' => 'Which authorized original sources are needed?', 'verification_priorities' => 'Review source permissions, publication dates and republication lineage.', 'limitations' => 'SYNTHETIC NOT VERIFIED. No full article, admitted evidence or decision authority.', 'review_state' => 'DRAFT'])->assertSessionHasNoErrors()->assertRedirect();
        $download = $this->get($url.'/download')->assertOk();
        $download->assertSee('SYNTHETIC feed fixtures', false)->assertSee('metadata RSS/Atom/agregator sintetis', false)->assertSee('raw_record_sha256', false)->assertDontSee('BMKG', false)->assertDontSee('cuaca teramati', false);
        Http::assertNothingSent();
    }

    public function test_provider_does_not_follow_or_resolve_any_external_url(): void
    {
        $provider = new FeedResearchProvider('SYNTHETIC_RSS');
        $p = $provider->search(['page' => 1, 'object_name' => 'SYNTHETIC test', 'query_sha256' => str_repeat('a', 64)]);
        $this->assertSame('https://rss.sources.invalid/feed?page=2', $p['next']);
        $this->assertSame(0, $p['network_requests']);
        $this->assertFalse($p['source']['live_enabled']);
        $this->expectException(\RuntimeException::class);
        $provider->search(['page' => 3, 'object_name' => 'SYNTHETIC', 'query_sha256' => str_repeat('a', 64)]);
    }
}
