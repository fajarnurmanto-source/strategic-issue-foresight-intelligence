<?php

namespace App\Services\Sifi\Integration;

use App\Services\Sifi\BoundedFeedParser;
use App\Services\Sifi\TavilySearchProvider;

/** Parse only bounded RSS titles, canonical links and publisher-declared dates. */
final class AntaraRssMetadataParser
{
    /** @return list<array<string, mixed>> */
    public function parse(string $xml): array
    {
        if (strlen($xml) > 131072 || ! mb_check_encoding($xml, 'UTF-8')
            || str_contains($xml, "\0")
            || preg_match('/<!\s*(?:DOCTYPE|ENTITY)|<\?xml-stylesheet|\b(?:xml:base|xi:include)\s*=/i', $xml)) {
            throw new \RuntimeException('IH03E_UNSAFE_FEED_BYTES');
        }
        $prior = libxml_use_internal_errors(true);
        libxml_clear_errors();
        try {
            $reader = new \XMLReader();
            if (! $reader->XML($xml, null, LIBXML_NONET | LIBXML_COMPACT)) {
                throw new \RuntimeException('IH03E_INVALID_RSS');
            }
            $nodes = 0;
            while ($reader->read()) {
                if (++$nodes > 20000 || $reader->depth > 18 || $reader->attributeCount > 20
                    || $reader->nodeType === \XMLReader::DOC_TYPE) {
                    throw new \RuntimeException('IH03E_XML_BOUNDS');
                }
            }
            $reader->close();
            if (libxml_get_errors()) {
                throw new \RuntimeException('IH03E_INVALID_RSS');
            }
            $doc = new \DOMDocument();
            $doc->resolveExternals = false;
            $doc->substituteEntities = false;
            if (! $doc->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)
                || libxml_get_errors() || $doc->documentElement?->localName !== 'rss') {
                throw new \RuntimeException('IH03E_INVALID_RSS');
            }
            $xp = new \DOMXPath($doc);
            $nodes = $xp->query('/rss/channel/item');
            if ($nodes === false || $nodes->length > 200) {
                throw new \RuntimeException('IH03E_ITEM_LIMIT');
            }
            $entries = [];
            $seen = [];
            foreach ($nodes as $node) {
                if (!$node instanceof \DOMElement) continue;
                $title = trim(strip_tags($xp->evaluate('string(title)', $node)));
                $url = trim($xp->evaluate('string(link)', $node));
                if ($title === '' || mb_strlen($title) > 300) {
                    continue;
                }
                try {
                    $canonical = TavilySearchProvider::safeMetadataUrl($url);
                } catch (\Throwable) {
                    continue;
                }
                if (! in_array(parse_url($canonical, PHP_URL_HOST), ['www.antaranews.com', 'antaranews.com'], true)
                    || strlen($canonical) > 1000 || isset($seen[$canonical])) {
                    continue;
                }
                $seen[$canonical] = true;
                $published = app(BoundedFeedParser::class)->date(trim($xp->evaluate('string(pubDate)', $node)));
                $entries[] = [
                    'id' => hash('sha256', $canonical),
                    'title' => $title,
                    'canonical_url' => $canonical,
                    'url' => $canonical,
                    'snippet' => '',
                    'snippet_kind' => 'WITHHELD_PENDING_RIGHTS',
                    'snippet_sha256' => hash('sha256', ''),
                    'published_at' => $published,
                    'publication_precision' => $published === null ? 'UNKNOWN' : 'RSS_DECLARED_SECOND',
                    'retrieved_at' => now()->toIso8601String(),
                    'fixture_loaded_at' => null,
                    'rights_state' => 'RSS_TITLE_LINK_ONLY_ARTICLE_RIGHTS_UNASSESSED',
                    'attribution' => [
                        'provider' => 'ANTARA News RSS',
                        'source_url' => $canonical,
                        'license_url' => null,
                        'terms_url' => 'https://www.antaranews.com/rss/',
                        'notice' => 'Publisher RSS title and link only; no article content, summary, image or evidence admitted.',
                    ],
                    'retrieval_allowed' => false,
                    'evidence_admission' => 'NONE',
                    'origins' => ['www.antaranews.com'],
                    'origin_state' => 'DECLARED_NOT_VERIFIED',
                    'lineage' => [[
                        'provider' => 'ANTARA_RSS',
                        'url' => $canonical,
                        'origin' => 'www.antaranews.com',
                        'publication_precision' => $published === null ? 'UNKNOWN' : 'RSS_DECLARED_SECOND',
                    ]],
                    'relevance' => ['score' => 0, 'question_hits' => [], 'context_hits' => []],
                    'relevance_group' => 'UNFILTERED_OFFICIAL_RSS_HEADLINE',
                ];
                if (count($entries) >= 5) {
                    break;
                }
            }
            return $entries;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($prior);
        }
    }
}
