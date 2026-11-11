<?php

namespace App\Services\Sifi\Integration;

use DOMDocument;
use DOMElement;

/** Convert Google's Trending Now RSS into bounded, non-evidentiary attention metadata. */
final class GoogleTrendsRssParser
{
    /** @return array<string, mixed> */
    public function parse(string $xml): array
    {
        if ($xml === '' || strlen($xml) > 524288 || ! mb_check_encoding($xml, 'UTF-8')
            || preg_match('/<!\s*(?:DOCTYPE|ENTITY)\b/i', $xml)) {
            throw new \RuntimeException('SIFI_GOOGLE_XML_BOUND_OR_DTD');
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $doc = new DOMDocument('1.0', 'UTF-8');
            $doc->resolveExternals = false;
            $doc->substituteEntities = false;
            if (! $doc->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS)
                || $doc->doctype !== null || $doc->documentElement?->localName !== 'rss') {
                throw new \RuntimeException('SIFI_GOOGLE_XML_INVALID');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $channels = $doc->documentElement->getElementsByTagName('channel');
        if ($channels->length !== 1) {
            throw new \RuntimeException('SIFI_GOOGLE_RSS_CHANNEL_INVALID');
        }
        $items = [];
        foreach ($channels->item(0)->childNodes as $node) {
            if ($node instanceof DOMElement && $node->localName === 'item') {
                $items[] = $node;
            }
        }
        if (count($items) > 100) {
            throw new \RuntimeException('SIFI_GOOGLE_RSS_ITEM_BOUND');
        }

        $entries = [];
        $seen = [];
        foreach ($items as $item) {
            if (count($entries) >= 10) {
                break;
            }
            $term = trim($this->directChild($item, 'title') ?? '');
            if ($term === '' || mb_strlen($term) > 140 || preg_match('/[\x00-\x1f\x7f]/u', $term)) {
                continue;
            }

            // Stable per-term Google Explore pointer; never treat RSS links to news
            // or Google as distinct originating publications.
            $url = 'https://trends.google.com/trends/explore?geo=ID&q='.rawurlencode($term);
            if (isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;

            $published = null;
            $rawDate = $this->directChild($item, 'pubDate');
            if (is_string($rawDate) && strlen($rawDate) <= 80) {
                try {
                    $published = (new \DateTimeImmutable($rawDate))->setTimezone(
                        new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
                } catch (\Throwable) {
                    // Unknown date is explicit, never guessed.
                }
            }

            $traffic = $this->directChild($item, 'approx_traffic');
            if (! is_string($traffic) || ! preg_match('/\A[0-9.,]+[KMB]?\+?\z/iD', $traffic)
                || strlen($traffic) > 20) {
                $traffic = null;
            }
            $entries[] = [
                'id' => hash('sha256', $url),
                'title' => 'Tren pencarian Google Indonesia: '.$term,
                'canonical_url' => $url,
                'url' => $url,
                'google_trend_term' => $term,
                'search_volume_label' => $traffic,
                // No monitoring object configuration enters the RSS parser/transport.
                'watch_term_matches' => [],
                'watch_relevance' => 'NOT_CLASSIFIED_LOCAL',
                'snippet' => '',
                'snippet_sha256' => hash('sha256', ''),
                'snippet_kind' => 'NOT_RETAINED',
                'published_at' => $published,
                'publication_precision' => $published === null ? 'UNKNOWN' : 'GOOGLE_FEED_DECLARED',
                'retrieved_at' => now()->toIso8601String(),
                'rights_state' => 'SEARCH_TREND_DERIVED_METADATA_ONLY_RIGHTS_REVIEW_REQUIRED',
                'retrieval_allowed' => false,
                'evidence_admission' => 'NONE',
                'origins' => ['trends.google.com'],
                'origin_state' => 'SEARCH_AGGREGATOR_NOT_INDEPENDENT_PUBLISHER',
                'lineage' => [[
                    'provider' => 'GOOGLE_TRENDS_RSS_ID',
                    'url' => 'https://trends.google.com/trending/rss?geo=ID',
                    'origin' => 'trends.google.com',
                ]],
                'relevance' => ['score' => 0, 'question_hits' => [], 'context_hits' => []],
                'relevance_group' => 'GENERAL_SEARCH_TREND_ONLY',
            ];
        }

        return ['entries' => $entries, 'feed_item_count' => count($items)];
    }

    private function directChild(DOMElement $parent, string $name): ?string
    {
        foreach ($parent->childNodes as $node) {
            if ($node instanceof DOMElement && $node->localName === $name) {
                return trim($node->textContent);
            }
        }

        return null;
    }
}
