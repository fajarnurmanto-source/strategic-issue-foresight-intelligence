<?php

namespace App\Services\Sifi;

/** One archived, rights-reviewed public response. No network or arbitrary source import. */
final class WorldBankGdpSourceAdapter
{
    public const SOURCE_ID = 'SRC-WB-WDI-IDN-GDP';

    public const DOCUMENTATION = 'https://data.worldbank.org/indicator/NY.GDP.MKTP.KD.ZG?locations=ID';

    public const ENDPOINT = 'https://api.worldbank.org/v2/country/IDN/indicator/NY.GDP.MKTP.KD.ZG?format=json&date=2022:2024&source=2&per_page=3&page=1';

    public const RESPONSE_SHA = '91a84ffd873a11d5e74c13a742f8490cfb35b7ee8b4843e768a1e0b2777aa346';

    public const SCENARIOS = ['wb_snapshot' => 'World Bank · arsip nyata PDB Indonesia 2022–2024'];

    /** @return array<string,mixed> */
    public function snapshot(): array
    {
        $raw = file_get_contents(resource_path('sifi/ae09-wb-response.json'));
        $metaBytes = file_get_contents(resource_path('sifi/ae09-wb-retrieval.json'));
        $rightsBytes = file_get_contents(resource_path('sifi/ae09-wb-rights.json'));
        if ($raw === false || $metaBytes === false || $rightsBytes === false) {
            throw new \RuntimeException('WORLD_BANK_ARCHIVE_UNAVAILABLE');
        }
        $meta = json_decode($metaBytes, true, 32, JSON_THROW_ON_ERROR);
        $rights = json_decode($rightsBytes, true, 32, JSON_THROW_ON_ERROR);
        if (! is_array($meta) || ! is_array($rights)) {
            throw new \RuntimeException('WORLD_BANK_ARCHIVE_METADATA_INVALID');
        }
        $r = $this->normalizeResponse($raw, $meta, $rights);
        if (! hash_equals(self::RESPONSE_SHA, hash('sha256', $raw))) {
            return array_replace($r, ['status' => 'BLOCKED', 'reason' => 'ARCHIVE_DIGEST_MISMATCH', 'observations' => []]);
        }

        return $r;
    }

    /** @return array<string,mixed> */
    public function fetchLive(): array
    {
        return ['status' => 'BLOCKED', 'reason' => 'LIVE_ACQUISITION_NOT_AUTHORIZED'];
    }

