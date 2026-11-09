<?php

/** Operational Core V2 remains disabled. The session panel is read-only. */
return [
    'enabled' => false,
    'shadow_workbench' => env('SIFI_CORE_V2_SHADOW_WORKBENCH', true),
    'mode' => 'SHADOW_READ_ONLY',
    'external_requests' => false,
    'ai_inference' => false,
    'queue_dispatch' => false,
    'evidence_admission' => false,
];
