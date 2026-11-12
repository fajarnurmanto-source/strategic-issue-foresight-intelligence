<?php

namespace App\Services\Sifi;

use App\Models\SifiMonitoringObject;

final class FeedDiscovery
{
    /**
     * @param  array<string,mixed>  $plan
     * @param  array<string,mixed>  $binding
     * @return array<string,mixed>
     */
    public function execute(SifiMonitoringObject $object, array $plan, array $binding): array
    {
        $p = $plan['feed'];
        $mp = $p['query_plan'];
        $options = $p['options'];
        $pack = app(FeedSourcePack::class);
        $w = app(MultiSourceResearchWorkspace::class);
        $started = hrtime(true); /** @var array<string,array{id:string,title:string,snippet:string,snippet_sha256:string,relevance:array{score:int},origins:list<string>,family:?string,canonical_url:string,revision_state:string}&array<string,mixed>> $entries */
        $entries = [];
        $byUrl = [];
        $calls = [];
        $raw = 0;
        $duplicates = 0;
        $used = 0;
        $filtered = ['object' => 0, 'language' => 0, 'exclusions' => 0, 'region' => 0, 'period' => 0];
        $stop = 'COMPLETED';
        $limit = ['query_limit' => 1, 'provider_limit' => 3, 'simulated_call_limit' => 6, 'result_limit' => 12, 'metadata_byte_limit' => 65536, 'page_byte_limit' => 32768, 'time_limit_ms' => 2000, 'max_pages_per_source' => $options['pages'], 'network_request_limit' => 0, 'cost_limit' => 0];
        foreach ($options['sources'] as $sourceId) {
            $source = $pack->source($sourceId);
            $provider = new FeedResearchProvider($sourceId);
            for ($page = 1; $page <= min($source['max_pages'], $options['pages']); $page++) {
                if (count($calls) >= 6) {
                    $stop = 'SIMULATED_CALL_LIMIT';
                    break 2;
                }if ((hrtime(true) - $started) / 1e6 >= 2000) {
                    $stop = 'TIME_LIMIT';
                    break 2;
                }
                $state = match ($options['provider_state']) {
                    'partial' => $sourceId === 'SYNTHETIC_ATOM' ? 'UNAVAILABLE' : 'OK','quota' => 'QUOTA','timeout' => 'TIMEOUT',default => 'OK'
                };
                $context = ['query_sha256' => $w->digest($mp), 'object_name' => $object->name, 'page' => $page, 'fixture_state' => $state];
                try {
                    $packet = $provider->search($context);
                    $error = null;
                } catch (\Throwable) {
                    $packet = ['state' => 'PARSER_REJECTED', 'results' => [], 'next' => null, 'raw_bytes' => 0];
                    $error = 'PARSER_OR_CONTINUATION_REJECTED';
                }
                if ($used + $packet['raw_bytes'] > 65536) {
                    $stop = 'METADATA_BYTE_LIMIT';
                    break 2;
                }$used += $packet['raw_bytes'];
                $calls[] = ['provider' => $sourceId, 'query_index' => 0, 'query' => $mp['question_original'], 'state' => $packet['state'], 'page' => $page, 'response_sha256' => $w->digest($packet), 'body_sha256' => $packet['body_sha256'] ?? null, 'raw_bytes' => $packet['raw_bytes'], 'fixture_loaded_at' => $packet['fixture_loaded_at'] ?? null, 'retrieved_at' => null, 'simulated_call' => true, 'error' => $error, 'next_available' => $packet['next'] !== null, 'continuation_followed' => $packet['next'] !== null && $page < $options['pages'], 'endpoint' => $source['endpoint'].($page > 1 ? '?page='.$page : ''), 'rights_state' => 'UNASSESSED', 'collector_state' => 'OFF'];
                if ($packet['state'] !== 'OK') {
                    break;
                }
                foreach ($packet['results'] as $item) {
                    $raw++;
                    $canonical = $w->canonical($item['url']);
                    if ($item['origin_url'] !== null) {
                        $w->canonical($item['origin_url']);
                    }
                    $hay = mb_strtolower($item['title'].' '.$item['snippet']);
                    $entities = array_values(array_filter(array_map(fn ($e) => trim(preg_replace('/\[?synthetic\]?/iu', '', $e['text'])), $mp['entities']), fn ($e) => mb_strlen($e) >= 3));
                    if ($options['provider_state'] === 'no_result' || ! array_filter($entities, fn ($e) => mb_stripos($hay, $e) !== false)) {
                        $filtered['object']++;

                        continue;
                    }
                    if (array_filter($mp['exclusions'], fn ($x) => $x !== '' && mb_stripos($hay, $x) !== false)) {
                        $filtered['exclusions']++;

                        continue;
                    }
                    $declared = strtolower(explode('-', $item['declared_language'])[0]);
                    if (! preg_match('/\A[a-z]{2,3}\z/', $declared)) {
                        $declared = 'UNKNOWN';
                    }
                    $detection = app(MultilingualQueryPlan::class)->detect($item['title'].' '.$item['snippet']);
                    $lang = $declared !== 'UNKNOWN' ? $declared : $detection['code'];
                    if ($options['language'] !== 'ALL' && $options['language'] !== $lang) {
                        $filtered['language']++;

                        continue;
                    }
                    if ($options['region'] !== 'ALL' && $options['region'] !== $item['region']) {
                        $filtered['region']++;

                        continue;
                    }
                    $start = $mp['window']['start'];
                    $end = $mp['window']['end_exclusive'];
                    $pub = $item['published_at'];
                    $time = $start === null ? 'WINDOW_UNSPECIFIED' : ($pub === null ? 'PUBLICATION_UNKNOWN' : ($pub >= $start && $pub < $end ? 'PUBLISHED_IN_WINDOW' : 'OUTSIDE_WINDOW'));
                    if (($options['period'] === 'PUBLISHED' && $time !== 'PUBLISHED_IN_WINDOW') || ($options['period'] === 'UNKNOWN' && $pub !== null)) {
                        $filtered['period']++;

                        continue;
                    }
                    $logical = $w->digest([$canonical, $item['title'], $item['snippet'], $item['updated_at']]);
                    $id = hash('sha256', $canonical.'|'.$logical);
                    $urlKey = hash('sha256', $canonical);
                    $hit = ['provider' => $sourceId, 'query_index' => 0, 'page' => $page, 'result_key' => $item['key'], 'url' => $item['url'], 'origin' => $item['origin'], 'publisher' => $item['publisher'], 'snippet' => $item['snippet'], 'response_sha256' => $w->digest($packet), 'body_sha256' => $packet['body_sha256'], 'pointer' => $item['pointer'], 'raw_record_sha256' => $item['raw_record_sha256'], 'parser_version' => $packet['parser_version'], 'synthetic' => true, 'observation_time' => $item['observation_time']];
                    // Aggregator-only title metadata augments lineage; absence of article text is not a revision.
                    if ($source['format'] === 'GDELT_JSON') {
                        foreach ($byUrl[$urlKey] ?? [] as $prior) {
                            if ($entries[$prior]['title'] === $item['title']) {
                                $id = $prior;
                                break;
                            }
                        }
                    }
                    if (isset($entries[$id])) {
                        $entries[$id]['lineage'][] = $hit;
                        $duplicates++;

                        continue;
                    }
                    $contextHits = array_values(array_intersect(MultiSourceFixtureSupport::words(implode(' ', $entities)), MultiSourceFixtureSupport::words($hay)));
                    $questionHits = array_values(array_diff(array_intersect($plan['question_terms'], MultiSourceFixtureSupport::words($hay)), ['synthetic', 'fixture', 'fiktif']));
                    $byUrl[$urlKey][] = $id;
                    $entries[$id] = ['id' => $id, 'title' => $item['title'], 'original_title' => $item['title'], 'url' => $item['url'], 'canonical_url' => $canonical, 'snippet' => $item['snippet'], 'original_snippet' => $item['snippet'], 'derived_translation' => null, 'source_language' => $lang, 'declared_language' => $declared, 'language_detection' => $detection, 'language_basis' => $declared !== 'UNKNOWN' ? 'SOURCE_DECLARED_NOT_VERIFIED' : 'HEURISTIC_OR_UNKNOWN', 'published_at' => $pub, 'updated_at' => $item['updated_at'], 'event_date' => null, 'time_classification' => $time, 'region' => $item['region'], 'source_type' => $source['format'] === 'GDELT_JSON' ? 'PUBLIC_API' : 'RSS_ATOM', 'origins' => [$item['origin']], 'publisher' => $item['publisher'], 'origin_state' => 'DECLARED_NOT_VERIFIED', 'family' => $item['family'], 'lineage' => [$hit], 'snippet_sha256' => hash('sha256', $item['snippet']), 'item_revision_sha256' => $logical, 'revision_state' => 'FIRST_IN_RUN', 'rights_state' => 'UNASSESSED', 'collector_state' => 'OFF', 'retrieval_allowed' => false, 'evidence_admission' => 'NONE', 'fixture_use' => 'SELF_AUTHORED_SYNTHETIC_ONLY', 'fixture_loaded_at' => $packet['fixture_loaded_at'], 'retrieved_at' => null, 'relevance' => ['score' => count($contextHits) + count($questionHits) * 3, 'question_hits' => $questionHits, 'context_hits' => $contextHits], 'relevance_group' => 'QUESTION_AND_OBJECT_MATCH', 'attribution' => ['provider' => $sourceId, 'license_url' => null, 'terms_url' => $source['endpoint'], 'notice' => $source['attribution']], 'snippet_kind' => $item['snippet'] === '' ? 'TITLE_ONLY_NO_ARTICLE_TEXT' : 'SYNTHETIC_FEED_SUMMARY_NOT_FULL_ARTICLE', 'binding' => $binding];
                }
                if ($packet['next'] === null) {
                    break;
                }
            }
        }
        foreach ($byUrl as $ids) {
            if (count($ids) > 1) {
                foreach ($ids as $id) {
                    if (isset($entries[$id])) {
                        $entries[$id]['revision_state'] = 'MULTIPLE_VERSIONS_PRESERVED_NOT_SEPARATE_EVENTS';
                    }
                }
            }
        }
        $all = array_values($entries);
        usort($all, fn ($a, $b) => ($b['relevance']['score'] <=> $a['relevance']['score']) ?: strcmp($a['id'], $b['id']));
        $entries = array_slice($all, 0, 12);
        $pairs = [];
        foreach ($entries as $i => $a) {
            foreach (array_slice($entries, $i + 1) as $b) {
                $sameOrigin = (bool) array_intersect($a['origins'], $b['origins']);
                $family = $a['family'] !== null && $a['family'] === $b['family'];
                $sameUrl = $a['canonical_url'] === $b['canonical_url'];
                $fingerprint = $a['snippet'] !== '' && $a['snippet_sha256'] === $b['snippet_sha256'];
                $pairs[] = ['left' => $a['id'], 'right' => $b['id'], 'shared_terms' => [], 'left_only_terms' => [], 'right_only_terms' => [], 'possible_republication' => $sameOrigin || $family || $sameUrl || $fingerprint, 'shared_declared_origin' => $sameOrigin, 'same_canonical_revision' => $sameUrl, 'same_declared_family' => $family, 'same_content_fingerprint' => $fingerprint, 'independence' => 'NOT_VERIFIED', 'conclusion' => 'Lineage/fingerprint hanya kandidat duplikat, revisi atau publikasi ulang; bukan jumlah peristiwa atau bukti independen.'];
            }
        }
        if ($stop === 'COMPLETED') {
            $stop = array_filter($calls, fn ($c) => $c['state'] !== 'OK') ? ($entries ? 'PARTIAL_COVERAGE' : 'NO_RESULTS_PROVIDER_ERRORS') : ($entries ? 'COMPLETED' : 'NO_RESULTS');
        }

        return ['version' => 'sifi-feed-research-1', 'mode' => 'SYNTHETIC_FEED_ACQUISITION_ONLY', 'entries' => $entries, 'comparisons' => $pairs, 'calls' => $calls, 'feed' => ['selected_sources' => array_map(fn ($id) => $pack->source($id), $options['sources']), 'options' => $options, 'query_plan' => $mp, 'parser_version' => 'sifi-feed-parser-1', 'continuation' => 'EXACT_PACK_ENDPOINT_ONLY_MAX_2_PAGES', 'ingestion' => 'EXPLICIT_SAVED_SELECTION_TO_EXISTING_SYNTHETIC_DRAFT_PIPELINE'], 'provider_ids' => $options['sources'], 'plan_sha256' => $w->digest($plan), 'budget' => $limit, 'usage' => ['simulated_calls' => count($calls), 'fixture_metadata_bytes' => $used, 'network_requests' => 0, 'documents_retrieved' => 0, 'cost' => 0], 'raw_result_count' => $raw, 'unique_result_count' => count($entries), 'collapsed_hits' => $duplicates, 'omitted_unique_count' => max(0, count($all) - count($entries)), 'stopping_reason' => $stop, 'filtered_counts' => $filtered, 'coverage_languages' => array_values(array_unique(array_column($entries, 'source_language'))), 'coverage' => 'RSS2/Atom + GDELT-shaped JSON supplied SYNTHETIC bytes only. No external collection, social API or complete coverage. Observation/seendate is not publication/event date.', 'independence' => 'NOT_VERIFIED', 'claim_verification' => 'NOT_PERFORMED_METADATA_ONLY', 'ai' => 'OFF'];
    }
}
