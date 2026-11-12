<?php

namespace App\Services\Sifi;

use App\Contracts\SifiResearchProvider;

/** Actual parser over bounded fixture bytes; no transport or network-capable dependency. */
final class FeedResearchProvider implements SifiResearchProvider
{
    public function __construct(private readonly string $sourceId) {}

    public function search(array $context): array
    {
        $pack = app(FeedSourcePack::class);
        $source = $pack->source($this->sourceId);
        $page = (int) ($context['page'] ?? 1);
        $state = $context['fixture_state'] ?? 'OK';
        if (! in_array($state, ['OK', 'UNAVAILABLE', 'QUOTA', 'TIMEOUT'], true)) {
            throw new \RuntimeException('STATE_BOUND');
        }
        $base = ['version' => 'sifi-feed-provider-1', 'provider' => $source['id'], 'query_sha256' => $context['query_sha256'], 'page' => $page, 'source' => $source, 'state' => $state, 'network_requests' => 0, 'cost' => 0, 'fixture_loaded_at' => now()->toIso8601String(), 'retrieved_at' => null, 'raw_bytes' => 0, 'results' => [], 'next' => null];
        if ($state !== 'OK') {
            return $base;
        }
        $body = $pack->bytes($source['id'], $page, $context['object_name']);
        $parsed = app(BoundedFeedParser::class)->parse($body, $source['format']);
        if ($parsed['next'] !== null && ($page !== 1 || $parsed['next'] !== $source['endpoint'].'?page=2')) {
            throw new \RuntimeException('CONTINUATION_ENDPOINT_REJECTED');
        }

        return array_replace($base, ['results' => $parsed['items'], 'body_sha256' => $parsed['body_sha256'], 'raw_bytes' => strlen($body), 'next' => $parsed['next'], 'parser_version' => $parsed['version']]);
    }
}
