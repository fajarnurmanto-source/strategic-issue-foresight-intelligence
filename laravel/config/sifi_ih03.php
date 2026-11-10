<?php

return [
    // Never enabled by an Integration Hub draft or by publishing assets.
    // Only an isolated SQLite database is eligible for this shadow projection.
    'shadow_ingest_enabled' => env('SIFI_IH03_SHADOW_INGEST', false),
];
