<?php

namespace App\Services\Sifi\Integration;

use App\Services\Sifi\ResearchWorldBankAcquisition;
use GuzzleHttp\Psr7\StreamDecoratorTrait;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\Process\Process;

/** One fixed publisher RSS GET; no redirects, dynamic URLs, bodies or retries. */
class AntaraRssTransport
{
    public const ENDPOINT = 'https://www.antaranews.com/rss/terkini.xml';

    protected function address(): string
    {
        $process = new Process(['/usr/bin/getent', 'ahostsv4', 'www.antaranews.com'], null,
            ['PATH' => '/usr/bin:/bin']);
        $process->setTimeout(3);
        $process->run();
        if (! $process->isSuccessful()) {
            throw new \RuntimeException('DNS_POLICY');
        }
        $ips = [];
        foreach (explode("\n", trim($process->getOutput())) as $line) {
            $fields = preg_split('/\s+/', $line);
            if (($fields[0] ?? '') !== '') {
                $ips[] = $fields[0];
            }
        }

        return app(ResearchWorldBankAcquisition::class)->publicAddress(array_values(array_unique($ips)));
    }

    /** @param array<string, mixed> $options */
    protected function request(array $options): Response
    {
        return Http::withOptions($options)->connectTimeout(8)->timeout(18)->get(self::ENDPOINT);
    }

    /** @return array<string, mixed> */
    public function fetch(string $contact): array
    {
        app(AntaraLocalPermission::class)->assertAllowed();
        app(AntaraLocalPermission::class)->assertContact($contact);
        $streamResource = fopen('php://temp/maxmemory:131072', 'w+b');
        if ($streamResource === false) {
            throw new \RuntimeException('IH03E_RSS_SINK_OPEN_FAILED');
        }
        $sink = new class(Utils::streamFor($streamResource)) implements StreamInterface
        {
            use StreamDecoratorTrait;

            private StreamInterface $stream;

            public function __construct(StreamInterface $stream)
            {
                $this->stream = $stream;
            }

            public function write($data): int
            {
                if ($this->tell() + strlen($data) > 131072) {
                    throw new \LengthException('IH03E_RSS_BYTE_LIMIT');
                }

                return $this->stream->write($data);
            }
        };
        $status = null;
        $requests = 0;
        $begin = hrtime(true);
        $phase = 'DNS_RESOLUTION';
        try {
            if (! extension_loaded('curl')) {
                throw new \RuntimeException('BOUNDED_CURL_REQUIRED');
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
                    CURLOPT_RESOLVE => ['www.antaranews.com:443:'.$ip],
                ],
                'sink' => $sink,
                'headers' => [
                    'Accept' => 'application/rss+xml, application/xml, text/xml',
                    'Accept-Encoding' => 'identity',
                    'User-Agent' => 'SIFI-Metadata-Research/1.0 (mailto:'.$contact.')',
                ],
                'on_headers' => static function ($response) use (&$status): void {
                    $status = $response->getStatusCode();
                    if ($status !== 200) {
                        throw new \RuntimeException($status === 429 ? 'PROVIDER_QUOTA' : 'HTTP_UNAVAILABLE');
                    }
                    $type = strtolower($response->getHeaderLine('Content-Type'));
                    if (! str_contains($type, 'xml')) {
                        throw new \RuntimeException('RSS_CONTENT_TYPE');
                    }
                    $encoding = strtolower($response->getHeaderLine('Content-Encoding'));
                    if ($encoding !== '' && $encoding !== 'identity') {
                        throw new \RuntimeException('RSS_CONTENT_ENCODING');
                    }
                    $length = $response->getHeaderLine('Content-Length');
                    if ($length !== '' && (! ctype_digit($length) || (int) $length > 131072)) {
                        throw new \LengthException('IH03E_RSS_BYTE_LIMIT');
                    }
                },
            ];
            $requests = 1;
            $phase = 'HTTP_DISPATCH';
            $response = $this->request($options);
            $phase = 'RSS_VALIDATION';
            if ($response->status() !== 200 || $status !== 200
                || (hrtime(true) - $begin) / 1e9 > 22) {
                throw new \RuntimeException('RSS_RESPONSE_INVALID');
            }
            $sink->rewind();
            $bytes = $sink->getContents();
            $entries = app(AntaraRssMetadataParser::class)->parse($bytes);

            return [
                'state' => 'OK', 'network_requests' => 1,
                'http_status' => 200, 'response_bytes' => strlen($bytes),
                'response_sha256' => hash('sha256', $bytes),
                'entries' => $entries,
                'elapsed_ms' => (int) ((hrtime(true) - $begin) / 1e6),
            ];
        } catch (\Throwable $error) {
            return [
                'state' => $status === 429 ? 'QUOTA' : 'UNAVAILABLE',
                'network_requests' => $requests,
                'http_status' => $status,
                'response_bytes' => 0,
                'response_sha256' => null,
                'entries' => [],
                'failure_stage' => $phase,
                'failure_category' => $status === 429 ? 'PROVIDER_QUOTA'
                    : ($error instanceof ConnectionException
                        ? 'CONNECTION_FAILURE' : 'VALIDATION_OR_HTTP_FAILURE'),
                'elapsed_ms' => (int) ((hrtime(true) - $begin) / 1e6),
            ];
        }
    }
}
