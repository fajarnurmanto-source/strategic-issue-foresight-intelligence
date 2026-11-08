<?php
namespace App\Services\Sifi;

use DecisionIntelligence\IssueFilter;

/** AE-16 F02 reusable server-side issue filter, advisory only; no DB/network operations. */
final class Ae16UnifiedFilter
{
    public function normalize(array $config): array
    {
        $window = $config['date_window'] ?? [];
        $payload = [
            'query' => $config['query'] ?? '',
            'period' => ['start' => $window['start'] ?? null, 'end_exclusive' => $window['end_exclusive'] ?? null],
            'region' => $config['geography'] ?? '',
            'media' => $config['media_types'] ?? [],
            'sentiment' => $config['sentiment'] ?? '',
        ];
        $valid = IssueFilter::validate($payload);
        if (($window['timezone'] ?? '') !== 'Asia/Jakarta') {
            throw new \InvalidArgumentException('AE16_TIMEZONE_UNSUPPORTED');
        }
        if (($window['time_basis'] ?? '') !== 'publication') {
            throw new \InvalidArgumentException('AE16_TIME_BASIS_UNSUPPORTED');
        }
        if ($valid['region'] !== 'KOTA_YOGYAKARTA') {
            throw new \InvalidArgumentException('AE16_GEOGRAPHY_UNSUPPORTED');
        }
        return $valid + ['time_basis' => 'publication', 'timezone' => 'Asia/Jakarta'];
    }

    /**
     * Three-valued matching: MATCH, EXCLUDED, REVIEW_REQUIRED.
     * The input item must contain actual reviewed metadata. Missing sentiment/date/geo is never inferred.
     */
    public function classify(array $item, array $filter): array
    {
        $required = ['media_type', 'publication_date', 'geography', 'sentiment'];
        foreach ($required as $field) {
            if (!isset($item[$field]) || $item[$field] === '' || $item[$field] === 'unknown') {
                return ['decision' => 'REVIEW_REQUIRED', 'reason' => 'MISSING_OR_UNKNOWN_' . strtoupper($field)];
            }
        }
        if (!in_array($item['media_type'], $filter['media'], true)) {
            return ['decision'=>'EXCLUDED','reason'=>'MEDIA_TYPE'];
        }
        $date = $item['publication_date'];
        if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date) || !checkdate((int)substr($date,5,2),(int)substr($date,8,2),(int)substr($date,0,4))) {
            return ['decision'=>'REVIEW_REQUIRED','reason'=>'PUBLICATION_DATE_UNVERIFIED'];
        }
        if ($date < $filter['period']['start'] || $date >= $filter['period']['end_exclusive']) {
            return ['decision'=>'EXCLUDED','reason'=>'OUTSIDE_PUBLICATION_WINDOW'];
        }
        if ($item['geography'] !== $filter['region']) {
            if (in_array($item['geography'], ['AMBIGUOUS','DIY_UNSPECIFIED'], true)) {
                return ['decision'=>'REVIEW_REQUIRED','reason'=>'GEOGRAPHY_UNVERIFIED'];
            }
            return ['decision'=>'EXCLUDED','reason'=>'OUTSIDE_CITY'];
        }
        if (($item['sentiment_target'] ?? null) !== $filter['region'] || ($item['sentiment_reviewed'] ?? false) !== true) {
            return ['decision'=>'REVIEW_REQUIRED','reason'=>'SENTIMENT_TARGET_OR_REVIEW_MISSING'];
        }
        if ($filter['sentiment'] !== 'all' && $item['sentiment'] !== $filter['sentiment']) {
            return ['decision'=>'EXCLUDED','reason'=>'SENTIMENT_MISMATCH'];
        }
        return ['decision'=>'MATCH','reason'=>'FILTER_MATCH_REVIEWED_METADATA'];
    }
}
