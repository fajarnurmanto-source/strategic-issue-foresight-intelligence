<?php

namespace App\Services\Sifi;

use GuzzleHttp\Psr7\StreamDecoratorTrait;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\Process\Process;

/** Dormant live transport: exact server scope + consumed-once reservation before DNS. */
class OneShotSearchTransport
{
    /** @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function recover(string $provider, array $context): array
    {
        app(SearchProviderRegistry::class)->get($provider);
        $file = storage_path('app/private/sifi-search-runtime/'.app(OneShotSearchAuthority::class)->contextHash($provider, $context).'.receipt.enc');
        if (! is_file($file) || is_link($file) || filesize($file) > 131072) {
            throw new \RuntimeException('RECEIPT_MISSING');
        }$cipher = file_get_contents($file);
        if ($cipher === false) {
            throw new \RuntimeException('RECEIPT_MISSING');
        }$p = json_decode(Crypt::decryptString($cipher), true, 30, JSON_THROW_ON_ERROR);
        if (! is_array($p)) {
            throw new \RuntimeException('RECEIPT_MISSING');
        }
        if (($p['provider'] ?? null) !== $provider || ($p['query_sha256'] ?? null) !== ($context['query_sha256'] ?? null) || ($p['binding_sha256'] ?? null) !== hash('sha256', json_encode($context['binding'], JSON_THROW_ON_ERROR)) || strtotime($p['retention_until'] ?? '') <= now()->timestamp) {
            throw new \RuntimeException('RECEIPT_CONTEXT_OR_EXPIRY');
        }

return $p;
    }

    /** @return array<string, mixed> */
    public function failureDiagnostics(\Throwable $error, string $phase, ?int $status): array
    {
        $errno = null;
        for ($cause = $error; $cause; $cause = $cause->getPrevious()) {
            if (method_exists($cause, 'getHandlerContext')) {
                $v = $cause->getHandlerContext()['errno'] ?? null;
                if (is_int($v) && $v >= 0 && $v < 1000) {
                    $errno = $v;
                }
            }
        }
        $category = match (true) {
            $status === 429 => 'PROVIDER_QUOTA',$status !== null && $status >= 300 && $status < 400 => 'REDIRECT_BLOCKED',$status !== null && $status >= 400 => 'HTTP_FAILURE',$errno === 6 => 'DNS_FAILURE',$errno === 7 => 'CONNECT_FAILURE',$errno === 28 => 'TIMEOUT',in_array($errno, [35, 51, 58, 60, 77], true) => 'TLS_FAILURE',$error instanceof ConnectionException => 'CONNECTION_FAILURE',default => 'UNKNOWN'
        };

        return ['failure_stage' => $phase, 'failure_category' => $category, 'curl_errno' => $errno, 'exception_class' => get_class($error), 'raw_exception_message_retained' => false];
    }

    protected function address(string $host): string
    {
        $p = new Process(['/usr/bin/getent', 'ahostsv4', $host], null, ['PATH' => '/usr/bin:/bin']);
        $p->setTimeout(1);
        $p->run();
        if (! $p->isSuccessful()) {
            throw new \RuntimeException('DNS_POLICY');
        }$ips = [];
        foreach (explode("\n", trim($p->getOutput())) as $line) {
            $v = preg_split('/\s+/', $line);
            if ($v[0] ?? '') {
                $ips[] = $v[0];
            }
        }

        return (new ResearchWorldBankAcquisition)->publicAddress(array_values(array_unique($ips)));
    }

    /** @param array<string, mixed> $options */
    protected function request(string $endpoint, array $options): Response
    {
        $method = $options['_sifi_method'] ?? 'GET';
        unset($options['_sifi_method']);
        $gdelt = str_starts_with($endpoint, 'https://api.gdeltproject.org/api/v2/doc/doc?');

        return Http::withOptions($options)->connectTimeout($gdelt ? 12 : 1)->timeout($gdelt ? 25 : 3)->send($method, $endpoint);
    }

