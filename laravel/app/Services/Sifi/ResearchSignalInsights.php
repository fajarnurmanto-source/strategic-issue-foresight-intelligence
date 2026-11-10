<?php

namespace App\Services\Sifi;

/**
 * Deterministic, network-free intelligence projection for existing research receipts.
 * Candidate signals are not verified events, independent origins, or admitted evidence.
 */
final class ResearchSignalInsights
{
    public function summarize(array $envelope): array
    {
        $entries = $envelope['entries'] ?? null;
        if (! is_array($entries) || ! array_is_list($entries) || count($entries) > 1000) {
            throw new \InvalidArgumentException('RESEARCH_INSIGHTS_INPUT_BOUND');
        }

        $declaredOrigins = [];
        $providerHits = [];
        $fingerprints = [];
        $unknownPublication = 0;
        $unassessedRights = 0;
        $withoutPublisher = 0;

        foreach ($entries as $entry) {
            if (! is_array($entry) || ! is_string($entry['id'] ?? null)
                || preg_match('/\A[a-f0-9]{64}\z/D', $entry['id']) !== 1) {
                throw new \InvalidArgumentException('RESEARCH_INSIGHTS_ENTRY_INVALID');
            }

            if (! is_string($entry['published_at'] ?? null) || trim($entry['published_at']) === '') {
                $unknownPublication++;
            }
            $rights = strtoupper((string) ($entry['rights_state'] ?? 'UNASSESSED'));
            if ($rights === '' || str_contains($rights, 'UNASSESSED') || $rights === 'UNKNOWN') {
                $unassessedRights++;
            }
            if (! is_string($entry['publisher'] ?? null) || trim($entry['publisher']) === '') {
                $withoutPublisher++;
            }

            foreach (($entry['origins'] ?? []) as $origin) {
                if (is_string($origin) && trim($origin) !== '') {
                    $declaredOrigins[mb_strtolower(trim($origin))] = true;
                }
            }
            foreach (($entry['lineage'] ?? []) as $hit) {
                if (is_array($hit) && is_string($hit['provider'] ?? null)) {
                    $providerHits[$hit['provider']] = ($providerHits[$hit['provider']] ?? 0) + 1;
                }
            }

            // Same nonempty snippet across separate canonical URLs is a *candidate* repost.
            // The hash alone does not establish copying, coordination, or origin independence.
            $fingerprint = $entry['snippet_sha256'] ?? null;
            $snippet = $entry['snippet'] ?? null;
            $url = $entry['canonical_url'] ?? null;
            if (is_string($fingerprint) && preg_match('/\A[a-f0-9]{64}\z/D', $fingerprint) === 1
                && is_string($snippet) && trim($snippet) !== '' && is_string($url)
                && preg_match('#\Ahttps://#D', $url) === 1) {
                $fingerprints[$fingerprint][$url][] = $entry['id'];
            }
        }

        $republication = [];
        foreach ($fingerprints as $hash => $byUrl) {
            if (count($byUrl) < 2) {
                continue;
            }
            $ids = array_values(array_unique(array_merge(...array_values($byUrl))));
            sort($ids, SORT_STRING);
            $republication[] = [
                'fingerprint_sha256' => $hash,
                'item_ids' => array_slice($ids, 0, 20),
                'distinct_url_count' => count($byUrl),
                'classification' => 'POSSIBLE_REPUBLICATION_UNVERIFIED',
            ];
        }
        usort($republication, fn (array $a, array $b): int => strcmp($a['fingerprint_sha256'], $b['fingerprint_sha256']));
        ksort($providerHits, SORT_STRING);

        return [
            'schema_version' => 'sifi.research.insights.v1',
            'mode' => 'DETERMINISTIC_METADATA_SHADOW',
            'candidate_item_count' => count($entries),
            'raw_hit_count' => max(count($entries), (int) ($envelope['raw_result_count'] ?? count($entries))),
            'declared_origin_label_count' => count($declaredOrigins),
            'independent_origin_count' => null,
            'independence_verification' => 'NOT_VERIFIED',
            'provider_hit_counts' => $providerHits,
            'possible_republication_clusters' => array_slice($republication, 0, 12),
            'possible_republication_cluster_count' => count($republication),
            'unknown_publication_date_count' => $unknownPublication,
            'rights_unassessed_count' => $unassessedRights,
            'publisher_not_declared_count' => $withoutPublisher,
            'claim_confidence' => null,
            'claim_confidence_state' => 'INSUFFICIENT_PRIMARY_EVIDENCE',
            'engagement_normalized_velocity' => null,
            'cross_platform_propagation' => 'INSUFFICIENT_PLATFORM_OBSERVATIONS',
            'amplifier_roles' => 'NOT_CLASSIFIED',
            'event_frequency' => null,
            'prevalence' => null,
            'impact_severity' => null,
            'probabilistic_foresight' => 'NOT_COMPUTABLE_FROM_METADATA_ONLY',
            'rights_grant' => false,
            'evidence_admission' => 'NONE',
            'production_admission' => 'DENIED',
        ];
    }
}
