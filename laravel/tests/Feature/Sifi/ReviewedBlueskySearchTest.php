<?php

namespace Tests\Feature\Sifi;

use App\Models\SifiMonitoringObject;
use App\Models\User;
use App\Services\Sifi\Integration\BlueskyPublicSearchParser;
use App\Services\Sifi\Integration\BlueskySearchTransport;
use App\Services\Sifi\Integration\ReviewedBlueskySearch;
use App\Services\Sifi\Integration\SocialWatchHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ReviewedBlueskySearchTest extends TestCase
{
    use RefreshDatabase;

    private string $temporary;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-09 12:00:00', 'Asia/Jakarta'));
        $this->temporary = sys_get_temp_dir().'/sifi-social-bluesky-'.Str::uuid();
        mkdir($this->temporary, 0700);
        $this->app->useStoragePath($this->temporary);
        config([
            'sifi_social.bluesky_public_search_enabled' => true,
            'sifi_ih03.shadow_ingest_enabled' => true,
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        if (str_starts_with($this->temporary, sys_get_temp_dir().'/sifi-social-bluesky-')) {
            (new \Illuminate\Filesystem\Filesystem)->deleteDirectory($this->temporary);
        }
        parent::tearDown();
    }

    public function test_public_posts_are_deduplicated_and_reduced_to_derived_metadata(): void
    {
        $uri = 'at://did:plc:u5cwb2mwiv2bfq53cjufe6yn/app.bsky.feed.post/3k4duaz5vfs2b';
        $wire = json_encode(['posts' => [
            ['uri' => $uri, 'record' => [
                'text' => 'Sampah menumpuk di jalan, mohon ditindaklanjuti',
                'createdAt' => '2026-11-09T04:00:00.000Z',
            ], 'likeCount' => 7, 'repostCount' => 2, 'replyCount' => 3],
            ['uri' => $uri, 'record' => ['text' => 'DUPLICATE']],
        ]], JSON_THROW_ON_ERROR);
        $entries = app(BlueskyPublicSearchParser::class)->parse($wire, 'sampah');

        $this->assertCount(1, $entries);
        $this->assertSame('https://bsky.app/profile/did:plc:u5cwb2mwiv2bfq53cjufe6yn/post/3k4duaz5vfs2b', $entries[0]['canonical_url']);
        $this->assertSame(['sampah', 'jalan'], $entries[0]['issue_term_candidates']);
        $this->assertSame(7, $entries[0]['engagement_snapshot']['likeCount']);
        $this->assertSame('', $entries[0]['snippet']);
        $this->assertStringNotContainsString('menumpuk', json_encode($entries, JSON_THROW_ON_ERROR));
        $this->assertSame('NONE', $entries[0]['evidence_admission']);
    }

    public function test_one_local_social_search_creates_an_owned_receipt_without_live_network(): void
    {
        $fake = new class extends BlueskySearchTransport {
            public int $calls = 0;

            public function fetch(string $query, string $contact): array
            {
                $this->calls++;
                $url = 'https://bsky.app/profile/did:plc:u5cwb2mwiv2bfq53cjufe6yn/post/3k4duaz5vfs2b';

                return [
                    'state' => 'OK', 'network_requests' => 1,
                    'http_status' => 200, 'response_bytes' => 100,
                    'response_sha256' => str_repeat('a', 64), 'elapsed_ms' => 100,
                    'entries' => [[
                        'id' => hash('sha256', $url),
                        'title' => 'Unggahan publik Bluesky terkait: sampah',
                        'canonical_url' => $url, 'url' => $url,
                        'snippet' => '', 'snippet_sha256' => hash('sha256', ''),
                        'published_at' => '2026-11-09T04:00:00Z',
                        'rights_state' => 'PUBLIC_POST_LINK_AND_DERIVED_METADATA_ONLY',
                        'origins' => ['bsky.app'],
                        'lineage' => [['provider' => 'BLUESKY_PUBLIC_SEARCH']],
                        'evidence_admission' => 'NONE',
                    ]],
                ];
            }
        };
        app()->instance(BlueskySearchTransport::class, $fake);
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $object = SifiMonitoringObject::create([
            'id' => (string) Str::uuid(), 'user_id' => $owner->id,
            'name' => '[SYNTHETIC] Persampahan', 'description' => 'Test only',
            'category' => 'Research', 'objective' => 'Cermati sinyal sosial',
            'horizon' => '90 hari', 'geography' => null, 'aliases_json' => [],
            'keywords_json' => ['sampah'], 'exclusions_json' => [],
            'state' => 'CONFIGURED', 'revision' => 1,
        ]);

        $result = app(ReviewedBlueskySearch::class)->collect($owner, $object, 'sampah', 'maintainer@example.org');
        $this->assertSame('OK', $result['state']);
        $this->assertSame(1, $result['post_links']);
        $this->assertSame(1, $result['shadow_observations']);
        $this->assertSame(1, $fake->calls);
        $envelope = json_decode(DB::table('sifi_research_sessions')
            ->where('id', $result['session_id'])->value('results_json'), true, 32,
            JSON_THROW_ON_ERROR)['multi_source']['envelope'];
        $this->assertSame('APPROVED_LIVE_CORPUS_METADATA', $envelope['mode']);
        $this->assertSame('NONE', $envelope['entries'][0]['evidence_admission']);
        $this->assertSame('FIRST_OBSERVED_IN_LOCAL_INDEX', $envelope['entries'][0]['local_history_state']);
        $this->assertSame(1, $envelope['social_watch_history']['first_observed_count']);
        $this->assertSame(0, $envelope['social_watch_history']['previously_observed_count']);
        $this->assertDatabaseCount('sifi_connector_observations', 1);
        $nextScan = app(SocialWatchHistory::class)->classify($owner->id, $object, $envelope['entries']);
        $this->assertSame(0, $nextScan['summary']['first_observed_count']);
        $this->assertSame(1, $nextScan['summary']['previously_observed_count']);
        $this->assertSame('PREVIOUSLY_OBSERVED', $nextScan['entries'][0]['local_history_state']);
        $this->assertDatabaseCount('sifi_evidence_drafts', 0);
        Http::assertNothingSent();

        $this->expectExceptionMessage('SIFI_SOCIAL_LOCAL_RATE_LIMIT');
        app(ReviewedBlueskySearch::class)->collect($owner, $object, 'sampah', 'maintainer@example.org');
    }

    public function test_public_search_403_is_a_restricted_provider_not_an_empty_corpus(): void
    {
        $transport = new class extends BlueskySearchTransport {
            public int $calls = 0;

            protected function address(): string
            {
                return '1.1.1.1'; // Unused dummy IP; no real network dispatched.
            }

            protected function request(array $options, array $query): \Illuminate\Http\Client\Response
            {
                $this->calls++;
                ($options['on_headers'])(new \GuzzleHttp\Psr7\Response(403));

                throw new \LogicException('HTTP 403 must abort in header policy');
            }
        };

        $result = $transport->fetch('sampah', 'maintainer@example.org');
        $this->assertSame('RESTRICTED', $result['state']);
        $this->assertSame(403, $result['http_status']);
        $this->assertSame('PUBLIC_SEARCH_ACCESS_RESTRICTED', $result['failure_category']);
        $this->assertSame(1, $result['network_requests']);
        $this->assertSame([], $result['entries']);
        $this->assertSame(1, $transport->calls);
        Http::assertNothingSent();
    }

    public function test_default_opt_out_never_calls_provider(): void
    {
        config(['sifi_social.bluesky_public_search_enabled' => false]);
        $this->expectExceptionMessage('SIFI_SOCIAL_ISOLATED_OPT_IN_REQUIRED');
        app(\App\Services\Sifi\Integration\BlueskyLocalPermission::class)->assertAllowed();
        Http::assertNothingSent();
    }
}
