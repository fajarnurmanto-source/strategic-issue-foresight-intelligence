<?php

namespace App\Services\Sifi\Integration;

/** Google access matrix; public RSS availability is not commercial licensing. */
final class GoogleSourceCoverage
{
    /** @return array<string, mixed> */
    public function snapshot(string $asOf): array
    {
        $reviewed = $asOf < (string) config('sifi_google.review_expires');
        $enabled = config('sifi_google.trends_rss_enabled') === true;

        return [
            [
                'id' => 'GOOGLE_TRENDS_RSS_ID',
                'label' => 'Google Trends — Trending Now Indonesia',
                'state' => ! $reviewed ? 'REVIEW_EXPIRED' : ($enabled ? 'LOCAL_OPT_IN_ONLY' : 'OFF_BY_DEFAULT'),
                'access' => 'Official public RSS export; no API key. One GET, up to ten trend terms.',
                'scope' => 'Search-interest trends only: not web search, news articles, social opinion, or event prevalence.',
                'action' => 'Pilot: scripts/bridge/sifi-google-trends-isolated-pilot.sh; commercial usage needs separate rights review.',
            ],
            [
                'id' => 'GOOGLE_CUSTOM_SEARCH_JSON',
                'label' => 'Google Custom Search JSON API (legacy)',
                'state' => 'NOT_AVAILABLE_TO_NEW_CUSTOMERS',
                'access' => 'Closed to new customers; existing API sunset 1 January 2027.',
                'scope' => 'Not a zero-spend fallback for new SIFI installations.',
                'action' => 'Do not automatically use this API or scrape Google Search result pages.',
            ],
            [
                'id' => 'GOOGLE_NEWS_RSS',
                'label' => 'Google News RSS',
                'state' => 'RIGHTS_REVIEW_REQUIRED',
                'access' => 'Feed URLs are not an unrestricted Google Search or licensed news API.',
                'scope' => 'Potential article discovery only; no content retention or independent origin inference.',
                'action' => 'Keep unconnected pending explicit suitability and commercial reuse review.',
            ],
        ];
    }
}
