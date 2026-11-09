<?php

return [
    // Source-level, isolated opt-in only. No background dispatch or production admission.
    'bluesky_public_search_enabled' => env('SIFI_SOCIAL_BLUESKY_PUBLIC_SEARCH', false),
    'contact_email' => env('SIFI_SOCIAL_CONTACT_EMAIL'),
    // Disabled until an owner explicitly enables unattended local collection.
    'auto_watch_enabled' => env('SIFI_SOCIAL_AUTOWATCH_ENABLED', false),
    'auto_watch_owner_id' => env('SIFI_SOCIAL_AUTOWATCH_OWNER_ID'),
    'reviewed_on' => '2026-11-08',
    'review_expires' => '2027-02-08',
    'max_requests_per_day' => 3,
    'min_interval_seconds' => 180,
    'max_posts_per_request' => 10,
];
