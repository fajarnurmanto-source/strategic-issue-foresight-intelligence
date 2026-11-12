<?php

namespace App\Services\Sifi;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** One concrete source import adapter. SIFI exchange schema is NOT the unconfirmed official API schema. */
class YogyakartaPadSourceAdapter
{
    public const SOURCE_ID = 'SRC-OPENDATA-YK-PAD';

    public const ENDPOINT = 'https://opendata.jogjakota.go.id/data/pajak/pad_tahun_noseriesapi/index/';

    public const DOCUMENTATION = 'https://opendata.jogjakota.go.id/data/pajak/pad_tahun_noseries';

    public const SCHEMA = 'SIFI_YK_PAD_IMPORT_V1';

    public const SCENARIOS = [
        'pad_valid' => 'Open Data PAD · impor valid', 'pad_duplicate' => 'Open Data PAD · duplikat lintas halaman',
        'pad_updated' => 'Open Data PAD · revisi record', 'pad_stale' => 'Open Data PAD · sumber kedaluwarsa',
        'pad_http_error' => 'Open Data PAD · HTTP gagal', 'pad_rate_limited' => 'Open Data PAD · HTTP 429',
        'pad_timeout' => 'Open Data PAD · timeout', 'pad_missing' => 'Open Data PAD · metadata kurang',
        'pad_denied' => 'Open Data PAD · sumber tidak diizinkan', 'pad_malformed' => 'Open Data PAD · payload rusak',
        'pad_schema' => 'Open Data PAD · schema berubah', 'pad_pagination' => 'Open Data PAD · halaman hilang',
        'pad_import' => 'Open Data PAD · impor JSON simulasi sendiri',
    ];

    /** @return array<string,mixed> */
    public function template(): array
    {
        $contents = file_get_contents(resource_path('sifi/ae08-pad-fixture.json'));
        if ($contents === false) {
            throw new \RuntimeException('PAD_FIXTURE_UNAVAILABLE');
        }
        $packet = json_decode($contents, true, 32, JSON_THROW_ON_ERROR);
        if (! is_array($packet)) {
            throw new \RuntimeException('PAD_FIXTURE_INVALID');
        }

        return $packet;
    }

    /** @return array<string,mixed> */
    public function preview(string $scenario, ?string $json = null): array
    {
        abort_unless(isset(self::SCENARIOS[$scenario]), 422);
        $packet = $this->template();
        if ($scenario === 'pad_import' && $json !== null) {
            return $this->importJson($json);
        }
        if ($scenario === 'pad_duplicate') {
            $packet['pages'][1]['records'][] = $packet['pages'][0]['records'][0];
        }
        if ($scenario === 'pad_updated') {
            $packet['pages'][1]['records'][] = array_replace($packet['pages'][0]['records'][0], ['record_version' => 2, 'amount_rupiah' => 240000000]);
        }
        if ($scenario === 'pad_stale') {
            $packet['source_updated_at'] = '2020-01-01T00:00:00Z';
        }
        if ($scenario === 'pad_missing') {
            unset($packet['attribution']);
        }
        if ($scenario === 'pad_denied') {
            $packet['source_id'] = 'UNAUTHORIZED-SOURCE';
        }
        if ($scenario === 'pad_schema') {
            $packet['schema'] = 'UNCONFIRMED_OFFICIAL_V2';
        }
        if ($scenario === 'pad_pagination') {
            array_pop($packet['pages']);
        }
        if ($scenario === 'pad_malformed') {
            return $this->importJson('{invalid');
        }
        if ($scenario === 'pad_http_error') {
            return $this->blocked('HTTP_500');
        }
        if ($scenario === 'pad_rate_limited') {
            return $this->blocked('RATE_LIMITED', 120);
        }
        if ($scenario === 'pad_timeout') {
            return $this->blocked('SOURCE_TIMEOUT');
        }

        return $this->normalize($packet);
    }

