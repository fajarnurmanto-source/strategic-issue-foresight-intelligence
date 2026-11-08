<?php

return [
    // Source-level, isolated opt-in only. No background dispatch or production admission.
    'bluesky_public_search_enabled' => env('SIFI_SOCIAL_BLUESKY_PUBLIC_SEARCH', false),
    'contact_email' => env('SIFI_SOCIAL_CONTACT_EMAIL'),
    'reviewed_on' => '2026-11-08',
    'review_expires' => '2027-02-08',
    'max_requests_per_day' => 3,
    'min_interval_seconds' => 180,
    'max_posts_per_request' => 10,
];
