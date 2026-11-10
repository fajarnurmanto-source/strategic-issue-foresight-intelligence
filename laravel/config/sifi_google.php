<?php

return [
    // Only an explicit local isolated pilot can read Google's official Trending Now RSS.
    // RSS trends are not Google web-search results, verified events or commercial rights.
    'trends_rss_enabled' => env('SIFI_GOOGLE_TRENDS_RSS', false),
    'reviewed_on' => '2026-11-09',
    'review_expires' => '2026-12-09',
    'max_requests_per_day' => 3,
    'min_interval_seconds' => 300,
];
