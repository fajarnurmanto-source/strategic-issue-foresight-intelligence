<?php

namespace App\Services\Sifi\Integration;

use Illuminate\Support\Facades\Http;

/**
 * One-shot, user-consented Google News public RSS metadata preview.
 * No Google Web Search API, full article scraping, scheduler or evidence admission.
 * Google News RSS search is an undocumented public feed and can change/disappear.
 */
final class GoogleNewsRssPreview
{
    public function fetch(string $query, string $startDate, string $endDate): array
    {
        $query = trim(preg_replace('/\s+/u', ' ', $query) ?? '');
        if (mb_strlen($query) < 3 || mb_strlen($query) > 200
            || preg_match('/[\x00-\x1f\x7f]/u', $query)) {
            throw new \RuntimeException('GOOGLE_NEWS_INVALID_QUERY');
        }
        // Endpoint and locale are constant. Never accept remote URL, headers, API keys or host from input.
        $response = Http::timeout(8)->connectTimeout(4)
            ->withOptions(['allow_redirects' => false])
            ->withHeaders([
                'Accept' => 'application/rss+xml, application/xml;q=0.8',
                'User-Agent' => 'SIFI-Research/1.0 (single-request public RSS metadata preview)',
            ])->get('https://news.google.com/rss/search', [
                'q' => $query,
                'hl' => 'id',
                'gl' => 'ID',
                'ceid' => 'ID:id',
            ]);
        if (! $response->successful()) {
            throw new \RuntimeException('GOOGLE_NEWS_RSS_UNAVAILABLE');
        }
        $body = $response->body();
        if (strlen($body) > 262144 || stripos($body, '<!DOCTYPE') !== false
            || stripos($body, '<!ENTITY') !== false) {
            throw new \RuntimeException('GOOGLE_NEWS_RSS_RESPONSE_REJECTED');
        }
        if (! class_exists(\DOMDocument::class)) {
            throw new \RuntimeException('GOOGLE_NEWS_XML_EXTENSION_REQUIRED');
        }
        $xml = new \DOMDocument();
        if (! @$xml->loadXML($body, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            throw new \RuntimeException('GOOGLE_NEWS_RSS_INVALID_XML');
        }

        $entries = [];
        $seen = [];
        foreach ($xml->getElementsByTagName('item') as $item) {
            if (! $item instanceof \DOMElement) {
                continue;
            }
            $published = trim($item->getElementsByTagName('pubDate')->item(0)?->textContent ?? '');
            try {
                $day = (new \DateTimeImmutable($published))
                    ->setTimezone(new \DateTimeZone('Asia/Jakarta'))->format('Y-m-d');
            } catch (\Throwable) {
                continue;
            }
            if ($day < $startDate || $day > $endDate) {
                continue;
            }
            $url = trim($item->getElementsByTagName('link')->item(0)?->textContent ?? '');
            $parts = parse_url($url);
            if (! filter_var($url, FILTER_VALIDATE_URL) || ($parts['scheme'] ?? '') !== 'https'
                || ! is_string($parts['host'] ?? null) || isset($parts['user']) || isset($parts['pass'])) {
                continue;
            }
            $key = hash('sha256', $url);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $entries[] = [
                'title' => mb_substr(trim($item->getElementsByTagName('title')->item(0)?->textContent ?? ''), 0, 300),
                'url' => $url,
                'published_at' => $day,
                'publisher' => mb_substr(trim($item->getElementsByTagName('source')->item(0)?->textContent ?? ''), 0, 120),
            ];
            if (count($entries) >= 10) {
                break;
            }
        }
        return [
            'provider_id' => 'GOOGLE_NEWS_RSS',
            'request_count' => 1,
            'api_cost_authorized' => false,
            'entries' => $entries,
            'response_sha256' => hash('sha256', $body),
            'coverage' => 'Google News public RSS latest-index metadata only; no guaranteed historical backfill, complete web search, independent origins, or verification.',
        ];
    }
}
