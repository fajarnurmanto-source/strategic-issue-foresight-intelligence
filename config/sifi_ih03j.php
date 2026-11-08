<?php

return [
    // Browser-triggered, one-request-at-a-time LOCAL research only.
    // Underlying IH03B/IH03E opt-ins, expiration and isolated DB rules still apply.
    'owner_collection_enabled' => env('SIFI_IH03J_OWNER_COLLECTION', false),
];
