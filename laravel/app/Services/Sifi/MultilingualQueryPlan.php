<?php

namespace App\Services\Sifi;

use App\Models\SifiMonitoringObject;

final class MultilingualQueryPlan
{
    public const LANGUAGES = ['id', 'en', 'es', 'fr', 'ja'];

    /** @param array<string, mixed> $base
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function build(SifiMonitoringObject $o, array $base, array $options): array
    {
        $requested = ($options['language_mode'] ?? 'AUTO') === 'AUTO' ? ['id', 'en', 'es', 'fr'] : array_values(array_unique($options['languages'] ?? []));
        if (! $requested || array_diff($requested, self::LANGUAGES) || count($requested) > 5) {
            throw new \RuntimeException('LANGUAGE_BOUND');
        }
        $entities = array_values(array_unique(array_filter([$o->name, ...array_slice($o->aliases_json ?? [], 0, 3)])));
        $entities = array_slice($entities, 0, 3);
        $question = $base['question'] ?? $base['normalized_question'] ?? '';
        $question = $options['question'] ?? $question;
        $start = $options['start_date'] ?? null;
        $end = $options['end_date'] ?? null;
        if (! $start && ! $end && preg_match_all('/\b(?:19|20)\d{2}\b/u', $question, $years) && count(array_unique($years[0])) === 1) {
            $year = $years[0][0];
            $start = $year.'-01-01';
            $end = ((int) $year + 1).'-01-01';
        }
        if (($start === null) !== ($end === null)) {
            throw new \RuntimeException('DATE_WINDOW_REQUIRED');
        }
        $terms = ['id' => 'informasi publik penelitian', 'en' => 'public information research', 'es' => 'información pública investigación', 'fr' => 'information publique recherche', 'ja' => '公開情報 調査'];
        $topics = [];
        $glossary = ['public_services' => [['pelayanan', 'layanan', 'public service', 'servicio público', 'service public'], ['id' => 'pelayanan publik', 'en' => 'public services', 'es' => 'servicios públicos', 'fr' => 'services publics', 'ja' => '公共サービス']], 'criticism' => [['negatif', 'kritik', 'negative', 'criticism', 'crítica'], ['id' => 'kritik pemberitaan konteks klarifikasi', 'en' => 'criticism reporting context clarification', 'es' => 'crítica cobertura contexto aclaración', 'fr' => 'critique actualités contexte clarification', 'ja' => '批判 報道 背景 説明']], 'electric_vehicle' => [['kendaraan listrik', 'electric vehicle', 'vehículo eléctrico', 'EV'], ['id' => 'kendaraan listrik', 'en' => 'electric vehicles', 'es' => 'vehículos eléctricos', 'fr' => 'véhicules électriques', 'ja' => '電気自動車']]];
        $hay = mb_strtolower($question);
        $known = [];
        $contextTopics = [];
        foreach ($glossary as $key => [$triggers,$v]) {
            foreach ($triggers as $trigger) {
                if (mb_stripos($hay, $trigger) !== false) {
                    $topics[$key] = $v;
                    foreach ($triggers as $term) {
                        $known = [...$known, ...MultiSourceFixtureSupport::words($term)];
                    }break;
                }
            }foreach ($triggers as $trigger) {
                if (mb_stripos($o->objective.' '.implode(' ', $o->keywords_json ?? []), $trigger) !== false) {
                    $contextTopics[] = $key;
                    break;
                }
            }
        }
        $stopwords = ['apa', 'saja', 'yang', 'bagaimana', 'tentang', 'sepanjang', 'tahun', 'untuk', 'dan', 'di', 'pada', 'dari', 'what', 'which', 'how', 'is', 'are', 'the', 'in', 'of', 'for', 'about', 'this', 'information', 'available', 'public', 'synthetic', 'fixture', 'fiktif'];
        $entityWords = MultiSourceFixtureSupport::words(implode(' ', $entities).' '.($o->geography ?? ''));
        $untranslated = array_values(array_filter(array_diff(MultiSourceFixtureSupport::words($question), $known, $stopwords, $entityWords), fn ($w) => ! preg_match('/\A[0-9]+\z/', $w)));
        $untranslated = array_slice($untranslated, 0, 12);
        $queries = [];
        for ($variant = 0; $variant < 2; $variant++) {
            foreach ($requested as $lang) {
                if (count($queries) >= 6) {
                    break 2;
                }$entity = $entities[min($variant, count($entities) - 1)];
                $expanded = implode(' ', array_map(fn ($v) => $v[$lang], $topics));
                $q = '"'.$entity.'" '.($o->geography ? '"'.$o->geography.'" ' : '').($expanded ?: $terms[$lang]);
                if ($untranslated) {
                    $q .= ' '.implode(' ', $untranslated);
                }if (! $topics) {
                    $q .= ' '.$question;
                }$q .= ' '.implode(' ', array_slice($o->keywords_json ?? [], 0, 4));
                $q .= ($start ? ' '.$start.' '.$end : '');
                foreach (array_slice($o->exclusions_json ?? [], 0, 6) as $x) {
                    $q .= ' -"'.$x.'"';
                }$q = mb_substr($q, 0, 600);
                $queries[] = ['query' => $q, 'language' => $lang, 'entity' => $entity, 'variant' => $variant, 'query_sha256' => hash('sha256', $q), 'derivation' => 'DERIVED_DETERMINISTIC_GLOSSARY_NOT_FULL_TRANSLATION'];
            }
        }

        return ['version' => 'sifi-multilingual-plan-1', 'requested_languages' => $requested, 'queries' => $queries, 'entities' => array_map(fn ($x) => ['text' => $x, 'authority' => $x === $o->name ? 'PRIMARY_OBJECT_NAME' : 'OWNER_DECLARED_ALIAS_NOT_INDEPENDENTLY_VERIFIED'], $entities), 'geography' => $o->geography, 'objective' => $o->objective, 'question_original' => $question, 'question_translation' => 'NOT_PERFORMED', 'topic_concepts' => array_keys($topics), 'context_topic_concepts' => array_values(array_unique($contextTopics)), 'untranslated_terms' => $untranslated, 'unknown_terms_policy' => 'Retain up to12 unknown question terms literally; preserve full original question in plan. Context topics never replace unknown question intent. No invented entity translations.', 'inclusion_terms' => array_slice($o->keywords_json ?? [], 0, 4), 'exclusions' => $o->exclusions_json ?? [], 'window' => ['start' => $start, 'end_exclusive' => $end], 'filters' => ['language' => $options['result_language'] ?? 'ALL', 'region' => $options['region'] ?? 'EXACT_OBJECT', 'period' => $options['period'] ?? 'ALL_CONTEXT', 'source_type' => $options['source_type'] ?? 'ALL'], 'scenario' => $options['provider_state'] ?? 'normal', 'limits' => ['queries' => 6, 'providers' => 2, 'simulated_calls' => 12, 'results' => 12, 'metadata_bytes' => 65536, 'time_ms' => 2000, 'network_requests' => 0, 'cost' => 0], 'coverage_gaps' => ['Five fixture languages available; AUTO samples id/en/es/fr within budget. Other languages require reviewed adapters/fixtures.', 'Glossary expansion is partial, not translation of arbitrary questions.', 'Object aliases are owner-declared; same name or broader region is not identity proof.']];
    }

    /** @return array{code: string, confidence: float, method: string} */
    public function detect(string $text): array
    {
        if (preg_match('/[\x{3040}-\x{30ff}]/u', $text)) {
            return ['code' => 'ja', 'confidence' => 0.9, 'method' => 'HEURISTIC_SCRIPT_NOT_CALIBRATED'];
        }
        $markers = ['id' => ['untuk', 'layanan', 'penelitian', 'sumber', 'publik', 'contoh'], 'en' => ['the', 'public', 'research', 'source', 'this', 'context'], 'es' => ['para', 'pública', 'investigación', 'fuente', 'este', 'contexto'], 'fr' => ['pour', 'publique', 'recherche', 'source', 'ceci', 'contexte']];
        $words = MultiSourceFixtureSupport::words($text);
        $scores = [];
        foreach ($markers as $l => $terms) {
            $scores[$l] = count(array_intersect($words, $terms));
        }arsort($scores);
        $values = array_values($scores);
        $best = array_key_first($scores);
        $confidence = $values[0] / max(1, array_sum($values));
        if ($values[0] < 2 || $values[0] === $values[1] || $confidence < 0.6) {
            return ['code' => 'UNKNOWN', 'confidence' => round($confidence, 2), 'method' => 'HEURISTIC_LEXICON_INSUFFICIENT_OR_AMBIGUOUS'];
        }

        return ['code' => $best, 'confidence' => round(min(.95, $confidence), 2), 'method' => 'HEURISTIC_LEXICON_NOT_CALIBRATED'];
    }
}
