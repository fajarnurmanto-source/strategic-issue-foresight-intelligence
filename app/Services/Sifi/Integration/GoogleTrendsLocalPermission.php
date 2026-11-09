<?php

namespace App\Services\Sifi\Integration;

/** Source opt-in is isolated to validation SQLite; never admits production. */
final class GoogleTrendsLocalPermission
{
    public function assertAllowed(): void
    {
        $db = config('database.connections.sqlite.database');
        if (config('sifi_google.trends_rss_enabled') !== true
            || ! app()->environment(['local', 'testing'])
            || config('database.default') !== 'sqlite'
            || now('Asia/Jakarta')->toDateString() >= config('sifi_google.review_expires')
            || ($db !== ':memory:' && (! is_string($db)
                || preg_match('#\A/mnt/d/Research/SIFI/05_VALIDATION/[^\r\n]+\.sqlite\z#D', $db) !== 1))) {
            throw new \RuntimeException('SIFI_GOOGLE_ISOLATED_OPT_IN_REQUIRED');
        }
    }
}
