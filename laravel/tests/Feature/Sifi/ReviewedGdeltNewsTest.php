<?php

namespace Tests\Feature\Sifi;

use App\Models\SifiMonitoringObject;
use App\Models\User;
use App\Services\Sifi\Integration\ReviewedGdeltNews;
use App\Services\Sifi\OneShotSearchTransport;
use App\Services\Sifi\SearchProviderRegistry;
use GuzzleHttp\Psr7\Response;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ReviewedGdeltNewsTest extends TestCase
{
    use RefreshDatabase;

    private string $temporary;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-09 12:00:00', 'Asia/Jakarta'));
        $this->temporary = sys_get_temp_dir().'/sifi-ih03d-review-'.Str::uuid();
        mkdir($this->temporary, 0700);
        $this->app->useStoragePath($this->temporary);
        config([
            'sifi_ih03d.gdelt_news_enabled' => true,
            'sifi_ih03.shadow_ingest_enabled' => true,
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        if (str_starts_with($this->temporary, sys_get_temp_dir().'/sifi-ih03d-review-')) {
            (new Filesystem)->deleteDirectory($this->temporary);
        }
        parent::tearDown();
    }

    public function test_bounded_gdelt_metadata_receipt_withheld_article_text(): void
    {
        $wire = new class extends OneShotSearchTransport
        {
            public int $calls = 0;

            protected function address(string $host): string
            {
                return '8.8.8.8';
            }

            protected function request(string $endpoint, array $options): \Illuminate\Http\Client\Response
            {
                $this->calls++;
                if (! str_contains($endpoint, 'maxrecords=5') || ! str_contains($endpoint, 'mode=artlist')) {
                    throw new \RuntimeException('UNEXPECTED_GDELT_PARAMETERS');
                }
                $body = json_encode(['articles' => [[
                    'url' => 'https://example.org/news/misinformation',
                    'title' => 'Misinformation digital media report',
                    'seendate' => '20261109T110000Z',
                    'domain' => 'example.org',
                    'language' => 'English',
                    'sourcecountry' => 'United States',
                    'socialimage' => 'https://unlicensed.example.org/asset.jpg',
                ]]], JSON_THROW_ON_ERROR);
                $response = new Response(200, ['Content-Type' => 'application/json'], $body);
                $options['on_headers']($response);
                $options['sink']->write($body);

                return new \Illuminate\Http\Client\Response($response);
            }
        };
        app()->instance(OneShotSearchTransport::class, $wire);
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $object = SifiMonitoringObject::create([
            'id' => (string) Str::uuid(), 'user_id' => $owner->id,
            'name' => '[SYNTHETIC] Media monitoring', 'description' => 'Isolated live adapter test',
            'category' => 'Research', 'objective' => 'Review news metadata only',
            'horizon' => '90 hari', 'geography' => null, 'aliases_json' => [],
            'keywords_json' => ['misinformation'], 'exclusions_json' => [],
            'state' => 'CONFIGURED', 'revision' => 1,
        ]);
        $sessionId = app(ReviewedGdeltNews::class)->collect($owner, $object, [
            'question' => 'misinformation', 'object_revision' => 1,
            'submission_key' => (string) Str::uuid(),
            'contact_email' => 'maintainer@example.org',
        ]);
        $envelope = json_decode(DB::table('sifi_research_sessions')->where('id', $sessionId)
            ->value('results_json'), true, 32, JSON_THROW_ON_ERROR)['multi_source']['envelope'];
        $this->assertSame(1, $wire->calls);
        $this->assertSame('OK', $envelope['calls'][0]['state']);
        $this->assertSame(1, $envelope['usage']['network_requests']);
        $this->assertCount(1, $envelope['entries']);
        $this->assertSame('WITHHELD_PENDING_RIGHTS', $envelope['entries'][0]['snippet_kind']);
        $this->assertSame('', $envelope['entries'][0]['snippet']);
        $this->assertSame('UNKNOWN_PROVIDER_FIRST_SEEN_ONLY', $envelope['entries'][0]['publication_precision']);
        $this->assertNull($envelope['entries'][0]['published_at']);
        $this->assertSame('2026-11-09', $envelope['entries'][0]['first_seen_at']);
        $this->assertSame('NONE', $envelope['entries'][0]['evidence_admission']);
        $encryptedReceipts = glob($this->temporary.'/app/private/sifi-search-runtime/*.receipt.enc');
        $this->assertCount(1, $encryptedReceipts);
        $this->assertStringNotContainsString('socialimage', Crypt::decryptString(file_get_contents($encryptedReceipts[0])));
        $this->assertDatabaseCount('sifi_connector_observations', 1);
        $this->assertDatabaseCount('sifi_real_source_signals', 0);
        $this->assertDatabaseCount('sifi_evidence_drafts', 0);
        Http::assertNothingSent();
    }

    public function test_gdelt_is_not_in_default_provider_set(): void
    {
        $this->assertNotContains('GDELT_DOC', SearchProviderRegistry::IDS);
        $this->assertSame('artlist', app(SearchProviderRegistry::class)
            ->parameters('GDELT_DOC', 'misinformation', 5)['mode']);
        Http::assertNothingSent();
    }
}
