<?php

return [
    // Explicit isolated-local opt-in. No production, article retrieval, or evidence admission.
    'gdelt_news_enabled' => env('SIFI_IH03D_GDELT_NEWS', false),
    'gdelt_reviewed_on' => '2026-11-08',
    'gdelt_review_expires' => '2026-11-15',
    'max_requests_per_day' => 2,
    'min_interval_seconds' => 120,
];
