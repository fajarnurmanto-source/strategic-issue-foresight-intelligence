<?php

namespace App\Services\Sifi\Integration;

final class AntaraLocalPermission
{
    public function assertAllowed(): void
    {
        $db = config('database.connections.sqlite.database');
        if (config('sifi_ih03e.antara_rss_enabled') !== true
            || ! app()->environment(['local', 'testing'])
            || config('database.default') !== 'sqlite'
            || now('Asia/Jakarta')->toDateString() >= config('sifi_ih03e.review_expires')
            || ($db !== ':memory:' && (! is_string($db)
                || preg_match('#\A/mnt/d/Research/SIFI/05_VALIDATION/[^\r\n]+\.sqlite\z#D', $db) !== 1))) {
            throw new \RuntimeException('IH03E_ANTARA_ISOLATED_OPT_IN_REQUIRED');
        }
    }

    public function assertContact(string $email): void
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)
            || preg_match('/[\r\n]/', $email)
            || str_ends_with($email, '.test') || str_ends_with($email, '.invalid')) {
            throw new \InvalidArgumentException('IH03E_CONTACT_REQUIRED');
        }
    }
}
