<?php

namespace App\Services\Sifi;

use App\Contracts\SifiResearchProvider;
use Illuminate\Http\Client\Factory;
use Illuminate\Validation\ValidationException;

/** Dedicated native HTTP factory, fake handler only. No facade/network fallback. */
final class FixtureResearchProvider implements SifiResearchProvider
{
    public const ENDPOINT = 'https://search.sources.invalid/fixture';

    /**
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    public function search(array $context): array
    {
        $response = ['version' => 'ae14-search-1', 'provider' => 'LOCAL_SYNTHETIC_HTTP_FAKE',
            'query_sha256' => $context['query_sha256'], 'state' => 'OK', 'results' => $context['entries']];

        return $this->fromFixtureResponse($context, json_encode($response, JSON_THROW_ON_ERROR));
    }

    /** Test seam accepts fixture bytes, never an endpoint/credential from the client. */
    /**
     * @param  array<string,mixed>  $context
     * @param  array<string,string>  $headers
     * @return array<string,mixed>
     */
    public function fromFixtureResponse(array $context, string $body, int $status = 200, array $headers = []): array
    {
        $http = new Factory;
        $http->preventStrayRequests();
        $http->fake([self::ENDPOINT => $http->response($body, $status, $headers + ['Content-Type' => 'application/json'])]);
        $response = $http->acceptJson()->setHandler(fn () => throw new \RuntimeException('FIXTURE_NETWORK_FALLBACK_FORBIDDEN'))->withoutRedirecting()->connectTimeout(1)->timeout(2)
            ->withOptions(['decode_content' => false])->post(self::ENDPOINT, ['query_sha256' => $context['query_sha256']]);
        if ($status === 429) {
            return $this->empty($context, 'QUOTA');
        }
        if ($status !== 200) {
            return $this->empty($context, 'UNAVAILABLE');
        }
        if (strlen($response->body()) > 65536 || $response->header('Content-Encoding') || ! str_starts_with($response->header('Content-Type'), 'application/json')) {
            $this->fail('RESPONSE_LIMIT_OR_FORMAT');
        }
        try {
            $packet = json_decode($response->body(), true, 32, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            $this->fail('SCHEMA');
        }
        if (! is_array($packet) || ($packet['version'] ?? null) !== 'ae14-search-1' || ($packet['provider'] ?? null) !== 'LOCAL_SYNTHETIC_HTTP_FAKE' || ($packet['query_sha256'] ?? null) !== $context['query_sha256']) {
            $this->fail('PROVENANCE');
        }
        if (! in_array($packet['state'] ?? null, ['OK', 'QUOTA', 'TIMEOUT', 'UNAVAILABLE'], true)) {
            $this->fail('STATE');
        }
        if (! is_array($packet['results'] ?? null) || ! array_is_list($packet['results']) || count($packet['results']) > 20) {
            $this->fail('RESULT_LIMIT');
        }
        if ($packet['state'] !== 'OK') {
            if ($packet['results']) {
                $this->fail('ERROR_WITH_RESULTS');
            }

            return $this->empty($context, $packet['state']);
        }
        $seen = [];
        $origins = [];
        foreach ($packet['results'] as $entry) {
            if (! is_array($entry)) {
                $this->fail('SCHEMA');
            }
            app(DiscoveryCatalog::class)->assertDescriptor($entry);
            if (! str_ends_with((string) (parse_url($entry['url'], PHP_URL_HOST) ?? ''), '.sources.invalid')) {
                $this->fail('FIXTURE_URL_ONLY');
            }
            foreach (['title' => 250, 'snippet' => 1000, 'origin' => 100, 'key' => 80] as $field => $limit) {
                if (! is_string($entry[$field] ?? null) || strlen($entry[$field]) > $limit) {
                    $this->fail('FIELD_LIMIT');
                }
            }
            $url = app(DiscoveryCatalog::class)->safeUrl($entry['url'], true);
            $identity = hash('sha256', $url);
            if (isset($seen[$identity]) && $seen[$identity]['origin'] !== $entry['origin']) {
                $this->fail('AMBIGUOUS_ORIGIN');
            }
            $seen[$identity] = $entry + ['result_identity' => $identity, 'retrieved_at' => null];
            $origins[$entry['origin']] = true;
        }

        return array_replace($this->empty($context, 'OK'), ['results' => array_values($seen), 'distinct_origins' => count($origins)]);
    }

    /**
     * @param  array<string,mixed>  $c
     * @return array<string,mixed>
     */
    private function empty(array $c, string $state): array
    {
        return ['version' => 'ae14-search-1', 'provider' => 'LOCAL_SYNTHETIC_HTTP_FAKE', 'query_sha256' => $c['query_sha256'], 'authorization' => 'SELF_AUTHORED_FIXTURE_ONLY', 'state' => $state, 'results' => [], 'distinct_origins' => 0, 'origin_independence' => 'NOT_VERIFIED', 'network_requests' => 0, 'fake_http_exchanges' => 1, 'documents_retrieved' => 0, 'cost' => 0, 'fallback' => 'NONE'];
    }

    private function fail(string $reason): never
    {
        throw ValidationException::withMessages(['provider' => $reason]);
    }
}
