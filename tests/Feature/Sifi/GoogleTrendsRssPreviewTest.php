<?php

namespace Tests\Feature\Sifi;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/** Google is fully mocked for offline repeatability. */
final class GoogleTrendsRssPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::preventStrayRequests();
    }

    public function test_consented_google_trends_rss_returns_bounded_attention_metadata_without_billing(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<rss version="2.0" xmlns:ht="https://trends.google.com/trending/rss">'
            .'<channel><item><title>Topik tren uji</title>'
            .'<pubDate>Mon, 09 Nov 2026 02:00:00 +0000</pubDate>'
            .'<ht:approx_traffic>2K+</ht:approx_traffic>'
            .'</item></channel></rss>';
        Http::fake(['https://trends.google.com/trending/rss*' => Http::response($xml, 200)]);

        $this->post('/sifi/source-catalog/google-trends-preview', ['consent' => true])
            ->assertSessionHasNoErrors()->assertRedirect('/sifi/source-catalog');
        $report = session('google_trends_preview');
        $this->assertSame((int) $user->id, $report['owner_id']);
        $this->assertSame('GOOGLE_TRENDS_RSS', $report['provider_id']);
        $this->assertSame('Topik tren uji', $report['entries'][0]['title']);
        $this->assertSame('2K+', $report['entries'][0]['approx_traffic']);
        $this->assertSame('NONE', $report['evidence_admission']);
        $this->assertSame('DENIED', $report['production_admission']);
        Http::assertSentCount(1);
        Http::assertSent(static fn ($request): bool =>
            str_starts_with($request->url(), 'https://trends.google.com/trending/rss?')
            && str_contains($request->url(), 'geo=ID'));

        $other = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($other)->get('/sifi/source-catalog')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('sifi/source-catalog')
                ->where('googleTrendsPreview', null)->etc());
    }

    public function test_google_trends_refuses_missing_consent_and_user_supplied_endpoints(): void
    {
        $this->actingAs(User::factory()->create(['email_verified_at' => now()]));
        $this->post('/sifi/source-catalog/google-trends-preview', [])
            ->assertSessionHasErrors('consent');
        $this->post('/sifi/source-catalog/google-trends-preview', [
            'consent' => true, 'url' => 'http://127.0.0.1/private',
        ])->assertSessionHasErrors('google_trends');
        Http::assertNothingSent();
    }
}
