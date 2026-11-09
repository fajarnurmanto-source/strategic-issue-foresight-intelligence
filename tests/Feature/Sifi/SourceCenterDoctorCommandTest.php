<?php

namespace Tests\Feature\Sifi;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class SourceCenterDoctorCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    public function test_default_doctor_reads_local_install_state_without_remote_requests(): void
    {
        $this->artisan('sifi:sources:doctor')->assertExitCode(0);
        Http::assertNothingSent();
    }

    public function test_live_doctor_calls_only_two_fixed_public_google_rss_endpoints(): void
    {
        $published = now('Asia/Jakarta')->format('D, d M Y H:i:s O');
        $news = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<rss version="2.0"><channel><item><title>Uji berita</title>'
            .'<link>https://news.google.com/rss/articles/doctor</link>'
            .'<pubDate>'.$published.'</pubDate></item></channel></rss>';
        $trends = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<rss version="2.0" xmlns:ht="https://trends.google.com/trending/rss">'
            .'<channel><item><title>Uji tren</title>'
            .'<pubDate>'.$published.'</pubDate><ht:approx_traffic>1K+</ht:approx_traffic>'
            .'</item></channel></rss>';
        Http::fake([
            'https://news.google.com/rss/search*' => Http::response($news, 200),
            'https://trends.google.com/trending/rss*' => Http::response($trends, 200),
        ]);
        $this->artisan('sifi:sources:doctor', ['--live' => true, '--query' => 'Yogyakarta'])
            ->assertExitCode(0);
        Http::assertSentCount(2);
        Http::assertSent(static fn ($request): bool =>
            str_starts_with($request->url(), 'https://news.google.com/rss/search?'));
        Http::assertSent(static fn ($request): bool =>
            str_starts_with($request->url(), 'https://trends.google.com/trending/rss?'));
    }
}
