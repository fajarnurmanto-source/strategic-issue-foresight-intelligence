<?php

namespace Tests\Feature\Sifi;

use App\Models\SifiMonitoringObject;
use App\Models\User;
use App\Services\Sifi\Integration\GoogleTrendsLocalPermission;
use App\Services\Sifi\Integration\GoogleTrendsRssParser;
use App\Services\Sifi\Integration\GoogleTrendsRssTransport;
use App\Services\Sifi\Integration\ReviewedGoogleTrends;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ReviewedGoogleTrendsTest extends TestCase
{
    use RefreshDatabase;

    private string $temporary;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-09 10:00:00', 'Asia/Jakarta'));
        $this->temporary = sys_get_temp_dir().'/sifi-google-trends-'.Str::uuid();
        mkdir($this->temporary, 0700);
        $this->app->useStoragePath($this->temporary);
        config([
            'sifi_google.trends_rss_enabled' => true,
            'sifi_ih03.shadow_ingest_enabled' => true,
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        if (str_starts_with($this->temporary, sys_get_temp_dir().'/sifi-google-trends-')) {
            (new Filesystem)->deleteDirectory($this->temporary);
        }
        parent::tearDown();
    }

    public function test_official_rss_terms_are_deduplicated_with_relevance_not_event_claims(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0" xmlns:ht="https://trends.google.com/trending/rss"><channel>'
            .'<item><title>Sampah Jogja</title><pubDate>Mon, 09 Nov 2026 00:00:00 GMT</pubDate><ht:approx_traffic>5K+</ht:approx_traffic></item>'
            .'<item><title>Sampah Jogja</title></item>'
            .'<item><title>Sepak bola</title><ht:approx_traffic>10K+</ht:approx_traffic></item>'
            .'</channel></rss>';
        $parsed = app(GoogleTrendsRssParser::class)->parse($xml);
        $this->assertSame(3, $parsed['feed_item_count']);
        $this->assertCount(2, $parsed['entries']);
        $first = $parsed['entries'][0];
        $this->assertSame('NOT_CLASSIFIED_LOCAL', $first['watch_relevance']);
        $this->assertSame([], $first['watch_term_matches']);
        $this->assertSame('5K+', $first['search_volume_label']);
        $this->assertSame('NONE', $first['evidence_admission']);
        $this->assertSame('', $first['snippet']);
        $this->assertSame('NOT_CLASSIFIED_LOCAL', $parsed['entries'][1]['watch_relevance']);
        $this->assertSame('SEARCH_AGGREGATOR_NOT_INDEPENDENT_PUBLISHER', $first['origin_state']);
        $this->assertStringStartsWith('https://trends.google.com/trends/explore?', $first['canonical_url']);
    }

    public function test_xml_dtd_is_rejected_before_parser(): void
    {
        $xml = '<?xml version="1.0"?><!DOCTYPE rss [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>'
            .'<rss><channel><item><title>&xxe;</title></item></channel></rss>';
        $this->expectExceptionMessage('SIFI_GOOGLE_XML_BOUND_OR_DTD');
        app(GoogleTrendsRssParser::class)->parse($xml);
    }

    public function test_isolated_google_source_creates_owned_research_receipt_without_network_in_test(): void
    {
        $fake = new class extends GoogleTrendsRssTransport
        {
            public int $calls = 0;

            public function fetch(): array
            {
                $this->calls++;
                $url = 'https://trends.google.com/trends/explore?geo=ID&q=Sampah%20Jogja';

                return [
                    'state' => 'OK', 'network_requests' => 1,
                    'http_status' => 200, 'response_bytes' => 154,
                    'response_sha256' => str_repeat('b', 64), 'elapsed_ms' => 100,
                    'feed_item_count' => 1,
                    'entries' => [[
                        'id' => hash('sha256', $url),
                        'title' => 'Tren pencarian Google Indonesia: Sampah Jogja',
                        'canonical_url' => $url,
                        'google_trend_term' => 'Sampah Jogja',
                        'watch_relevance' => 'NOT_CLASSIFIED_LOCAL',
                        'watch_term_matches' => [],
                        'relevance' => ['score' => 0, 'question_hits' => [], 'context_hits' => []],
                        'relevance_group' => 'GENERAL_SEARCH_TREND_ONLY',
                        'snippet' => '', 'snippet_sha256' => hash('sha256', ''),
                        'published_at' => '2026-11-09T00:00:00Z',
                        'rights_state' => 'SEARCH_TREND_DERIVED_METADATA_ONLY_RIGHTS_REVIEW_REQUIRED',
                        'origins' => ['trends.google.com'],
                        'lineage' => [['provider' => 'GOOGLE_TRENDS_RSS_ID']],
                        'evidence_admission' => 'NONE',
                    ]],
                ];
            }
        };
        app()->instance(GoogleTrendsRssTransport::class, $fake);
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $object = SifiMonitoringObject::query()->create([
            'id' => (string) Str::uuid(), 'user_id' => $owner->id,
            'name' => '[SYNTHETIC] Persampahan', 'description' => 'Test only',
            'category' => 'Research', 'objective' => 'Cermati perhatian pencarian',
            'horizon' => '90 hari', 'geography' => null, 'aliases_json' => [],
            'keywords_json' => ['sampah'], 'exclusions_json' => [],
            'state' => 'CONFIGURED', 'revision' => 1,
        ]);
        $result = app(ReviewedGoogleTrends::class)->collect($owner, $object);
        $this->assertSame('OK', $result['state']);
        $this->assertSame(1, $result['trend_terms']);
        $this->assertSame(1, $result['keyword_match_candidates']);
        $this->assertSame(1, $result['shadow_observations']);
        $this->assertSame(1, $fake->calls);
        $data = json_decode(DB::table('sifi_research_sessions')
            ->where('id', $result['session_id'])->value('results_json'), true, 32, JSON_THROW_ON_ERROR);
        $envelope = $data['multi_source']['envelope'];
        $this->assertSame('NOT_VERIFIED', $envelope['independence']);
        $this->assertSame('NONE', $envelope['entries'][0]['evidence_admission']);
        $this->assertSame('FIRST_OBSERVED_IN_LOCAL_INDEX', $envelope['entries'][0]['local_history_state']);
        $this->assertDatabaseCount('sifi_connector_observations', 1);
        $this->assertDatabaseCount('sifi_evidence_drafts', 0);
        Http::assertNothingSent();
        $this->expectExceptionMessage('SIFI_GOOGLE_LOCAL_RATE_LIMIT');
        app(ReviewedGoogleTrends::class)->collect($owner, $object);
    }

    public function test_default_disabled_and_unisolated_database_are_denied(): void
    {
        config(['sifi_google.trends_rss_enabled' => false]);
        try {
            app(GoogleTrendsLocalPermission::class)->assertAllowed();
            $this->fail('Default off must reject');
        } catch (\RuntimeException $error) {
            $this->assertSame('SIFI_GOOGLE_ISOLATED_OPT_IN_REQUIRED', $error->getMessage());
        }
        config([
            'sifi_google.trends_rss_enabled' => true,
            'database.connections.sqlite.database' => '/home/fajar/projects/sifi-laravel-vnext/database/database.sqlite',
        ]);
        $this->expectExceptionMessage('SIFI_GOOGLE_ISOLATED_OPT_IN_REQUIRED');
        app(GoogleTrendsLocalPermission::class)->assertAllowed();
        Http::assertNothingSent();
    }
}
