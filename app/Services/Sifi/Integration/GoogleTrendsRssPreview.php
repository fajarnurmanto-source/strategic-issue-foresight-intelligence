<?php

namespace App\Services\Sifi\Integration;

use Illuminate\Support\Facades\Http;

/**
 * One-shot Google Trends Indonesia RSS export (attention signal, not social/news prevalence).
 * Public feed; fixed URL, no user-provided endpoint, no Google Search API and no scheduler.
 */
final class GoogleTrendsRssPreview
{
    public function fetchIndonesia(): array
    {
        $response = Http::timeout(8)->connectTimeout(4)
            ->withOptions(['allow_redirects' => false])
            ->withHeaders([
                'Accept' => 'application/rss+xml, application/xml;q=0.8',
                'User-Agent' => 'SIFI-Research/1.0 (single-request public RSS trend preview)',
            ])->get('https://trends.google.com/trending/rss', ['geo' => 'ID']);
        if (! $response->successful()) {
            throw new \RuntimeException('GOOGLE_TRENDS_RSS_UNAVAILABLE');
        }
        $body = $response->body();
        if (strlen($body) > 131072 || stripos($body, '<!DOCTYPE') !== false
            || stripos($body, '<!ENTITY') !== false) {
            throw new \RuntimeException('GOOGLE_TRENDS_RSS_RESPONSE_REJECTED');
        }
        if (! class_exists(\DOMDocument::class)) {
            throw new \RuntimeException('GOOGLE_TRENDS_XML_EXTENSION_REQUIRED');
        }
        $xml = new \DOMDocument();
        if (! @$xml->loadXML($body, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            throw new \RuntimeException('GOOGLE_TRENDS_RSS_INVALID_XML');
        }

        $entries = [];
        foreach ($xml->getElementsByTagName('item') as $item) {
            if (! $item instanceof \DOMElement) {
                continue;
            }
            $title = mb_substr(trim($item->getElementsByTagName('title')->item(0)?->textContent ?? ''), 0, 200);
            if ($title === '') {
                continue;
            }
            $published = trim($item->getElementsByTagName('pubDate')->item(0)?->textContent ?? '');
            try {
                $started = (new \DateTimeImmutable($published))
                    ->setTimezone(new \DateTimeZone('Asia/Jakarta'))->format('Y-m-d H:i');
            } catch (\Throwable) {
                $started = null;
            }
            $trafficNode = $item->getElementsByTagNameNS('*', 'approx_traffic')->item(0);
            $traffic = mb_substr(trim($trafficNode?->textContent ?? ''), 0, 40);
            $entries[] = [
                'title' => $title,
                'started_at' => $started,
                'approx_traffic' => $traffic,
            ];
            if (count($entries) >= 10) {
                break;
            }
        }
        return [
            'provider_id' => 'GOOGLE_TRENDS_RSS',
            'country' => 'ID',
            'entries' => $entries,
            'request_count' => 1,
            'response_sha256' => hash('sha256', $body),
            'coverage' => 'Public Google Trends Indonesia trending-search RSS; attention estimates only, not all searches, news mentions, population prevalence or causal evidence.',
            'evidence_admission' => 'NONE',
            'production_admission' => 'DENIED',
        ];
    }
}
