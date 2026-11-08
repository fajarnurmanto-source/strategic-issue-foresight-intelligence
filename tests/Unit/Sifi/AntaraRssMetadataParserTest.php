<?php

namespace Tests\Unit\Sifi;

use App\Services\Sifi\Integration\AntaraRssMetadataParser;
use Tests\TestCase;

final class AntaraRssMetadataParserTest extends TestCase
{
    public function test_only_titles_links_dates_retained_no_description(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"><channel><title>ANTARA News</title><item>
<title>Berita publik terbaru</title>
<link>https://www.antaranews.com/berita/123/berita-publik</link>
<pubDate>Sun, 08 Nov 2026 10:00:00 +0700</pubDate>
<description>Unlicensed full summary must not be retained</description>
</item></channel></rss>
XML;
        $entries = app(AntaraRssMetadataParser::class)->parse($xml);
        $this->assertCount(1, $entries);
        $this->assertSame('Berita publik terbaru', $entries[0]['title']);
        $this->assertSame('WITHHELD_PENDING_RIGHTS', $entries[0]['snippet_kind']);
        $this->assertSame('', $entries[0]['snippet']);
        $this->assertSame('2026-11-08T03:00:00Z', $entries[0]['published_at']);
        $this->assertStringNotContainsString('Unlicensed full summary', json_encode($entries));
        $this->assertSame('NONE', $entries[0]['evidence_admission']);
    }

    public function test_dtd_and_external_entities_rejected_before_xml_parse(): void
    {
        $rss = '<!DOCTYPE rss [<!ENTITY xx SYSTEM "file:///etc/passwd">]>'
            .'<rss version="2.0"><channel><item><title>&xx;</title></item></channel></rss>';
        $this->expectException(\RuntimeException::class);
        app(AntaraRssMetadataParser::class)->parse($rss);
    }
}
