<?php

namespace Tests\Feature\Sifi;

use App\Models\SifiMonitoringObject;
use App\Models\User;
use App\Services\Sifi\Integration\AntaraRssTransport;
use App\Services\Sifi\Integration\ReviewedAntaraRss;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ReviewedAntaraRssTest extends TestCase
{
    use RefreshDatabase;

    private string $temporary;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-09 12:00:00', 'Asia/Jakarta'));
        $this->temporary = sys_get_temp_dir().'/sifi-ih03e-review-'.Str::uuid();
        mkdir($this->temporary, 0700);
        $this->app->useStoragePath($this->temporary);
        config([
            'sifi_ih03e.antara_rss_enabled' => true,
            'sifi_ih03.shadow_ingest_enabled' => true,
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        if (str_starts_with($this->temporary, sys_get_temp_dir().'/sifi-ih03e-review-')) {
            (new \Illuminate\Filesystem\Filesystem)->deleteDirectory($this->temporary);
        }
        parent::tearDown();
    }

    public function test_bounded_real_publisher_receipt_without_live_http_or_evidence(): void
    {
        $wire = new class extends AntaraRssTransport {
            public int $calls = 0;
            public function fetch(string $contact): array
            {
                $this->calls++;
                $url = 'https://www.antaranews.com/berita/123/berita-baru';
                return [
                    'state' => 'OK', 'network_requests' => 1, 'http_status' => 200,
                    'response_bytes' => 300, 'response_sha256' => str_repeat('a', 64),
                    'elapsed_ms' => 200, 'entries' => [[
                        'id' => hash('sha256', $url), 'title' => 'Berita terkini ANTARA',
                        'canonical_url' => $url, 'url' => $url,
                        'snippet' => '', 'snippet_kind' => 'WITHHELD_PENDING_RIGHTS',
                        'snippet_sha256' => hash('sha256', ''),
                        'published_at' => '2026-11-09T05:00:00Z',
                        'publication_precision' => 'RSS_DECLARED_SECOND',
                        'rights_state' => 'RSS_TITLE_LINK_ONLY_ARTICLE_RIGHTS_UNASSESSED',
                        'origins' => ['www.antaranews.com'], 'origin_state' => 'DECLARED_NOT_VERIFIED',
                        'lineage' => [['provider' => 'ANTARA_RSS']],
                        'evidence_admission' => 'NONE',
                    ]],
                ];
            }
        };
        app()->instance(AntaraRssTransport::class, $wire);
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $object = SifiMonitoringObject::create([
            'id' => (string) Str::uuid(), 'user_id' => $owner->id,
            'name' => '[SYNTHETIC] News research', 'description' => 'Isolated test',
            'category' => 'Research', 'objective' => 'Review news headlines',
            'horizon' => '90 hari', 'geography' => null, 'aliases_json' => [],
            'keywords_json' => ['news'], 'exclusions_json' => [],
            'state' => 'CONFIGURED', 'revision' => 1,
        ]);
        $result = app(ReviewedAntaraRss::class)->collect($owner, $object, 'maintainer@example.org');
        $this->assertSame('OK', $result['state']);
        $this->assertSame(1, $result['news_links']);
        $this->assertSame(1, $result['shadow_observations']);
        $this->assertSame(1, $wire->calls);
        $envelope = json_decode(DB::table('sifi_research_sessions')
            ->where('id', $result['session_id'])->value('results_json'), true, 32,
            JSON_THROW_ON_ERROR)['multi_source']['envelope'];
        $this->assertSame('APPROVED_LIVE_CORPUS_METADATA', $envelope['mode']);
        $this->assertSame('NONE', $envelope['entries'][0]['evidence_admission']);
        $this->assertDatabaseCount('sifi_connector_observations', 1);
        $this->assertDatabaseCount('sifi_evidence_drafts', 0);
        Http::assertNothingSent();
    }
}
