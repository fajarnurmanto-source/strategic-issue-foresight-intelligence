<?php

namespace App\Services\Sifi;

use Illuminate\Support\Facades\Crypt;

/** Separate namespace from consumed World Bank grants. No issuer or client grant endpoint. */
final class OneShotSearchAuthority
{
    /** @param array<string, mixed> $context */
    public function contextHash(string $provider, array $context): string
    {
        return hash('sha256', json_encode(['provider' => $provider, 'context' => $context], JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $context */
    public function path(string $provider, array $context): string
    {
        return storage_path('app/private/sifi-search-authority/'.$this->contextHash($provider, $context).'.enc');
    }

    /** @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function consume(string $provider, array $context): array
    {
        if (config('sifi.real_search_live') !== true || ($context['mode'] ?? null) !== 'LIVE') {
            throw new \RuntimeException('LIVE_SEARCH_LOCKED');
        }
        $registry = app(SearchProviderRegistry::class)->get($provider);
        if (now()->toDateString() >= $registry['expires_on']) {
            throw new \RuntimeException('PROVIDER_REVIEW_EXPIRED');
        }
        $path = $this->path($provider, $context);
        if (! is_file($path) || is_link($path) || filesize($path) > 16384) {
            throw new \RuntimeException('SEARCH_AUTHORITY_MISSING');
        }
        $handle = fopen($path, 'r+');
        if (! $handle || ! flock($handle, LOCK_EX | LOCK_NB)) {
            throw new \RuntimeException('SEARCH_AUTHORITY_BUSY');
        }
        try {
            $cipher = stream_get_contents($handle);
            if ($cipher === false) {
                throw new \RuntimeException('SEARCH_AUTHORITY_INVALID');
            }$p = json_decode(Crypt::decryptString($cipher), true, 20, JSON_THROW_ON_ERROR);
            if (! is_array($p)) {
                throw new \RuntimeException('SEARCH_AUTHORITY_INVALID');
            }
            if (($p['version'] ?? null) !== 'sifi-search-authority-1' || ($p['context_hash'] ?? null) !== $this->contextHash($provider, $context) || ($p['endpoint'] ?? null) !== $registry['endpoint'] || ($p['method'] ?? null) !== ($registry['method'] ?? 'GET') || ($p['cost_limit'] ?? null) !== 0 || ($p['request_limit'] ?? null) !== 1 || ($p['response_byte_limit'] ?? null) !== 32768 || ($p['consumed'] ?? true) !== false || ($p['expires_at'] ?? 0) <= now()->timestamp || ! preg_match('/\A[a-f0-9]{64}\z/D', $p['approval_scope_sha256'] ?? '') || ! preg_match('/\A[a-f0-9]{64}\z/D', $p['nonce'] ?? '')) {
                throw new \RuntimeException('SEARCH_AUTHORITY_INVALID');
            }
            // Contact is explicitly supplied by owner in the reviewed proposal, never inferred from account email.
            if (! is_string($p['contact_email'] ?? null) || ! filter_var($p['contact_email'], FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $p['contact_email']) || str_ends_with($p['contact_email'], '.invalid') || str_ends_with($p['contact_email'], '.test')) {
                throw new \RuntimeException('CONTACT_REQUIRED');
            }
            $p['consumed'] = true;
            $p['reserved_at'] = now()->toIso8601String();
            $cipher = Crypt::encryptString(json_encode($p, JSON_THROW_ON_ERROR));
            rewind($handle);
            if (fwrite($handle, $cipher) !== strlen($cipher) || ! ftruncate($handle, strlen($cipher)) || ! fflush($handle)) {
                throw new \RuntimeException('SEARCH_RESERVATION_FAILED');
            }if (function_exists('fsync')) {
                fsync($handle);
            }

            return $p;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
