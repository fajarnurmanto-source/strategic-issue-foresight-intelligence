<?php

namespace Tests\Feature\Sifi;

use App\Models\SifiMonitoringObject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** External HTTP is always faked; these tests make no requests to Google. */
final class GoogleNewsRssPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::preventStrayRequests();
        config(['sifi_e4.live_enabled' => false]);
    }

    private function ownerAndObject(): array
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($owner)->post('/sifi/monitoring-objects', [
            'name' => 'Isu persampahan Yogyakarta',
            'description' => 'Objek uji terdaftar, bukan pernyataan fakta.',
            'category' => 'Isu',
            'aliases' => [],
            'keywords' => ['sampah kota Yogyakarta', 'pengelolaan sampah'],
            'exclusions' => [],
            'geography' => null,
            'objective' => 'Menemukan metadata berita.',
            'horizon' => '7 hari',
            'state' => 'CONFIGURED',
            'source_ids' => [],
        ])->assertSessionHasNoErrors();
        $object = SifiMonitoringObject::query()->where('user_id', $owner->id)->firstOrFail();
        return [$owner, $object];
    }

    public function test_google_news_preview_returns_real_shaped_public_metadata_with_one_bounded_http_call(): void
    {
        [, $object] = $this->ownerAndObject();
        $today = now('Asia/Jakarta')->toDateString();
        $published = now('Asia/Jakarta')->format('D, d M Y H:i:s O');
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<rss version="2.0"><channel><title>Google News</title>'
            .'<item><title>Uji RSS persampahan</title>'
            .'<link>https://news.google.com/rss/articles/test01</link>'
            .'<pubDate>'.$published.'</pubDate><source>Contoh Media</source></item>'
            .'</channel></rss>';
        Http::fake(['https://news.google.com/rss/search*' => Http::response($xml, 200, [
            'Content-Type' => 'application/rss+xml',
        ])]);

        $this->post('/sifi/discovery-period/google-news-preview', [
            'object_id' => $object->id,
            'object_revision' => $object->revision,
            'start_date' => $today,
            'end_date' => $today,
            'consent' => true,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $report = session('e4_result');
        $this->assertIsArray($report);
        $this->assertSame('PUBLIC_REAL_METADATA_PREVIEW', $report['result_state']);
        $this->assertSame('GOOGLE_NEWS_RSS', $report['items'][0]['provider']);
        $this->assertSame('Contoh Media', $report['items'][0]['publisher']);
        $this->assertSame('NONE', $report['evidence_admission']);
        $this->assertSame('DENIED', $report['production_admission']);
        $this->assertDatabaseCount('sifi_research_sessions', 0);
        Http::assertSentCount(1);
        Http::assertSent(static fn ($request): bool =>
            str_starts_with($request->url(), 'https://news.google.com/rss/search?')
            && str_contains($request->url(), 'ceid=ID%3Aid'));
    }

    public function test_google_news_preview_refuses_no_consent_and_untrusted_input_without_network(): void
    {
        [, $object] = $this->ownerAndObject();
        $date = now('Asia/Jakarta')->toDateString();
        $payload = [
            'object_id' => $object->id, 'object_revision' => $object->revision,
            'start_date' => $date, 'end_date' => $date,
        ];
        $this->post('/sifi/discovery-period/google-news-preview', $payload)
            ->assertSessionHasErrors('consent');
        $this->post('/sifi/discovery-period/google-news-preview', $payload + [
            'consent' => true, 'endpoint' => 'http://127.0.0.1:80',
        ])->assertSessionHasErrors('source');
        Http::assertNothingSent();
    }

    public function test_google_news_preview_rejects_foreign_monitoring_object(): void
    {
        [, $object] = $this->ownerAndObject();
        $date = now('Asia/Jakarta')->toDateString();
        $other = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($other)->post('/sifi/discovery-period/google-news-preview', [
            'object_id' => $object->id, 'object_revision' => $object->revision,
            'start_date' => $date, 'end_date' => $date, 'consent' => true,
        ])->assertNotFound();
        Http::assertNothingSent();
    }
}
