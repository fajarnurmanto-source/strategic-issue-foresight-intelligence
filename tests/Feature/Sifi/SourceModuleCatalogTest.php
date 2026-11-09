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
        ]);

        $catalog = app(SourceModuleCatalog::class)->forE4(now('Asia/Jakarta')->toDateString());

        $this->assertSame('sifi.source-modules.v1', $catalog['schema']);
        $this->assertSame(8, $catalog['module_count']);
        $this->assertSame(28, $catalog['source_count']);
        $this->assertSame(0, $catalog['external_requests']);
        $this->assertFalse($catalog['rights_granted_by_catalog']);
        $this->assertSame('DENIED', $catalog['production_admission']);
        $this->assertSame(0, $catalog['e4_dispatchable_count']);

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
        foreach ($catalog['sources'] as $source) {
            $this->assertFalse($source['dispatchable'], 'Unexpected E4 dispatch: '.$source['id']);
            $this->assertNotEmpty($source['module_id']);
            $this->assertNotEmpty($source['state']);
            $this->assertNotEmpty($source['notes']);
        }
        Http::assertNothingSent();
    }
}
