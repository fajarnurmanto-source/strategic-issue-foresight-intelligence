<?php

namespace App\Services\Sifi\Integration;

final class GdeltLocalPermission
{
    public function eligible(): bool
    {
        if (config('sifi_ih03d.gdelt_news_enabled') !== true
            || ! app()->environment(['local', 'testing'])
            || config('database.default') !== 'sqlite'
            || now('Asia/Jakarta')->toDateString() >= config('sifi_ih03d.gdelt_review_expires')) {
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
            throw new \RuntimeException('IH03D_GDELT_LOCAL_PERMISSION_DENIED');
        }
    }

    public function assertContact(string $email): void
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)
            || preg_match('/[\r\n]/', $email)
            || str_ends_with($email, '.test')
            || str_ends_with($email, '.invalid')) {
            throw new \InvalidArgumentException('IH03D_REAL_CONTACT_EMAIL_REQUIRED');
        }
    }
}