    /** @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function get(string $provider, array $context): array
    {
        $registry = app(SearchProviderRegistry::class);
        $p = $registry->get($provider);
        if (config('sifi.real_search_live') !== true) {
            throw new \RuntimeException('LIVE_SEARCH_LOCKED');
        }
        if (($context['mode'] ?? null) !== 'LIVE' || ($context['query_sha256'] ?? null) !== hash('sha256', $context['query'] ?? '')) {
            throw new \RuntimeException('QUERY_BINDING');
        }
        foreach (['owner_id', 'object_id', 'session_id', 'object_revision', 'plan_sha256'] as $key) {
            if (! isset($context['binding'][$key])) {
                throw new \RuntimeException('CONTEXT_REQUIRED');
            }
        }
        $parameters = $registry->parameters($provider, $context['query'], (int) ($context['limit'] ?? 5), $context);
        if (! extension_loaded('curl')) {
            throw new \RuntimeException('BOUNDED_CURL_REQUIRED');
        }
        // Keep a global provider lock across dispatch, preventing parallel calls and <=1/2sec.
        $dir = storage_path('app/private/sifi-search-runtime');
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }$lock = fopen($dir.'/'.$provider.'.lock', 'c+');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            throw new \RuntimeException('PROVIDER_BUSY');
        }
        try {
            $last = (float) stream_get_contents($lock);
            if (microtime(true) - $last < 2) {
                throw new \RuntimeException('LOCAL_RATE_LIMIT');
            }
            $permit = app(OneShotSearchAuthority::class)->consume($provider, $context);
            rewind($lock);
            ftruncate($lock, 0);
            fwrite($lock, (string) microtime(true));
            fflush($lock);
            $base = ['provider' => $provider, 'query' => $context['query'], 'query_sha256' => $context['query_sha256'], 'binding_sha256' => hash('sha256', json_encode($context['binding'], JSON_THROW_ON_ERROR)), 'endpoint' => $p['endpoint'], 'method' => $p['method'] ?? 'GET', 'mode' => 'LIVE', 'network_requests' => 0, 'cost' => 0, 'results' => [], 'retrieved_at' => null, 'fixture_loaded_at' => null, 'response_sha256' => null, 'response_bytes' => 0, 'retry_after_seconds' => null, 'approval_scope_sha256' => $permit['approval_scope_sha256']];
            $started = hrtime(true);
            $headers = [];
            $phase = 'DNS_RESOLUTION';
            $streamResource = fopen('php://temp/maxmemory:32768', 'w+b');
            if ($streamResource === false) {
                throw new \RuntimeException('SINK_OPEN_FAILED');
            }$sink = new class(Utils::streamFor($streamResource)) implements StreamInterface
            {
                use StreamDecoratorTrait;

                private StreamInterface $stream;

                public function __construct(StreamInterface $stream)
                {
                    $this->stream = $stream;
                }

                public function write($s): int
                {
                    if ($this->tell() + strlen($s) > 32768) {
                        throw new \LengthException('BYTE_LIMIT');
                    }

return $this->stream->write($s);
                }
            };
            try {
                $ip = $this->address($p['host']);
                $method = $p['method'] ?? 'GET';
                $endpoint = $p['endpoint'].($method === 'GET' ? '?'.http_build_query($parameters, '', '&', PHP_QUERY_RFC3986) : '');
                $base['network_requests'] = 1;
                $options = ['_curl_retries' => 2, 'transport_sharing' => 'none', 'version' => '1.1', 'curl' => [CURLOPT_FRESH_CONNECT => true, CURLOPT_FORBID_REUSE => true, CURLOPT_RESOLVE => [$p['host'].':443:'.$ip]], 'allow_redirects' => false, 'verify' => true, 'proxy' => '', 'decode_content' => false, 'http_errors' => false, 'sink' => $sink, 'headers' => ['Accept' => 'application/json', 'Accept-Encoding' => 'identity', 'User-Agent' => 'SIFI-Research/1.0 (mailto:'.$permit['contact_email'].')'], 'on_headers' => function ($r) use (&$headers) {
                    $headers = ['status' => $r->getStatusCode(), 'retry_after' => $r->getHeaderLine('Retry-After')];
                    if ($headers['status'] === 429) {
                        throw new \RuntimeException('QUOTA');
                    }if ($headers['status'] >= 300 && $headers['status'] < 400) {
                        throw new \RuntimeException('REDIRECT_BLOCKED');
                    }if ($headers['status'] !== 200) {
                        throw new \RuntimeException('UNAVAILABLE');
                    }if (! preg_match('~\Aapplication/json(?:\s*;\s*charset=utf-8)?\z~i', $r->getHeaderLine('Content-Type'))) {
                        throw new \RuntimeException('INVALID_RESPONSE');
                    }$encoding = $r->getHeaderLine('Content-Encoding');
                    if ($encoding !== '' && strtolower($encoding) !== 'identity') {
                        throw new \RuntimeException('INVALID_RESPONSE');
                    }$len = $r->getHeaderLine('Content-Length');
                    if ($len !== '' && (! ctype_digit($len) || (float) $len > 32768)) {
                        throw new \LengthException('BYTE_LIMIT');
                    }
                }];
                $options['_sifi_method'] = $method;
                if ($method === 'POST') {
                    $options['json'] = $parameters;
                    $options['headers']['X-Tavily-Access-Mode'] = 'keyless';
                    $options['headers']['Content-Type'] = 'application/json';
                }$phase = 'HTTP_DISPATCH';
                $response = $this->request($endpoint, $options);
                $phase = 'RESPONSE_VALIDATION';
                if ((hrtime(true) - $started) / 1e9 > ($provider === 'GDELT_DOC' ? 30 : 5)) {
                    throw new \RuntimeException('TIMEOUT');
                }if ($response->status() !== 200 || ! $headers) {
                    throw new \RuntimeException('INVALID_RESPONSE');
                }$sink->rewind();
                $raw = $sink->getContents();
                $body = json_decode($raw, true, 24, JSON_THROW_ON_ERROR);
                if (! is_array($body)) {
                    throw new \RuntimeException('INVALID_RESPONSE');
                }if ($provider === 'GDELT_DOC') {
                    if (! is_array($body['articles'] ?? null) || ! array_is_list($body['articles']) || count($body['articles']) > 5) {
                        throw new \RuntimeException('INVALID_GDELT_ITEMS');
                    }$body = ['articles' => array_map(static fn ($v) => is_array($v) ? array_intersect_key($v, array_flip(['url', 'title', 'seendate', 'domain', 'language', 'sourcecountry'])) : null, $body['articles'])];
                }if ($provider === 'CROSSREF' && is_array($body['message']['items'] ?? null)) {
                    $body['message']['items'] = array_map(static fn ($v) => is_array($v) ? array_intersect_key($v, array_flip(['DOI', 'title', 'publisher', 'published', 'URL', 'type'])) : $v, $body['message']['items']);
                } elseif ($provider === 'WIKIPEDIA_ID' && is_array($body['pages'] ?? null)) {
                    $body['pages'] = array_map(static fn ($v) => is_array($v) ? array_intersect_key($v, array_flip(['id', 'key', 'title', 'excerpt'])) : $v, $body['pages']);
                }if ($provider === 'TAVILY_KEYLESS') {
                    $body = ['query' => $body['query'] ?? null, 'results' => is_array($body['results'] ?? null) ? array_map(static fn ($v) => is_array($v) ? array_intersect_key($v, array_flip(['title', 'url', 'published_date', 'score'])) : $v, $body['results']) : null, 'request_id' => is_string($body['request_id'] ?? null) ? mb_substr($body['request_id'], 0, 100) : null];
                }$base['state'] = 'OK';
                $base['body'] = $body;
                $base['response_sha256'] = hash('sha256', $raw);
                $base['response_bytes'] = strlen($raw);
                $base['retrieved_at'] = now()->toIso8601String();
            } catch (\Throwable $e) {
                $base += $this->failureDiagnostics($e, $phase, $headers['status'] ?? null);
                $base['state'] = $base['failure_category'] === 'TIMEOUT' ? 'TIMEOUT' : 'UNAVAILABLE';
                for ($cause = $e; $cause; $cause = $cause->getPrevious()) {
                    if (in_array($cause->getMessage(), ['QUOTA', 'REDIRECT_BLOCKED', 'BYTE_LIMIT', 'TIMEOUT', 'DNS_POLICY', 'UNAVAILABLE'], true)) {
                        $base['state'] = $cause->getMessage();
                        break;
                    }
                }$base['body'] = null;
                if (ctype_digit($headers['retry_after'] ?? '')) {
                    $base['retry_after_seconds'] = min(3600, (int) $headers['retry_after']);
                }
            }
            $base['http_status'] = $headers['status'] ?? null;
            $base['dispatch_attempts'] = $base['network_requests'];
            $base['wire_delivery_confirmed'] = isset($headers['status']);
            $base['elapsed_ms'] = (int) ((hrtime(true) - $started) / 1e6);
            $base['retry_count'] = 0;
            $base['redirects_followed'] = 0;
            $base['retention_until'] = now()->addDays(30)->toIso8601String();
            // Failure receipts are written too. A consumed request is never retried during recovery.
            $receipt = $dir.'/'.app(OneShotSearchAuthority::class)->contextHash($provider, $context).'.receipt.enc';
            $encoded = Crypt::encryptString(json_encode($base, JSON_THROW_ON_ERROR));
            if (file_put_contents($receipt,$encoded,LOCK_EX) !== strlen($encoded)) {
                throw new \RuntimeException('RECEIPT_STORAGE_FAILED');
            }chmod($receipt,0600);

            return $base;
        } finally {
            flock($lock,LOCK_UN);
            fclose($lock);
        }
    }
}
