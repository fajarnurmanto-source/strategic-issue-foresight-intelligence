<?php

namespace App\Services\Sifi;

use App\Models\SifiMonitoringObject;
use Illuminate\Validation\ValidationException;

/** Metadata planner, executing only through the existing AE13 matcher. */
final class DeterministicResearchPlanner
{
    private const STOP = ['apa', 'apakah', 'bagaimana', 'mana', 'siapa', 'kapan', 'mengapa', 'sumber', 'lokal', 'relevan', 'untuk', 'tentang', 'yang', 'dan', 'atau', 'dengan', 'di', 'ke', 'dari', 'the', 'what', 'which', 'how', 'about', 'and', 'for', 'of', 'in', 'to'];

    private function clean(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', mb_strtolower($value)) ?? '');
    }

    /** @return list<string> */
    private function terms(string $value): array
    {
        return array_slice(array_values(array_unique(array_filter(
            preg_split('/[^\p{L}\p{N}]+/u', $this->clean($value)) ?: [],
            fn ($x) => mb_strlen($x) >= 2 && ! in_array($x, self::STOP, true)
        ))), 0, 60);
    }

    private function contains(string $haystack, string $term): bool
    {
        return $term !== '' && (bool) preg_match('/(?<![\p{L}\p{N}])'.preg_quote($term, '/').'(?![\p{L}\p{N}])/u', $haystack);
    }

    /** @param array<string, mixed> $limits
     * @return array<string, mixed>
     */
    public function plan(SifiMonitoringObject $object, string $question, array $limits): array
    {
        $normalized = $this->clean($question);
        $lookup = $normalized;
        foreach ($object->exclusions_json as $term) {
            $lookup = preg_replace('/(?<![\p{L}\p{N}])'.preg_quote($this->clean($term), '/').'(?![\p{L}\p{N}])/u', ' ', $lookup) ?? '';
        }
        $terms = $this->terms($lookup);
        $queries = [];
        $add = function (string $query, string $intent) use (&$queries, $limits): void {
            $query = mb_substr($this->clean($query), 0, 200);
            if ($query !== '' && count($queries) < $limits['query_limit'] && ! in_array($query, array_column($queries, 'query'), true)) {
                $queries[] = ['query' => $query, 'intent' => $intent];
            }
        };
        if ($terms) {
            $base = implode(' ', $terms);
            $add($base, 'QUESTION_TERMS');
            foreach ([$object->name, ...$object->aliases_json, $object->objective] as $context) {
                $add($context.' '.$base, 'OBJECT_CONTEXT');
            }
        }

        return [
            'version' => 'ae14-plan-2', 'question_original' => $question,
            'question_normalized' => $normalized, 'question_terms' => $terms,
            'object' => $object->only(['id', 'revision', 'name', 'category', 'geography', 'objective', 'horizon']),
            'aliases' => $object->aliases_json,
            'inclusions' => array_slice(array_values(array_unique([...$terms, ...$object->keywords_json])), 0, 30),
            'exclusions' => $object->exclusions_json, 'queries' => $queries,
            'scope' => ['provider' => 'LOCAL_SYNTHETIC_CATALOG', 'language' => $limits['language'],
                'source_type' => $limits['source_type'], 'time_window' => $limits['time_window'],
                'time_filter_status' => 'NOT_APPLIED_UNKNOWN_PUBLICATION_DATES'],
            'limits' => $limits,
            'provenance' => ['planner' => 'DETERMINISTIC', 'ai' => 'OFF', 'network' => 'OFF',
                'catalog_version' => 'ae13-catalog-1',
                'catalog_sha256' => hash_file('sha256', resource_path('sifi/source-discovery-catalog.json'))],
        ];
    }

    /** @param array<string, mixed> $plan
     * @return array<string, mixed>
     */
    public function execute(SifiMonitoringObject $object, array $plan): array
    {
        $catalog = app(DiscoveryCatalog::class);
        $catalogHash = hash_file('sha256', resource_path('sifi/source-discovery-catalog.json'));
        if ($catalogHash === false || ! hash_equals($plan['provenance']['catalog_sha256'], $catalogHash)) {
            throw ValidationException::withMessages(['catalog' => 'Katalog berubah. Buat ulang rencana.']);
        }
        $started = hrtime(true);
        $results = [];
        $origins = [];
        $used = 0;
        $reason = 'PLAN_COMPLETED';
        foreach ($plan['queries'] as $index => $query) {
            if ((hrtime(true) - $started) / 1e6 >= $plan['limits']['time_limit_ms']) {
                $reason = 'TIME_LIMIT_REACHED';
                break;
            }
            if ($used >= $plan['limits']['query_limit']) {
                $reason = 'QUERY_LIMIT_REACHED';
                break;
            }
            $used++;
            $filters = array_filter(['q' => $query['query'], 'limit' => 20,
                'language' => $plan['scope']['language'], 'type' => $plan['scope']['source_type']], fn ($v) => $v !== null);
            foreach ($catalog->discoverSynthetic($object, $filters) as $entry) {
                $hay = $this->clean(implode(' ', [$entry['title'], implode(' ', $entry['topics']), $entry['geography'] ?? '']));
                // Expansion must not make an unrelated question appear answered.
                $hits = array_values(array_filter($plan['question_terms'], fn ($term) => $this->contains($hay, $term)));
                if (! $hits) {
                    continue;
                }
                $identity = hash('sha256', $catalog->safeUrl($entry['url'], true));
                if (isset($results[$identity])) {
                    $results[$identity]['matched_query_indexes'][] = $index;

                    continue;
                }
                if (count($results) >= $plan['limits']['result_limit']) {
                    $reason = 'RESULT_LIMIT_REACHED';
                    break 2;
                }
                $results[$identity] = $entry + ['candidate_identity_sha256' => $identity,
                    'matched_query_indexes' => [$index], 'question_hits' => $hits,
                    'retrieved_at' => null, 'admission' => 'NONE'];
                $origins[$entry['origin']] = true;
            }
        }
        if (! $results && $reason === 'PLAN_COMPLETED') {
            $reason = 'NO_LOCAL_MATCH';
        }
        $latestHash = hash_file('sha256', resource_path('sifi/source-discovery-catalog.json'));
        if ($latestHash === false || ! hash_equals($catalogHash, $latestHash)) {
            throw ValidationException::withMessages(['catalog' => 'Katalog berubah selama lookup.']);
        }

        return ['entries' => array_values($results), 'result_count' => count($results),
            'distinct_origin_count' => count($origins), 'origin_independence' => 'NOT_VERIFIED',
            'queries_used' => $used, 'requests_used' => 0, 'bytes_used' => 0, 'cost_used' => 0,
            'retrieved_documents' => 0, 'catalog_size' => count($catalog->entries()),
            'stopping_reason' => $reason, 'time_filter_applied' => false,
            'coverage' => 'Hanya katalog lokal SYNTHETIC .invalid; bukan open-web search. Jendela waktu belum dapat diterapkan karena tanggal publikasi UNKNOWN.',
            'uncertainty' => $results ? 'Metadata belum diverifikasi; origin berbeda belum membuktikan independensi. Hasil bukan fakta, dokumen, evidence, atau sitasi.' : 'Tidak ada kecocokan lokal tidak membuktikan tidak adanya informasi di internet.'];
    }
}