    /** @return array<string,mixed> */
    public function importJson(string $json): array
    {
        if (strlen($json) > 65536) {
            return $this->blocked('PAYLOAD_TOO_LARGE');
        }
        try {
            $packet = json_decode($json, true, 24, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (\JsonException) {
            return $this->blocked('MALFORMED_PAYLOAD');
        }

        return is_array($packet) && ! array_is_list($packet) ? $this->normalize($packet) : $this->blocked('MALFORMED_PAYLOAD');
    }

    /** @param array<array-key,mixed> $data */
    private function digest(array $data): string
    {
        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param  array<array-key,mixed>  $value
     * @param  list<string>  $keys
     */
    private function exactKeys(array $value, array $keys): bool
    {
        $actual = array_keys($value);
        sort($actual);
        sort($keys);

        return $actual === $keys;
    }

    private function utc(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        if (! is_string($value)) {
            return false;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $value, new \DateTimeZone('UTC'));

        return $date !== false && $date->format('Y-m-d\TH:i:s\Z') === $value;
    }

    /** @return array<string,mixed> */
    public function blocked(string $reason, ?int $retryAfter = null): array
    {
        return ['connector' => 'yk_pad', 'mode' => 'SYNTHETIC_FIXTURE_ONLY', 'status' => 'BLOCKED', 'reason' => $reason,
            'source' => ['id' => self::SOURCE_ID, 'publisher' => 'SIFI synthetic import; rujukan Open Data Pemerintah Kota Yogyakarta',
                'documentation_url' => self::DOCUMENTATION, 'endpoint' => self::ENDPOINT, 'rights_state' => 'COMMERCIAL_REUSE_UNCONFIRMED',
                'collector_state' => 'OFF', 'freshness' => 'UNKNOWN', 'schema_status' => 'OFFICIAL_SCHEMA_UNCONFIRMED',
                'limitation' => 'Format impor SIFI, bukan schema API resmi yang telah terbukti. Seluruh angka berlabel SIMULASI; hak komersial dan jadwal sumber belum terverifikasi.'],
            'packet_hash' => hash('sha256', 'AE08_BLOCKED|'.$reason), 'observations' => [], 'duplicates' => [], 'conflicts' => [], 'changes' => [],
            'candidate_reason' => null, 'evidence_admission' => 'NOT_EVALUATED', 'claim_verified' => false,
            'retry_after_seconds' => $retryAfter, 'automatic_retry' => false, 'pages_processed' => 0];
    }

    /**
     * @param  array<string,mixed>  $packet
     * @return array<string,mixed>
     */
    public function normalize(array $packet, ?int $evaluationTime = null): array
    {
        $clock = $evaluationTime ?? now()->getTimestamp();
        $r = $this->blocked('MALFORMED_PAYLOAD');
        if (($packet['schema'] ?? null) !== self::SCHEMA) {
            return $this->blocked('SCHEMA_DRIFT');
        }
        if (($packet['synthetic'] ?? null) !== true || ($packet['source_id'] ?? null) !== self::SOURCE_ID || ($packet['endpoint'] ?? null) !== self::ENDPOINT) {
            return $this->blocked('USE_NOT_PERMITTED');
        }
        if (! is_string($packet['attribution'] ?? null) || ! str_starts_with($packet['attribution'], 'SIMULASI SIFI;') || mb_strlen($packet['attribution']) > 250) {
            return $this->blocked('SOURCE_METADATA_INCOMPLETE');
        }
        if (! $this->exactKeys($packet, ['schema', 'synthetic', 'source_id', 'endpoint', 'attribution', 'source_updated_at', 'pages']) || ! $this->utc($packet['source_updated_at'])) {
            return $this->blocked('SCHEMA_DRIFT');
        }
        $pages = $packet['pages'];
        if (! is_array($pages) || ! array_is_list($pages) || count($pages) < 1 || count($pages) > 5) {
            return $this->blocked('INCOMPLETE_PAGINATION');
        }
        $r['packet_hash'] = $this->digest($packet);
        $r['source']['attribution'] = $packet['attribution'];
        $r['source']['source_updated_at'] = $packet['source_updated_at'];
        $r['source']['fetched_at'] = gmdate('Y-m-d\TH:i:s\Z', $clock);
        $r['source']['source_version'] = self::SCHEMA;
        $r['source']['freshness_policy'] = 'SYNTHETIC_TEST_BUDGET_86400_SECONDS_NOT_OFFICIAL_SCHEDULE';
        if ($packet['source_updated_at'] !== null) {
            $updateTime = strtotime($packet['source_updated_at']);
            if ($updateTime === false) {
                return $this->blocked('SOURCE_UPDATE_METADATA_INVALID');
            }
            $age = $clock - $updateTime;
            if ($age < -300) {
                return $this->blocked('FUTURE_SOURCE_TIMESTAMP');
            }
            if ($age > 86400) {
                return $this->blocked('STALE_SOURCE');
            }
            $r['source']['freshness'] = 'WITHIN_SYNTHETIC_TEST_BUDGET';
        }
        $seen = [];
        $slots = [];
        $rowCount = 0;
        foreach ($pages as $i => $page) {
            if (! is_array($page) || ! $this->exactKeys($page, ['page', 'total_pages', 'records'])
                || ($page['page'] ?? null) !== $i + 1 || ($page['total_pages'] ?? null) !== count($pages)
                || ! is_array($page['records'] ?? null) || ! array_is_list($page['records']) || count($page['records']) < 1 || count($page['records']) > 20) {
                return $this->blocked('INCOMPLETE_PAGINATION');
            }
            foreach ($page['records'] as $j => $raw) {
                if (++$rowCount > 100 || ! is_array($raw) || ! $this->exactKeys($raw, ['tax_code', 'tax_name', 'fiscal_year', 'amount_rupiah', 'record_version', 'updated_at'])) {
                    return $this->blocked('SCHEMA_DRIFT');
                }
                if (! is_string($raw['tax_code']) || preg_match('/\ASIM-[A-Z0-9-]{1,24}\z/', $raw['tax_code']) !== 1
                    || ! is_string($raw['tax_name']) || mb_strlen($raw['tax_name']) > 100 || ! str_ends_with($raw['tax_name'], '(SIMULASI)')
                    || ! is_int($raw['fiscal_year']) || $raw['fiscal_year'] < 2000 || $raw['fiscal_year'] > 2100
                    || ! is_int($raw['amount_rupiah']) || $raw['amount_rupiah'] < 0 || $raw['amount_rupiah'] > 9007199254740991
                    || ! is_int($raw['record_version']) || $raw['record_version'] < 1 || $raw['record_version'] > 1000000 || ! $this->utc($raw['updated_at'])) {
                    return $this->blocked('MALFORMED_RECORD');
                }
                if ($raw['updated_at'] !== null && strtotime($raw['updated_at']) > $clock + 300) {
                    return $this->blocked('FUTURE_SOURCE_TIMESTAMP');
                }
                $key = hash('sha256', self::SOURCE_ID.'|'.$raw['fiscal_year'].'|'.$raw['tax_code']);
                $content = $this->digest(['tax_code' => $raw['tax_code'], 'tax_name' => $raw['tax_name'], 'fiscal_year' => $raw['fiscal_year'], 'amount_rupiah' => $raw['amount_rupiah'], 'currency' => 'IDR']);
                $id = hash('sha256', $key.'|'.$raw['record_version'].'|'.$content);
                $pointer = "/pages/$i/records/$j";
                if (isset($seen[$id])) {
                    $r['duplicates'][] = ['observation_id' => $id, 'pointer' => $pointer, 'raw_hash' => $this->digest($raw)];

                    continue;
                }
                $seen[$id] = true;
                $o = ['id' => $id, 'record_key' => $key, 'content_hash' => $content, 'source_version' => $raw['record_version'],
                    'source_id' => self::SOURCE_ID, 'issued_at' => $raw['updated_at'], 'valid_at' => null, 'period_label' => 'Tahun '.$raw['fiscal_year'],
                    'description' => $raw['tax_name'].' — Rp'.number_format($raw['amount_rupiah'], 0, ',', '.').' (SIMULASI)',
                    'amount_rupiah' => $raw['amount_rupiah'], 'currency' => 'IDR', 'classification' => 'OBSERVED_SYNTHETIC_AGGREGATE_NOT_VERIFIED',
                    'provenance' => ['packet_hash' => $r['packet_hash'], 'pointer' => $pointer, 'raw_hash' => $this->digest($raw), 'raw_record' => $raw,
                        'fetched_at' => $r['source']['fetched_at'], 'source_updated_at' => $packet['source_updated_at'], 'source_version' => $raw['record_version'],
                        'documentation_url' => self::DOCUMENTATION, 'endpoint' => self::ENDPOINT, 'publisher' => $r['source']['publisher'],
                        'attribution' => $packet['attribution'], 'schema' => self::SCHEMA, 'synthetic' => true, 'independence_verified' => false]];
                foreach ($slots[$key] ?? [] as $prior) {
                    $kind = $prior['source_version'] === $o['source_version'] ? 'conflicts' : 'changes';
                    $r[$kind][] = ['observation_ids' => [$prior['id'], $id], 'record_key' => $key, 'reason' => $kind === 'conflicts' ? 'SAME_RECORD_VERSION_DIFFERENT_CONTENT' : 'SOURCE_RECORD_REVISION_NOT_REAL_WORLD_CHANGE'];
                }
                $slots[$key][] = $o;
                $r['observations'][] = $o;
            }
        }
        $r['pages_processed'] = count($pages);
        $r['status'] = 'READY_FOR_ANALYST_DRAFT';
        $r['reason'] = null;
        $r['candidate_reason'] = 'Aturan demonstrasi PAD: ada agregat sintetis yang perlu ditelaah, duplikat atau revisi sumber. Angka dan revisi bukan bukti kegagalan fiskal, peristiwa, atau tren ekonomi.';
        $r['import_packet'] = $packet;

        return $r;
    }

    /** No grant or UI setting can enable this in AE08. Specific live approval and schema/rights proof are separate. */
    /** @return array<string,mixed> */
    public function fetchLive(int $year): array
    {
        return $this->blocked('LIVE_ACQUISITION_NOT_AUTHORIZED');
    }

    /** Prepared bounded transport, exercised through native HTTP fakes only; unreachable from live/UI entry point. */
    /** @return array<string,mixed> */
    protected function readOfficialResponse(int $year): array
    {
        if ($year < 2000 || $year > 2100) {
            return $this->blocked('INVALID_PERIOD');
        }
        try {
            $response = Http::acceptJson()->connectTimeout(2)->timeout(5)->withoutRedirecting()
                ->withOptions(['proxy' => '', 'stream' => true])->get(self::ENDPOINT, ['tahun' => $year]);
            if ($response->status() === 429) {
                $retry = $response->header('Retry-After');

                return $this->blocked('RATE_LIMITED', preg_match('/\A\d{1,6}\z/', $retry) ? min((int) $retry, 86400) : null);
            }
            if (! $response->successful()) {
                return $this->blocked('HTTP_'.$response->status());
            }
            $body = $response->toPsrResponse()->getBody();
            $data = '';
            while (! $body->eof() && strlen($data) <= 65536) {
                $chunk = $body->read(4096);
                if ($chunk === '') {
                    break;
                }$data .= $chunk;
            }
            if (strlen($data) > 65536) {
                return $this->blocked('PAYLOAD_TOO_LARGE');
            }

            // Even valid-looking JSON is not mapped using an invented official API schema.
            return $this->blocked('OFFICIAL_SCHEMA_UNCONFIRMED');
        } catch (ConnectionException) {
            return $this->blocked('SOURCE_TIMEOUT');
        }
    }
}
