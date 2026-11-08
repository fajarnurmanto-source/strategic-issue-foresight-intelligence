<?php

namespace Tests\Unit\Sifi;

use Tests\TestCase;

final class Ih03gBladePreviewTest extends TestCase
{
    public function test_local_dashboard_renders_receipt_counts_and_escapes_untrusted_metadata(): void
    {
        $snapshot = [
            'source_counts' => ['CROSSREF' => 1, 'ANTARA_RSS' => 1],
            'distinct_canonical_urls' => 2,
            'same_url_cross_source_candidates' => [],
            'titles' => [
                [
                    'provider' => 'CROSSREF',
                    'title' => '<script>alert(1)</script>',
                    'canonical_url' => 'https://doi.org/10.1234/test',
                    'published_at' => '2026',
                    'publication_precision' => 'YEAR',
                ],
                [
                    'provider' => 'ANTARA_RSS',
                    'title' => 'Berita uji',
                    'canonical_url' => 'https://www.antaranews.com/berita/1234/uji',
                    'published_at' => null,
                    'publication_precision' => 'UNKNOWN',
                ],
            ],
            'recurring_title_terms' => ['misinformation' => 2],
            'source_receipts' => [
                'CROSSREF' => '4c93499c-1f7e-4c27-8a10-f0fbbcc0b57d',
                'ANTARA_RSS' => '1f78108b-ec3c-4cee-aab7-e6f3a8f3b779',
            ],
        ];
        $html = view('sifi.ih03g-fusion', ['snapshot' => $snapshot])->render();
        $this->assertStringContainsString('Multi-Source', $html);
        $this->assertStringContainsString('Berita uji', $html);
        $this->assertStringContainsString('SHADOW', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('@media(max-width:430px)', $html);
        $this->assertStringContainsString('DATABASE WRITES: 0', $html);
    }
}
