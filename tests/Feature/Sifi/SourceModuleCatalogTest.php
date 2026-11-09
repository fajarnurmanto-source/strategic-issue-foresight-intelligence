<?php

namespace Tests\Feature\Sifi;

use App\Services\Sifi\SourceModuleCatalog;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class SourceModuleCatalogTest extends TestCase
{
    public function test_all_source_families_are_visible_without_network_or_implicit_rights(): void
    {
        Http::preventStrayRequests();
        config([
            'sifi_e4.live_enabled' => false,
            'sifi_ih03b.crossref_metadata_enabled' => false,
            'sifi_ih03e.antara_rss_enabled' => false,
            'sifi_ih03d.gdelt_news_enabled' => false,
            'sifi_social.bluesky_public_search_enabled' => false,
            'sifi_google.trends_rss_enabled' => false,
        ]);

        $catalog = app(SourceModuleCatalog::class)->forE4(now('Asia/Jakarta')->toDateString());

        $this->assertSame('sifi.source-modules.v1', $catalog['schema']);
        $this->assertSame(8, $catalog['module_count']);
        $this->assertSame(28, $catalog['source_count']);
        $this->assertSame(0, $catalog['external_requests']);
        $this->assertFalse($catalog['rights_granted_by_catalog']);
        $this->assertSame('DENIED', $catalog['production_admission']);
        $this->assertSame(0, $catalog['e4_dispatchable_count']);
        $this->assertSame(0, $catalog['local_permission_pass_count']);

        $expected = ['MEDIA_ONLINE', 'GDELT', 'WEB_SEARCH', 'SOCIAL_MEDIA', 'RESEARCH',
            'SEARCH_TRENDS', 'OFFICIAL_OPEN_DATA', 'DOCUMENTS_DATASETS'];
        $this->assertSame($expected, array_column($catalog['modules'], 'id'));
        $ids = array_column($catalog['sources'], 'id');
        $this->assertCount(count(array_unique($ids)), $ids);
        foreach (['GOOGLE_WEB_SEARCH', 'GDELT_DOC', 'BLUESKY_PUBLIC_SEARCH',
            'INSTAGRAM_GRAPH', 'FACEBOOK_GRAPH', 'X_API', 'YOUTUBE_DATA',
            'CROSSREF', 'ANTARA_RSS', 'GOOGLE_TRENDS_RSS'] as $provider) {
            $this->assertContains($provider, $ids);
        }
        $byId = array_column($catalog['sources'], null, 'id');
        $this->assertSame('OAUTH2', $byId['FACEBOOK_GRAPH']['auth_method']);
        $this->assertSame('APP_APPROVAL_REQUIRED', $byId['INSTAGRAM_GRAPH']['access_tier']);
        $this->assertSame('OAUTH2', $byId['X_API']['auth_method']);
        $this->assertSame('PLAN_OR_QUOTA', $byId['X_API']['access_tier']);
        $this->assertSame('OAUTH2_OR_API_KEY', $byId['YOUTUBE_DATA']['auth_method']);
        $this->assertSame('FREE_PUBLIC', $byId['CROSSREF']['access_tier']);
        foreach ($catalog['sources'] as $source) {
            $this->assertFalse($source['dispatchable'], 'Unexpected E4 dispatch: '.$source['id']);
            $this->assertNotEmpty($source['module_id']);
            $this->assertNotEmpty($source['state']);
            $this->assertNotEmpty($source['notes']);
        }
        Http::assertNothingSent();
    }
    public function test_reviewed_connector_gates_can_pass_without_activating_any_e4_request(): void
    {
        Http::preventStrayRequests();
        \Carbon\Carbon::setTestNow('2026-11-09 08:00:00');
        try {
            config([
                'sifi_e4.live_enabled' => false,
                'database.default' => 'sqlite',
                'database.connections.sqlite.database' => ':memory:',
                'sifi_ih03b.crossref_metadata_enabled' => true,
                'sifi_ih03e.antara_rss_enabled' => true,
                'sifi_ih03d.gdelt_news_enabled' => true,
                'sifi_social.bluesky_public_search_enabled' => true,
                'sifi_google.trends_rss_enabled' => true,
            ]);

            $catalog = app(SourceModuleCatalog::class)->forE4('2026-11-09');
            $gates = array_column($catalog['sources'], 'permission_gate', 'id');

            $this->assertSame(5, $catalog['local_permission_pass_count']);
            $this->assertSame(0, $catalog['e4_dispatchable_count']);
            foreach (['CROSSREF', 'ANTARA_RSS', 'GDELT_DOC',
                'BLUESKY_PUBLIC_SEARCH', 'GOOGLE_TRENDS_RSS'] as $provider) {
                $this->assertSame('LOCAL_GATE_PASSED', $gates[$provider]);
            }
            $this->assertSame('NOT_APPLICABLE', $gates['GOOGLE_WEB_SEARCH']);
            $this->assertSame('DENIED', $catalog['production_admission']);
            Http::assertNothingSent();
        } finally {
            \Carbon\Carbon::setTestNow();
        }
    }

}
