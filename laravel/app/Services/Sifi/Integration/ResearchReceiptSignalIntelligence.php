<?php

namespace App\Services\Sifi\Integration;

/**
 * IH03C: deterministic, read-only intelligence from a bounded research receipt.
 * Similar titles are topic candidates, NEVER independent corroboration or truth.
 */
final class ResearchReceiptSignalIntelligence
{
    private const STOP_WORDS = [
        'a', 'an', 'and', 'are', 'as', 'at', 'based', 'by', 'case', 'cases',
        'for', 'from', 'in', 'into', 'is', 'of', 'on', 'or', 'research',
        'study', 'the', 'their', 'through', 'to', 'using', 'with',
        'dan', 'dari', 'dalam', 'di', 'ini', 'itu', 'pada', 'yang',
    ];

    /**
     * @param  array<string, mixed>  $receipt
     * @return array<string, mixed>
     */
    public function project(array $receipt, int $limit = 10): array
    {
        if (($receipt['mode'] ?? null) !== 'APPROVED_LIVE_CORPUS_METADATA') {
            throw new \InvalidArgumentException('IH03C_LIVE_METADATA_RECEIPT_REQUIRED');
        }
        $entries = $receipt['entries'] ?? null;
        $calls = $receipt['calls'] ?? null;
        if (! is_array($entries) || ! array_is_list($entries) || count($entries) > 1000
            || ! is_array($calls) || ! array_is_list($calls) || count($calls) > 16
            || $limit < 1 || $limit > 20) {
            throw new \InvalidArgumentException('IH03C_RECEIPT_BOUNDS');
        }

        $items = [];
        $urlIndex = [];
        $termCounts = [];
        foreach ($entries as $item) {
            if (! is_array($item) || ! is_string($item['id'] ?? null)
                || preg_match('/\A[a-f0-9]{64}\z/D', $item['id']) !== 1
                || ! is_string($item['title'] ?? null)
                || trim($item['title']) === '' || mb_strlen($item['title']) > 300
                || ! is_string($item['canonical_url'] ?? null)
                || strlen($item['canonical_url']) > 1000
                || ! str_starts_with($item['canonical_url'], 'https://doi.org/')
                || hash('sha256', $item['canonical_url']) !== $item['id']
                || ($item['evidence_admission'] ?? null) !== 'NONE') {
                throw new \InvalidArgumentException('IH03C_INVALID_METADATA_ENTRY');
            }
            if (isset($urlIndex[$item['canonical_url']])) {
                continue;
            }
            $urlIndex[$item['canonical_url']] = true;
            $tokens = $this->terms($item['title']);
            foreach ($tokens as $term) {
                $termCounts[$term] = ($termCounts[$term] ?? 0) + 1;
            }
            $lineage = $item['lineage'] ?? [];
            $providers = [];
            if (is_array($lineage)) {
                foreach (array_slice($lineage, 0, 100) as $hit) {
                    $provider = is_array($hit) ? ($hit['provider'] ?? null) : null;
                    if (is_string($provider) && strlen($provider) <= 64) {
                        $providers[$provider] = true;
                    }
                }
            }
            $items[] = [
                'id' => $item['id'],
                'title' => $item['title'],
                'doi_url' => $item['canonical_url'],
                'published_at' => $item['published_at'] ?? null,
                'publication_precision' => $item['publication_precision'] ?? 'UNKNOWN',
                'rights_state' => $item['rights_state'] ?? 'UNASSESSED',
                'provider_ids' => array_keys($providers),
                'relevance_points' => (int) ($item['relevance']['score'] ?? 0),
                'terms' => $tokens,
            ];
        }

        $adjacency = array_fill(0, count($items), []);
        for ($i = 0; $i < count($items); $i++) {
            for ($j = $i + 1; $j < count($items); $j++) {
                $overlap = count(array_intersect($items[$i]['terms'], $items[$j]['terms']));
                $union = count(array_unique([...$items[$i]['terms'], ...$items[$j]['terms']]));
                if ($overlap >= 2 && $union > 0 && $overlap / $union >= 0.35) {
                    $adjacency[$i][] = $j;
                    $adjacency[$j][] = $i;
                }
            }
        }

        $visited = [];
        $clusters = [];
        for ($i = 0; $i < count($items); $i++) {
            if (isset($visited[$i])) {
                continue;
            }
            $queue = [$i];
            $visited[$i] = true;
            $group = [];
            while ($queue) {
                $index = array_shift($queue);
                $group[] = $index;
                foreach ($adjacency[$index] as $neighbor) {
                    if (! isset($visited[$neighbor])) {
                        $visited[$neighbor] = true;
                        $queue[] = $neighbor;
                    }
                }
            }
            if (count($group) > 1) {
                $ids = array_map(fn (int $k): string => $items[$k]['id'], $group);
                sort($ids, SORT_STRING);
                $clusters[] = [
                    'cluster_key' => hash('sha256', implode('|', $ids)),
                    'item_ids' => $ids,
                    'item_count' => count($ids),
                    'classification' => 'LEXICAL_TOPIC_CANDIDATE_UNVERIFIED',
                    'independent_origins' => null,
                ];
            }
        }

        uksort($termCounts, fn (string $a, string $b): int => ($termCounts[$b] <=> $termCounts[$a]) ?: strcmp($a, $b));
        usort($items, fn (array $a, array $b): int => ($b['relevance_points'] <=> $a['relevance_points']) ?: strcmp($a['id'], $b['id']));

        $callStates = [];
        foreach ($calls as $call) {
            if (! is_array($call)) {
                throw new \InvalidArgumentException('IH03C_INVALID_CALL');
            }
            $callStates[] = [
                'provider' => $call['provider'] ?? 'UNKNOWN',
                'state' => $call['state'] ?? 'UNKNOWN',
                'network_requests' => (int) ($call['network_requests'] ?? 0),
            ];
        }

        $top = array_slice($items, 0, $limit);
        foreach ($top as &$item) {
            unset($item['terms']);
        }
        unset($item);

        return [
            'schema_version' => 'sifi.ih03c.shadow-signal-intelligence.v1',
            'input' => 'REVIEWED_LIVE_BIBLIOGRAPHIC_METADATA',
            'provider_calls' => $callStates,
            'network_requests' => array_sum(array_column($callStates, 'network_requests')),
            'unique_publications' => count($items),
            'duplicate_canonical_urls_within_receipt' => count($entries) - count($items),
            'recurring_title_terms' => array_slice($termCounts, 0, 10, true),
            'lexical_topic_clusters' => $clusters,
            'lexical_topic_cluster_count' => count($clusters),
            'top_publications' => $top,
            'limitations' => [
                'title_similarity_is_not_copying_or_corroboration',
                'publication_count_is_not_independent_origin_count',
                'research_attention_is_not_event_frequency_or_prevalence',
                'single_snapshot_cannot_establish_velocity_or_foresight',
                'bibliographic_metadata_is_not_verified_primary_evidence',
            ],
            'claim_confidence' => null,
            'independent_origin_count' => null,
            'event_frequency' => null,
            'prevalence' => null,
            'impact_severity' => null,
            'trajectory' => 'NOT_ASSESSABLE_SINGLE_SNAPSHOT',
            'ai' => 'OFF',
            'evidence_admission' => 'NONE',
            'production_admission' => 'DENIED',
        ];
    }

    /** @return list<string> */
    private function terms(string $title): array
    {
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($title)) ?: [];
        $words = array_values(array_unique(array_filter($tokens,
            fn (string $word): bool => mb_strlen($word) >= 3
                && ! in_array($word, self::STOP_WORDS, true)
        )));
        sort($words, SORT_STRING);

        return $words;
    }
}
