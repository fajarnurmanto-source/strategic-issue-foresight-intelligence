<?php

return [
    // A deliberate local-only opt-in. Existing individual connector gates still apply.
    'live_enabled' => env('SIFI_E4_LIVE_ENABLED', false),
];
