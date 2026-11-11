<?php

namespace App\Services\Sifi;

use App\Models\SifiMonitoringObject;
use App\Models\User;

/**
 * Read-only, owner-scoped projection of existing permitted live metadata receipts.
 * Supports legacy collector receipts and new reviewed-source envelopes without
 * adding network calls or promoting a publication to verified evidence.
 */
final class CollectorObservations
{
    /** @return list<array<string, mixed>> */
    public function forObject(User $user, SifiMonitoringObject $object): array
    {
        $out = [];

        foreach (app(MultiSourceResearchWorkspace::class)->history($user, $object) as $session) {
            $multi = $session['multi'] ?? null;
            $envelope = $multi['envelope'] ?? null;

            if (! is_array($envelope)
                || ($envelope['mode'] ?? null) !== 'APPROVED_LIVE_CORPUS_METADATA') {
                continue;
            }

            $providerIds = array_values(array_unique(array_filter(
                is_array($envelope['provider_ids'] ?? null) ? $envelope['provider_ids'] : [],
                static fn ($id): bool => is_string($id) && preg_match('/\A[A-Z0-9_]{2,64}\z/D', $id) === 1
            )));
            if ($providerIds === []) {
                $call = $envelope['calls'][0] ?? null;
                $id = is_array($call) ? ($call['provider'] ?? null) : null;
                if (is_string($id) && preg_match('/\A[A-Z0-9_]{2,64}\z/D', $id) === 1) {
                    $providerIds = [$id];
                }
            }

            $entries = [];
            $stored = is_array($envelope['entries'] ?? null) ? $envelope['entries'] : [];
            foreach (array_slice($stored, 0, 20) as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $title = $item['original_title'] ?? $item['title'] ?? null;
                $url = $item['canonical_url'] ?? $item['url'] ?? null;
                if (! is_string($title) || trim($title) === '' || mb_strlen($title) > 300
                    || ! is_string($url) || strlen($url) > 1000
                    || ! str_starts_with($url, 'https://')) {
                    continue;
                }
                $urlParts = parse_url($url);
                if (! is_array($urlParts) || empty($urlParts['host'])
                    || isset($urlParts['user']) || isset($urlParts['pass'])) {
                    continue;
                }

                $origin = $item['origin'] ?? null;
                if (! is_string($origin) || $origin === '') {
                    $declared = $item['origins'] ?? [];
                    $origin = is_array($declared) && is_string($declared[0] ?? null)
                        ? $declared[0] : 'UNKNOWN';
                }

                $entries[] = [
                    'id' => (string) ($item['id'] ?? hash('sha256', $url)),
                    'title' => $title,
                    'url' => $url,
                    'publisher' => $origin,
                    'published_at' => $item['published_at'] ?? null,
                    'date_precision' => $item['publication_precision'] ?? 'UNKNOWN',
                    'event_date' => $item['event_date'] ?? null,
                    'revision_state' => $item['revision_state'] ?? 'UNKNOWN',
                    'source_language' => $item['source_language'] ?? 'UNKNOWN',
                    'region' => $item['region'] ?? 'UNKNOWN',
                    'rights_state' => $item['rights_state'] ?? 'UNASSESSED',
                    'evidence_admission' => 'NONE',
                ];
            }

            $out[] = [
                'session_id' => $session['id'],
                'question' => $session['question'],
                'state' => $multi['state'] ?? 'DRAFT',
                'saved_at' => $multi['saved_at'] ?? null,
                'receipt' => $this->receipt($envelope),
                'entries' => $entries,
                'provider_ids' => $providerIds,
                'source_kind' => $this->sourceKind($providerIds),
                'coverage' => is_string($envelope['coverage'] ?? null)
                    ? mb_substr($envelope['coverage'], 0, 400) : 'COVERAGE_NOT_SPECIFIED',
                'envelope_sha256' => $multi['envelope_sha256'] ?? null,
                'independent_origin_state' => 'NOT_VERIFIED',
                'claim_verification' => 'NOT_PERFORMED_METADATA_ONLY',
                'evidence_admission' => 'NONE',
                'filtered_count' => max(0, (int) ($envelope['filtered_by_relevance_or_period'] ?? 0)),
            ];

            if (count($out) >= 5) {
                break;
            }
        }

        return $out;
    }

    /** @param list<string> $ids */
    private function sourceKind(array $ids): string
    {
        if (count($ids) !== 1) {
            return 'MULTI_OR_UNKNOWN_PUBLIC_METADATA';
        }

        return match ($ids[0]) {
            'CROSSREF' => 'SCHOLARLY_BIBLIOGRAPHIC_METADATA',
            'ANTARA_RSS' => 'PUBLISHER_RSS_HEADLINES',
            'GDELT_DOC' => 'NEWS_LINK_METADATA',
            default => 'OTHER_REVIEWED_PUBLIC_METADATA',
        };
    }

    /** @param array<string, mixed> $envelope
     * @return array<string, mixed>
     */
    private function receipt(array $envelope): array
    {
        $legacy = is_array($envelope['collector'] ?? null) ? $envelope['collector'] : [];
        $calls = is_array($envelope['calls'] ?? null) ? $envelope['calls'] : [];
        $call = is_array($calls[0] ?? null) ? $calls[0] : [];
        $source = $legacy !== [] ? $legacy : $call;

        $sha = $source['response_sha256'] ?? null;
        $sha = is_string($sha) && preg_match('/\A[a-f0-9]{64}\z/D', $sha) === 1 ? $sha : null;
        $bytes = max(0, (int) ($source['response_bytes'] ?? 0));
        $requestCount = (int) ($source['network_requests']
            ?? $envelope['usage']['network_requests'] ?? 0);
        $wireConfirmed = is_bool($source['wire_delivery_confirmed'] ?? null)
            ? $source['wire_delivery_confirmed']
            : ($requestCount > 0 && ($source['state'] ?? null) === 'OK'
                && $bytes > 0 && $sha !== null);

        $retrievedAt = $source['retrieved_at'] ?? null;
        if (! is_string($retrievedAt)) {
            $first = $envelope['entries'][0] ?? null;
            $retrievedAt = is_array($first) && is_string($first['retrieved_at'] ?? null)
                ? $first['retrieved_at'] : null;
        }

        return [
            'http_status' => is_int($source['http_status'] ?? null)
                ? $source['http_status'] : null,
            'wire_delivery_confirmed' => $wireConfirmed,
            'retrieved_at' => $retrievedAt,
            'response_sha256' => $sha,
            'response_bytes' => $bytes,
            'failure_category' => is_string($source['failure_category'] ?? null)
                ? $source['failure_category'] : null,
        ];
    }
}
