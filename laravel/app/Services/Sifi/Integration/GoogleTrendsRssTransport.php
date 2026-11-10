<?php

namespace App\Services\Sifi\Integration;

use App\Services\Sifi\ResearchWorldBankAcquisition;
use GuzzleHttp\Psr7\{StreamDecoratorTrait, Utils};
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\Process\Process;

/** One bounded official Trends RSS export request, not Google Search scraping. */
class GoogleTrendsRssTransport
{
    public const ENDPOINT = 'https://trends.google.com/trending/rss?geo=ID';

    protected function address(): string
    {
        $process = new Process(['/usr/bin/getent', 'ahostsv4', 'trends.google.com'], null,
            ['PATH' => '/usr/bin:/bin']);
        $process->setTimeout(3);
        $process->run();
        if (! $process->isSuccessful()) {
            throw new \RuntimeException('SIFI_GOOGLE_DNS_POLICY');
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

    protected function request(array $options): \Illuminate\Http\Client\Response
    {
        return Http::withOptions($options)->connectTimeout(8)->timeout(18)->get(self::ENDPOINT);
    }

    public function fetch(): array
    {
        app(GoogleTrendsLocalPermission::class)->assertAllowed();
        $sink = new class(Utils::streamFor(fopen('php://temp/maxmemory:524288', 'w+b'))) implements StreamInterface {
            use StreamDecoratorTrait;

            private StreamInterface $stream;

            public function write($data): int
            {
                if ($this->tell() + strlen($data) > 524288) {
                    throw new \LengthException('SIFI_GOOGLE_RESPONSE_BOUND');
                }

                return $this->stream->write($data);
            }
        };
        $status = null;
        $requests = 0;
        $start = hrtime(true);
        $stage = 'DNS_RESOLUTION';
        try {
            if (! extension_loaded('curl')) {
                throw new \RuntimeException('SIFI_GOOGLE_BOUNDED_CURL_REQUIRED');
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
                    CURLOPT_RESOLVE => ['trends.google.com:443:'.$ip],
                ],
                'sink' => $sink,
                'headers' => [
                    'Accept' => 'application/rss+xml, application/xml, text/xml',
                    'Accept-Encoding' => 'identity',
                    'User-Agent' => 'SIFI-Local-Google-Trends-Research/1.0',
                ],
                'on_headers' => static function ($response) use (&$status): void {
                    $status = $response->getStatusCode();
                    if ($status !== 200) {
                        throw new \RuntimeException('SIFI_GOOGLE_HTTP_UNAVAILABLE');
                    }
                    $type = strtolower($response->getHeaderLine('Content-Type'));
                    if (! str_contains($type, 'xml') && ! str_contains($type, 'rss')) {
                        throw new \RuntimeException('SIFI_GOOGLE_CONTENT_TYPE_INVALID');
                    }
                    $encoding = strtolower($response->getHeaderLine('Content-Encoding'));
                    if ($encoding !== '' && $encoding !== 'identity') {
                        throw new \RuntimeException('SIFI_GOOGLE_ENCODING_INVALID');
                    }
                    $length = $response->getHeaderLine('Content-Length');
                    if ($length !== '' && (! ctype_digit($length) || (int) $length > 524288)) {
                        throw new \LengthException('SIFI_GOOGLE_RESPONSE_BOUND');
                    }
                },
            ];
            $requests = 1;
            $stage = 'HTTP_DISPATCH';
            $response = $this->request($options);
            $stage = 'RSS_PARSE';
            if ($response->status() !== 200 || $status !== 200 || (hrtime(true) - $start) / 1e9 > 22) {
                throw new \RuntimeException('SIFI_GOOGLE_RESPONSE_INVALID');
            }
            $sink->rewind();
            $body = $sink->getContents();
            $parsed = app(GoogleTrendsRssParser::class)->parse($body);

            return [
                'state' => 'OK', 'network_requests' => 1, 'http_status' => 200,
                'response_bytes' => strlen($body), 'response_sha256' => hash('sha256', $body),
                'entries' => $parsed['entries'], 'feed_item_count' => $parsed['feed_item_count'],
                'elapsed_ms' => (int) ((hrtime(true) - $start) / 1e6),
            ];
        } catch (\Throwable $error) {
            return [
                'state' => $status === 429 ? 'QUOTA' : ($status === 403 ? 'RESTRICTED' : 'UNAVAILABLE'),
                'network_requests' => $requests, 'http_status' => $status,
                'response_bytes' => 0, 'response_sha256' => null,
                'entries' => [], 'feed_item_count' => 0,
                'failure_stage' => $stage,
                'failure_category' => match ($status) {
                    403 => 'GOOGLE_RSS_ACCESS_RESTRICTED',
                    429 => 'PROVIDER_QUOTA',
                    default => $error instanceof \Illuminate\Http\Client\ConnectionException
                        ? 'CONNECTION_FAILURE' : 'VALIDATION_OR_HTTP_FAILURE',
                },
                'elapsed_ms' => (int) ((hrtime(true) - $start) / 1e6),
            ];
        }
    }
}
