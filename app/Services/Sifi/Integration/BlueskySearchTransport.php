<?php

namespace App\Services\Sifi\Integration;

use App\Services\Sifi\ResearchWorldBankAcquisition;
use GuzzleHttp\Psr7\{StreamDecoratorTrait, Utils};
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\Process\Process;

/** Exactly one pinned public AppView search GET, no pagination, redirects or retries. */
class BlueskySearchTransport
{
    public const ENDPOINT = 'https://public.api.bsky.app/xrpc/app.bsky.feed.searchPosts';

    protected function address(): string
    {
        $process = new Process(['/usr/bin/getent', 'ahostsv4', 'public.api.bsky.app'], null,
            ['PATH' => '/usr/bin:/bin']);
        $process->setTimeout(3);
        $process->run();
        if (! $process->isSuccessful()) {
            throw new \RuntimeException('SIFI_SOCIAL_DNS_POLICY');
        }
        $addresses = [];
        foreach (explode("\n", trim($process->getOutput())) as $line) {
            $fields = preg_split('/\s+/', $line);
            if (($fields[0] ?? '') !== '') {
                $addresses[] = $fields[0];
            }
        }

        return app(ResearchWorldBankAcquisition::class)->publicAddress(array_values(array_unique($addresses)));
    }

    protected function request(array $options, array $query): \Illuminate\Http\Client\Response
    {
        return Http::withOptions($options)->connectTimeout(8)->timeout(18)->get(self::ENDPOINT, $query);
    }

    public function fetch(string $query, string $contact): array
    {
        app(BlueskyLocalPermission::class)->assertAllowed();
        app(BlueskyLocalPermission::class)->assertContact($contact);
        app(BlueskyLocalPermission::class)->assertQuery($query);

        $sink = new class(Utils::streamFor(fopen('php://temp/maxmemory:262144', 'w+b'))) implements StreamInterface {
            use StreamDecoratorTrait;

            private StreamInterface $stream;

            public function write($data): int
            {
                if ($this->tell() + strlen($data) > 262144) {
                    throw new \LengthException('SIFI_SOCIAL_RESPONSE_BOUND');
                }

                return $this->stream->write($data);
            }
        };

        $status = null;
        $requests = 0;
        $start = hrtime(true);
        $phase = 'DNS_RESOLUTION';
        try {
            if (! extension_loaded('curl')) {
                throw new \RuntimeException('SIFI_SOCIAL_BOUNDED_CURL_REQUIRED');
            }
            $ip = $this->address();
            $options = [
                'allow_redirects' => false,
                'verify' => true,
                'proxy' => '',
                'decode_content' => false,
                'curl' => [
                    CURLOPT_FRESH_CONNECT => true,
                    CURLOPT_FORBID_REUSE => true,
                    CURLOPT_RESOLVE => ['public.api.bsky.app:443:'.$ip],
                ],
                'sink' => $sink,
                'headers' => [
                    'Accept' => 'application/json',
                    'Accept-Encoding' => 'identity',
                    'User-Agent' => 'SIFI-Public-Social-Research/1.0 (mailto:'.$contact.')',
                ],
                'on_headers' => static function ($response) use (&$status): void {
                    $status = $response->getStatusCode();
                    if ($status !== 200) {
                        throw new \RuntimeException($status === 429 ? 'SOCIAL_PROVIDER_QUOTA'
                            : ($status === 403 ? 'SOCIAL_PUBLIC_SEARCH_RESTRICTED' : 'SOCIAL_HTTP_UNAVAILABLE'));
                    }
                    if (! str_contains(strtolower($response->getHeaderLine('Content-Type')), 'application/json')) {
                        throw new \RuntimeException('SOCIAL_CONTENT_TYPE');
                    }
                    $encoding = strtolower($response->getHeaderLine('Content-Encoding'));
                    if ($encoding !== '' && $encoding !== 'identity') {
                        throw new \RuntimeException('SOCIAL_CONTENT_ENCODING');
                    }
                    $length = $response->getHeaderLine('Content-Length');
                    if ($length !== '' && (! ctype_digit($length) || (int) $length > 262144)) {
                        throw new \LengthException('SIFI_SOCIAL_RESPONSE_BOUND');
                    }
                },
            ];
            $requests = 1;
            $phase = 'HTTP_DISPATCH';
            $response = $this->request($options, ['q' => $query, 'limit' => 10, 'sort' => 'latest']);
            $phase = 'JSON_VALIDATION';
            if ($response->status() !== 200 || $status !== 200 || (hrtime(true) - $start) / 1e9 > 22) {
                throw new \RuntimeException('SOCIAL_RESPONSE_INVALID');
            }
            $sink->rewind();
            $bytes = $sink->getContents();
            $entries = app(BlueskyPublicSearchParser::class)->parse($bytes, $query);

            return [
                'state' => 'OK',
                'network_requests' => 1,
                'http_status' => 200,
                'response_bytes' => strlen($bytes),
                'response_sha256' => hash('sha256', $bytes),
                'entries' => $entries,
                'elapsed_ms' => (int) ((hrtime(true) - $start) / 1e6),
            ];
        } catch (\Throwable $error) {
            return [
                'state' => $status === 429 ? 'QUOTA'
                    : ($status === 403 ? 'RESTRICTED' : 'UNAVAILABLE'),
                'network_requests' => $requests,
                'http_status' => $status,
                'response_bytes' => 0,
                'response_sha256' => null,
                'entries' => [],
                'failure_stage' => $phase,
                'failure_category' => match ($status) {
                    403 => 'PUBLIC_SEARCH_ACCESS_RESTRICTED',
                    429 => 'PROVIDER_QUOTA',
                    default => $error instanceof \Illuminate\Http\Client\ConnectionException
                        ? 'CONNECTION_FAILURE' : 'VALIDATION_OR_HTTP_FAILURE',
                },
                'elapsed_ms' => (int) ((hrtime(true) - $start) / 1e6),
            ];
        }
    }
}
