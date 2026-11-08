<?php

namespace App\Services\Sifi\Integration;

/** One source-level opt-in, then automatic item triage; never per-post approvals. */
final class BlueskyLocalPermission
{
    public function assertAllowed(): void
    {
        $db = config('database.connections.sqlite.database');
        if (config('sifi_social.bluesky_public_search_enabled') !== true
            || ! app()->environment(['local', 'testing'])
            || config('database.default') !== 'sqlite'
            || now('Asia/Jakarta')->toDateString() >= config('sifi_social.review_expires')
            || ($db !== ':memory:' && (! is_string($db)
                || preg_match('#\A/mnt/d/Research/SIFI/05_VALIDATION/[^\r\n]+\.sqlite\z#D', $db) !== 1))) {
            throw new \RuntimeException('SIFI_SOCIAL_ISOLATED_OPT_IN_REQUIRED');
        }
    }

    public function assertContact(string $email): void
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)
            || preg_match('/[\r\n]/', $email)
            || str_ends_with($email, '.test') || str_ends_with($email, '.invalid')) {
            throw new \InvalidArgumentException('SIFI_SOCIAL_CONTACT_REQUIRED');
        }
    }

    public function assertQuery(string $query): void
    {
        if (mb_strlen($query) < 3 || mb_strlen($query) > 80
            || preg_match('/\A[\p{L}\p{N} #._-]+\z/uD', $query) !== 1
            || preg_match('/\s{2,}/u', $query)) {
            throw new \InvalidArgumentException('SIFI_SOCIAL_UNSAFE_QUERY');
        }
    }
}
