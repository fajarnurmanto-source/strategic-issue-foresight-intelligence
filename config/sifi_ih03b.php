<?php

return [
    // Owner-opt-in for isolated local metadata collection ONLY.
    // Never permits production, evidence admission, paid services, or generic URLs.
    'crossref_metadata_enabled' => env('SIFI_IH03B_CROSSREF_METADATA', false),
    'crossref_reviewed_on' => '2026-11-07',
    'crossref_review_expires' => '2026-11-14',
    'max_requests_per_day' => 5,
    'min_interval_seconds' => 60,
];