    private function digest(mixed $x): string
    {
        return hash('sha256', json_encode($x, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function date(string $v, string $format): bool
    {
        $d = \DateTimeImmutable::createFromFormat('!'.$format, $v, new \DateTimeZone('UTC'));

        return $d !== false && $d->format($format) === $v;
    }

    /** Decoder also accepts adversarial bodies in tests; application only supplies frozen archive. */
    /**
     * @param  array<string,mixed>  $retrieval
     * @param  array<string,mixed>  $rights
     * @return array<string,mixed>
     */
    public function normalizeResponse(string $raw, array $retrieval, array $rights, ?int $now = null): array
    {
        $now ??= now()->getTimestamp();
        $wire = hash('sha256', $raw);
        $source = ['id' => self::SOURCE_ID, 'publisher' => 'World Bank / World Development Indicators', 'documentation_url' => self::DOCUMENTATION, 'endpoint' => self::ENDPOINT,
            'rights_state' => $rights['state'] ?? 'UNKNOWN', 'schema_status' => 'OBSERVED_V2_JSON_2026_10_25', 'collector_state' => 'OFF',
            'source_updated_at' => null, 'fetched_at' => $retrieval['retrieved_at'] ?? null, 'freshness' => 'UNKNOWN', 'source_version' => null,
            'license_url' => $rights['license'] ?? null, 'terms_url' => $rights['terms_url'] ?? null, 'attribution' => $rights['attribution'] ?? null,
            'dataset_terms_url' => $rights['dataset_terms_url'] ?? null, 'rights_review_until' => $rights['review_until'] ?? null, 'retrieval_kind' => 'MANUAL_PUBLIC_AUDIT_ARCHIVE', 'wire_sha256' => $wire,
            'limitation' => 'Arsip publik tiga nilai tahunan nasional 2022–2024, bukan kondisi Kota Yogyakarta atau data real-time. lastupdated adalah tanggal dataset, bukan waktu publikasi tiap record. Unit % dari metadata indikator; unit API kosong dipertahankan. Tidak ada klaim sebab akibat, custody AQ01, verifikasi fakta atau admission. Transformasi SIFI: normalisasi, hash dan perbandingan antarperiode; bukan produk/dukungan resmi World Bank.'];
        $r = ['mode' => 'REAL_PUBLIC_ARCHIVE', 'connector' => 'wb_gdp', 'status' => 'BLOCKED', 'reason' => null, 'source' => $source, 'packet_hash' => $this->digest(['raw' => $wire, 'retrieval' => $retrieval, 'rights' => $rights]),
            'observations' => [], 'duplicates' => [], 'conflicts' => [], 'changes' => [], 'candidate_reason' => null, 'pages_processed' => 0, 'claim_verified' => false, 'evidence_admission' => 'NOT_EVALUATED'];
        $fail = fn (string $why) => array_replace($r, ['reason' => $why]);
        if (($rights['source_id'] ?? null) !== self::SOURCE_ID || ($rights['state'] ?? null) !== 'REVIEWED_CC_BY_4_0' || ($rights['commercial_reuse'] ?? null) !== true
            || ($rights['license'] ?? null) !== 'https://creativecommons.org/licenses/by/4.0/' || ($rights['indicator_url'] ?? null) !== self::DOCUMENTATION
            || ($rights['terms_url'] ?? null) !== 'https://data.worldbank.org/summary-terms-of-use' || ! is_string($rights['attribution'] ?? null) || strlen($rights['attribution']) < 40) {
            return $fail('RIGHTS_NOT_SATISFIED');
        }
        foreach (['reviewed_on', 'review_until'] as $key) {
            if (! is_string($rights[$key] ?? null) || ! $this->date($rights[$key], 'Y-m-d')) {
                return $fail('RIGHTS_METADATA_INCOMPLETE');
            }
        }
        if ($rights['reviewed_on'] > gmdate('Y-m-d', $now) || $rights['review_until'] < gmdate('Y-m-d', $now)) {
            return $fail('RIGHTS_REVIEW_EXPIRED');
        }
        if (($retrieval['url'] ?? null) !== self::ENDPOINT) {
            return $fail('SOURCE_NOT_SUPPORTED');
        }
        $status = $retrieval['status'] ?? null;
        if ($status === 429) {
            return $fail('RATE_LIMITED_NO_RETRY');
        }
        if ($status === 'TIMEOUT') {
            return $fail('TIMEOUT_NO_RETRY');
        }
        if ($status !== 200) {
            return $fail('HTTP_FAILURE_NO_RETRY');
        }
        if (! str_starts_with($retrieval['content_type'] ?? '', 'application/json')) {
            return $fail('CONTENT_TYPE_NOT_SUPPORTED');
        }
        if (($retrieval['sha256'] ?? null) !== $wire) {
            return $fail('CONTENT_DIGEST_MISMATCH');
        }
        if (! is_string($retrieval['retrieved_at'] ?? null) || ! $this->date($retrieval['retrieved_at'], 'Y-m-d\TH:i:s\Z')) {
            return $fail('RETRIEVAL_TIME_MISSING');
        }
        $t = strtotime($retrieval['retrieved_at']);
        if ($t === false) {
            return $fail('RETRIEVAL_TIME_INVALID');
        }
        if ($t > $now + 300) {
            return $fail('FUTURE_RETRIEVAL');
        }
        // Local archive review budget, not a claimed publisher update cadence.
        if ($now - $t > 30 * 86400) {
            return $fail('ARCHIVE_REVIEW_REQUIRED');
        }
        if (strlen($raw) > 65536) {
            return $fail('PAYLOAD_TOO_LARGE');
        }
        try {
            $data = json_decode($raw, true, 24, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $fail('MALFORMED_JSON');
        }
        if (! is_array($data) || ! array_is_list($data) || count($data) !== 2 || ! is_array($data[0]) || ! is_array($data[1]) || ! array_is_list($data[1])) {
            return $fail('SCHEMA_DRIFT');
        }
        [$meta,$rows] = $data;
        if (array_diff(array_keys($meta), ['page', 'pages', 'per_page', 'total', 'sourceid', 'lastupdated'])) {
            return $fail('SCHEMA_DRIFT');
        }
        if (($meta['page'] ?? null) !== 1 || ($meta['pages'] ?? null) !== 1 || ($meta['per_page'] ?? null) !== 3 || ($meta['total'] ?? null) !== 3 || count($rows) !== 3 || ($meta['sourceid'] ?? null) !== '2') {
            return $fail('INCOMPLETE_OR_UNSUPPORTED_PAGINATION');
        }
        if (! is_string($meta['lastupdated'] ?? null) || ! $this->date($meta['lastupdated'], 'Y-m-d') || $meta['lastupdated'] > gmdate('Y-m-d', $t)) {
            return $fail('SOURCE_UPDATE_METADATA_INVALID');
        }
        $r['source']['source_updated_at'] = $meta['lastupdated'];
        $r['source']['source_version'] = $meta['lastupdated'];
        $r['source']['freshness'] = 'HISTORICAL_ANNUAL_ARCHIVE_NOT_CURRENT';
        $r['pages_processed'] = 1;
        $seen = [];
        $periods = [];
        foreach ($rows as $i => $row) {
            if (! is_array($row) || array_diff(array_keys($row), ['indicator', 'country', 'countryiso3code', 'date', 'value', 'unit', 'obs_status', 'decimal'])
                || ! is_array($row['indicator'] ?? null) || count($row['indicator']) !== 2 || ($row['indicator']['id'] ?? null) !== 'NY.GDP.MKTP.KD.ZG' || ($row['indicator']['value'] ?? null) !== 'GDP growth (annual %)'
                || ! is_array($row['country'] ?? null) || count($row['country']) !== 2 || ($row['country']['id'] ?? null) !== 'ID' || ($row['country']['value'] ?? null) !== 'Indonesia' || ($row['countryiso3code'] ?? null) !== 'IDN'
                || ! in_array($row['date'] ?? null, ['2022', '2023', '2024'], true) || ($row['unit'] ?? null) !== '' || ($row['obs_status'] ?? null) !== '' || ($row['decimal'] ?? null) !== 1) {
                return $fail('RECORD_SCHEMA_DRIFT');
            }
            if (! is_int($row['value'] ?? null) && ! is_float($row['value'] ?? null)) {
                return $fail('VALUE_UNAVAILABLE');
            }
            if (! is_finite((float) $row['value'])) {
                return $fail('VALUE_INVALID');
            }
            $key = $this->digest([self::SOURCE_ID, $row['date']]);
            $content = $this->digest(['indicator' => 'NY.GDP.MKTP.KD.ZG', 'country' => 'IDN', 'year' => $row['date'], 'value' => $row['value'], 'unit' => $row['unit'], 'obs_status' => $row['obs_status']]);
            $id = $this->digest([$key, $content]);
            if (isset($seen[$id])) {
                $r['duplicates'][] = ['id' => $id, 'pointer' => '/1/'.$i];

                continue;
            }
            if (isset($periods[$row['date']])) {
                return $fail('SAME_PERIOD_CONFLICT');
            }
            $seen[$id] = true;
            $periods[$row['date']] = true;
            $r['observations'][] = ['id' => $id, 'record_key' => $key, 'content_hash' => $content, 'source_version' => (int) str_replace('-', '', $meta['lastupdated']),
                'description' => 'Pertumbuhan PDB Indonesia '.$row['date'].': '.$row['value'].'% (arsip World Bank; NOT VERIFIED oleh SIFI)',
                'period_label' => 'Tahun '.$row['date'], 'period_year' => (int) $row['date'], 'value' => $row['value'], 'unit' => '% per tahun', 'raw_unit' => $row['unit'],
                'issued_at' => null, 'valid_at' => null, 'provenance' => ['pointer' => '/1/'.$i, 'raw_hash' => $this->digest($row), 'raw_record' => $row,
                    'wire_sha256' => $wire, 'packet_hash' => $r['packet_hash'], 'source_id' => self::SOURCE_ID, 'endpoint' => self::ENDPOINT,
                    'retrieved_at' => $retrieval['retrieved_at'], 'dataset_updated_on' => $meta['lastupdated'], 'record_publication_at' => null,
                    'synthetic' => false, 'independence_verified' => false, 'attribution' => $rights['attribution'], 'license_url' => $rights['license'], 'terms_url' => $rights['terms_url']]];
        }
        if (count($periods) !== 3) {
            return $fail('INCOMPLETE_PERIOD_COVERAGE');
        }
        $sorted = $r['observations'];
        usort($sorted, fn ($a, $b) => $a['period_year'] <=> $b['period_year']);
        for ($i = 1; $i < count($sorted); $i++) {
            $r['changes'][] = ['kind' => 'ANNUAL_PERIOD_COMPARISON_NOT_REVISION_OR_CAUSATION', 'from' => $sorted[$i - 1]['id'], 'to' => $sorted[$i]['id'], 'difference_percentage_points' => $sorted[$i]['value'] - $sorted[$i - 1]['value']];
        }

        return array_replace($r, ['status' => 'READY_FOR_ANALYST_DRAFT', 'candidate_reason' => 'Tiga nilai pertumbuhan PDB nasional tahunan berbeda; bahan konteks makro untuk telaah analis. Bukan sinyal lokal Yogyakarta, sebab akibat, ramalan atau verifikasi claim.']);
    }
}
