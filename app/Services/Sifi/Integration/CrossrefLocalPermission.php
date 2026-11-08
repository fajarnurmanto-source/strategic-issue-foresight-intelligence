<?php

namespace App\Services\Sifi\Integration;

/** An isolated research permission; never an authorization for production. */
final class CrossrefLocalPermission
{
    public function eligible(): bool
    {
        if (config('sifi_ih03b.crossref_metadata_enabled') !== true
            || ! app()->environment(['local', 'testing'])
            || config('database.default') !== 'sqlite'
            || now('Asia/Jakarta')->toDateString() >= config('sifi_ih03b.crossref_review_expires')) {
            return false;
        }

        $database = config('database.connections.sqlite.database');
        if ($database === ':memory:') {
            return true;
        }

        return is_string($database)
            && preg_match('#\A/mnt/d/Research/SIFI/05_VALIDATION/[^\r\n]+\.sqlite\z#D', $database) === 1;
    }

    public function assertAllowed(): void
    {
        if (! $this->eligible()) {
            throw new \RuntimeException('IH03B_CROSSREF_LOCAL_PERMISSION_DENIED');
        }
    }

    public function assertContact(string $email): void
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)
            || preg_match('/[\r\n]/', $email)
            || str_ends_with($email, '.test')
            || str_ends_with($email, '.invalid')) {
            throw new \InvalidArgumentException('IH03B_REAL_CONTACT_EMAIL_REQUIRED');
        }
    }
}
