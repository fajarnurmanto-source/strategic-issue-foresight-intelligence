<?php

namespace App\Services\Sifi;

use App\Models\SifiClaimDraft;
use App\Models\SifiEvidenceDraft;
use App\Models\SifiForesightDraft;
use App\Models\SifiInvestigation;
use App\Models\SifiIssueCandidate;
use App\Models\SifiIssueCaseDraft;
use App\Models\SifiSourceDraft;
use App\Models\SifiTrajectoryDraft;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Bounded, local synthetic fixture slice. No network, collector or evidence authority. */
final class SourceObservationPipeline
{
    public const SCENARIOS = [
        'wb_research_acquisition' => 'World Bank research · fresh one-shot only',
        'research_fixture' => 'Dokumen riset sintetis',
        'feed_fixture' => 'RSS/Atom/open-news SYNTHETIC tersimpan',
        'valid' => 'Record valid', 'duplicate' => 'Record duplikat',
        'missing_metadata' => 'Metadata sumber tidak lengkap', 'unverified' => 'Klaim belum diverifikasi',
        'conflict' => 'Evidence bertentangan', 'change' => 'Pembaruan kondisi isu',
        'unavailable' => 'Sumber tidak tersedia', 'restricted' => 'Data tidak boleh digunakan',
        'synthetic' => 'Label sintetis wajib',
    ] + YogyakartaPadSourceAdapter::SCENARIOS + WorldBankGdpSourceAdapter::SCENARIOS + WorldBankOneShotAcquisition::SCENARIOS;

    /** @return array<string,mixed> */
    public function fixture(string $scenario): array
    {
        abort_unless(array_key_exists($scenario, self::SCENARIOS), 422);
        $bytes = file_get_contents(resource_path('sifi/ae07-weather-fixture.json'));
        if ($bytes === false) {
            throw new \RuntimeException('WEATHER_FIXTURE_UNAVAILABLE');
        }
        $packet = json_decode($bytes, true, 32, JSON_THROW_ON_ERROR);
        if (! is_array($packet)) {
            throw new \RuntimeException('WEATHER_FIXTURE_INVALID');
        }
        $cell = &$packet['data'][0]['cuaca'][0];
        if ($scenario === 'duplicate') {
            $cell[] = $cell[0];
        }
        if ($scenario === 'missing_metadata') {
            unset($packet['source']['publisher']);
        }
        if ($scenario === 'restricted') {
            $packet['source']['fixture_use'] = 'DENIED';
        }
        if ($scenario === 'unavailable') {
            $packet['unavailable'] = true;
        }
        if ($scenario === 'conflict') {
            $cell[] = array_replace($cell[0], ['weather' => 61, 'weather_desc' => 'Hujan ringan (SIMULASI)', 't' => 26]);
        }
        if ($scenario === 'change') {
            $cell[] = array_replace($cell[0], ['analysis_date' => '2026-01-01T01:00:00', 'weather' => 61, 'weather_desc' => 'Hujan ringan (SIMULASI)', 't' => 26]);
        }

        return $packet;
    }

    /** @return array<string,mixed> */
    public function preview(string $scenario, ?string $importJson = null, ?int $ownerId = null): array
    {
        if ($scenario === 'wb_research_acquisition') {
            abort_unless($ownerId !== null && is_string($importJson), 422);

            return app(WorldBankResearchWorkspace::class)->pipelinePreview(User::findOrFail($ownerId), $importJson);
        }
        if ($scenario === 'feed_fixture') {
            abort_unless($ownerId !== null && is_string($importJson), 422);

            return app(FeedResearchBridge::class)->pipelinePreview(User::findOrFail($ownerId), $importJson);
        }
        if ($scenario === 'research_fixture') {
            abort_unless($ownerId !== null && is_string($importJson), 422);

            return app(ResearchRetrievalWorkspace::class)->pipelinePreview(User::findOrFail($ownerId), $importJson);
        }
        if ($scenario === 'wb_acquisition') {
            $service = app(WorldBankOneShotAcquisition::class);
            $user = $ownerId !== null ? User::findOrFail($ownerId) : null;
            $p = $user ? $service->load($user, $importJson) : $service->emptyPreview();
            $p['acquisition_allowed'] = $user ? $service->canAcquire($user) : false;
            if ($user && $p['status'] === 'READY_FOR_ANALYST_DRAFT') {
                $p = $this->withImportHistory($p, $ownerId, $p['mode'] === 'MOCK_ACQUISITION_ONLY' ? 'AE10_MOCK_ACQUISITION' : 'AE10_REAL_ACQUISITION');
            }

            return $p + ['scenario' => $scenario, 'scenarios' => self::SCENARIOS, 'registration_required' => true];
        }
        if ($scenario === 'wb_snapshot') {
            $p = app(WorldBankGdpSourceAdapter::class)->snapshot();
            if ($ownerId !== null && $p['status'] === 'READY_FOR_ANALYST_DRAFT') {
                $p = $this->withImportHistory($p, $ownerId, 'AE09_REAL_PUBLIC_ARCHIVE');
            }

            return $p + ['scenario' => $scenario, 'scenarios' => self::SCENARIOS, 'registration_required' => true];
        }
        if (isset(YogyakartaPadSourceAdapter::SCENARIOS[$scenario])) {
            $adapter = app(YogyakartaPadSourceAdapter::class);
            $preview = $adapter->preview($scenario, $importJson);
            if ($ownerId !== null && $preview['status'] === 'READY_FOR_ANALYST_DRAFT') {
                $preview = $this->withImportHistory($preview, $ownerId);
            }

            return $preview + ['scenario' => $scenario, 'scenarios' => self::SCENARIOS,
                'import_template' => json_encode($adapter->template(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'import_json' => $importJson, 'registration_required' => true];
        }

        return $this->normalize($this->fixture($scenario)) + ['scenario' => $scenario, 'scenarios' => self::SCENARIOS];
    }

    /** @param array<array-key,mixed> $data */
    private function digest(array $data): string
    {
        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** A closed fixture envelope: never accepted as a remote, verified or user-uploaded record. */
    /**
     * @param  array<string,mixed>  $packet
     * @return array<string,mixed>
     */
    public function normalize(array $packet): array
    {
        $result = ['mode' => 'SYNTHETIC_FIXTURE_ONLY', 'status' => 'BLOCKED', 'reason' => null,
            'source' => $packet['source'] ?? [], 'packet_hash' => $this->digest($packet),
            'observations' => [], 'duplicates' => [], 'conflicts' => [], 'changes' => [],
            'candidate_reason' => null, 'evidence_admission' => 'NOT_EVALUATED', 'claim_verified' => false];
        $source = $packet['source'] ?? [];
        if (($packet['unavailable'] ?? false) === true) {
            return array_replace($result, ['reason' => 'SOURCE_UNAVAILABLE']);
        }
        if (($packet['synthetic'] ?? null) !== true || ($source['fixture_use'] ?? null) !== 'SYNTHETIC_TEST_ONLY') {
            return array_replace($result, ['reason' => 'USE_NOT_PERMITTED']);
        }
        foreach (['id', 'publisher', 'documentation_url', 'rights_state', 'limitation'] as $key) {
            if (! is_string($source[$key] ?? null) || trim($source[$key]) === '') {
                return array_replace($result, ['reason' => 'SOURCE_METADATA_INCOMPLETE']);
            }
        }
        if ($source['id'] !== 'SRC-BMKG' || $source['documentation_url'] !== 'https://data.bmkg.go.id/prakiraan-cuaca/'
            || ($source['collector_state'] ?? null) !== 'OFF') {
            return array_replace($result, ['reason' => 'SOURCE_NOT_SUPPORTED']);
        }
        $seen = [];
        $slots = [];
        $count = 0;
        if (! is_array($packet['data'] ?? null) || ! array_is_list($packet['data']) || count($packet['data']) !== 1) {
            return array_replace($result, ['reason' => 'MALFORMED_RECORD']);
        }
        foreach ($packet['data'] as $index => $data) {
            if (! is_array($data) || ($data['lokasi']['adm4'] ?? null) !== '00.00.00.0000'
                || ! is_array($data['cuaca'] ?? null) || ! array_is_list($data['cuaca'])) {
                return array_replace($result, ['reason' => 'MALFORMED_RECORD']);
            }
            foreach ($data['cuaca'] as $day => $cells) {
                if (! is_array($cells) || ! array_is_list($cells)) {
                    return array_replace($result, ['reason' => 'MALFORMED_RECORD']);
                }
                foreach ($cells as $position => $raw) {
                    if (++$count > 100 || ! is_array($raw)) {
                        return array_replace($result, ['reason' => 'MALFORMED_RECORD']);
                    }
                    foreach (['analysis_date' => 'Y-m-d\TH:i:s', 'utc_datetime' => 'Y-m-d H:i:s'] as $key => $format) {
                        $value = $raw[$key] ?? null;
                        if (! is_string($value) || ! ($date = \DateTimeImmutable::createFromFormat('!'.$format, $value, new \DateTimeZone('UTC')))
                            || $date->format($format) !== $value) {
                            return array_replace($result, ['reason' => 'MALFORMED_RECORD']);
                        }
                    }
                    if (! is_int($raw['weather'] ?? null) || ! in_array($raw['weather'], [3, 61], true)
                        || ! is_int($raw['t'] ?? null) || $raw['t'] < -50 || $raw['t'] > 60
                        || ! is_int($raw['hu'] ?? null) || $raw['hu'] < 0 || $raw['hu'] > 100
                        || ! is_string($raw['weather_desc'] ?? null) || mb_strlen($raw['weather_desc']) > 100
                        || ! str_contains($raw['weather_desc'], '(SIMULASI)')
                        || strtotime($raw['analysis_date'].'Z') > strtotime(str_replace(' ', 'T', $raw['utc_datetime']).'Z')) {
                        return array_replace($result, ['reason' => 'MALFORMED_RECORD']);
                    }
                    $value = ['source_id' => $source['id'], 'adm4' => '00.00.00.0000',
                        'issued_at' => $raw['analysis_date'].'Z', 'valid_at' => str_replace(' ', 'T', $raw['utc_datetime']).'Z',
                        'temperature_c' => $raw['t'], 'humidity_percent' => $raw['hu'],
                        'weather_code' => $raw['weather'], 'description' => $raw['weather_desc']];
                    $id = $this->digest($value);
                    $pointer = "/data/$index/cuaca/$day/$position";
                    if (isset($seen[$id])) {
                        $result['duplicates'][] = ['pointer' => $pointer, 'observation_id' => $id];

                        continue;
                    }
                    $seen[$id] = true;
                    $observation = $value + ['id' => $id, 'classification' => 'OBSERVED_FIXTURE_FORECAST_NOT_EVENT',
                        'provenance' => ['packet_hash' => $result['packet_hash'], 'pointer' => $pointer,
                            'raw_hash' => $this->digest($raw), 'raw_record' => $raw, 'publisher' => $source['publisher'],
                            'documentation_url' => $source['documentation_url'], 'synthetic' => true, 'independence_verified' => false]];
                    $slot = $value['adm4'].'|'.$value['valid_at'];
                    foreach ($slots[$slot] ?? [] as $previous) {
                        if ($previous['id'] === $id) {
                            continue;
                        }
                        $kind = $previous['issued_at'] === $value['issued_at'] ? 'conflicts' : 'changes';
                        $result[$kind][] = ['observation_ids' => [$previous['id'], $id], 'valid_at' => $value['valid_at'],
                            'reason' => $kind === 'conflicts' ? 'SAME_ISSUE_AND_VALID_TIME_DIFFERENT_CONTENT' : 'DISTINCT_FORECAST_ISSUES_NOT_OBSERVED_EVENT'];
                    }
                    $slots[$slot][] = $observation;
                    $result['observations'][] = $observation;
                }
            }
        }
        if ($count === 0) {
            return array_replace($result, ['reason' => 'NO_RECORDS']);
        }
        $result['status'] = 'READY_FOR_ANALYST_DRAFT';
        $result['candidate_reason'] = 'Aturan demonstrasi: minimal satu slot prakiraan sintetis berkode 61 (hujan ringan); perlu pemeriksaan analis, bukan indikasi kejadian aktual.';
        if (! collect($result['observations'])->contains('weather_code', 61)) {
            return array_replace($result, ['status' => 'NO_CANDIDATE', 'reason' => 'NO_MATCHING_FIXTURE_RULE']);
        }

        return $result;
    }

    /** Explicit analyst action creates new, auditable local drafts only; never updates existing drafts. */
    public function createDraft(User $user, string $scenario, string $note, ?SifiSourceDraft $registeredSource = null, ?int $sourceRevision = null, ?string $importJson = null, ?string $expectedPacketHash = null): SifiIssueCandidate
    {
        $preview = $this->preview($scenario, $importJson, (int) $user->id);
        $isResearch = ($preview['connector'] ?? null) === 'research_fixture';
        $isPad = ($preview['connector'] ?? null) === 'yk_pad';
        $isWb = in_array($preview['connector'] ?? null, ['wb_gdp', 'wb_acquisition'], true);
        $isAcquisition = ($preview['connector'] ?? null) === 'wb_acquisition';
        $isMock = ($preview['mode'] ?? null) === 'MOCK_ACQUISITION_ONLY';
        $isRegistered = $isPad || $isWb;
        if ($preview['status'] !== 'READY_FOR_ANALYST_DRAFT') {
            throw ValidationException::withMessages(['scenario' => 'Skenario diblokir: '.$preview['reason']]);
        }
        Gate::forUser($user)->authorize('viewAny', SifiSourceDraft::class);
        if ($isRegistered) {
            if (! is_string($expectedPacketHash) || ! hash_equals($preview['packet_hash'], $expectedPacketHash)) {
                throw ValidationException::withMessages(['input_digest' => 'Preview impor berubah; periksa ulang sebelum membuat draft.']);
            }
            if ($registeredSource === null) {
                throw ValidationException::withMessages(['source_draft_id' => 'Daftarkan dan pilih usulan sumber konektor terlebih dahulu.']);
            }
            Gate::forUser($user)->authorize('view', $registeredSource);
        }

        return DB::transaction(function () use ($user, $preview, $note, $isPad, $isWb, $isAcquisition, $isMock, $isRegistered, $registeredSource, $sourceRevision, $isResearch): SifiIssueCandidate {
            if ($isRegistered) {
                $locked = SifiSourceDraft::query()->whereKey($registeredSource->id)->lockForUpdate()->firstOrFail();
                abort_unless((int) $locked->user_id === (int) $user->id, 403);
                if ((int) $locked->revision !== $sourceRevision) {
                    throw ValidationException::withMessages(['source_revision' => 'Revisi sumber berubah; muat ulang.']);
                }
                if (! in_array($locked->homepage_url, ($isWb ? [WorldBankGdpSourceAdapter::DOCUMENTATION, WorldBankGdpSourceAdapter::ENDPOINT] : [YogyakartaPadSourceAdapter::DOCUMENTATION, YogyakartaPadSourceAdapter::ENDPOINT]), true)
                    || $locked->source_type !== 'OPEN_DATA' || $locked->collector_state !== 'OFF') {
                    throw ValidationException::withMessages(['source_draft_id' => 'Usulan sumber tidak cocok dengan endpoint konektor yang diizinkan.']);
                }
                $preview['source']['registered_source_id'] = $locked->id;
                $preview['source']['registered_source_revision'] = (int) $locked->revision;
            }
            // Serialize per-owner imports without a new table; stable UUID prevents repeat/concurrent duplication.
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($isAcquisition) {
                $preview = $this->withImportHistory($preview, (int) $user->id, $isMock ? 'AE10_MOCK_ACQUISITION' : 'AE10_REAL_ACQUISITION');
                if ($preview['status'] !== 'READY_FOR_ANALYST_DRAFT') {
                    throw ValidationException::withMessages(['scenario' => 'Riwayat sumber berubah: '.$preview['reason']]);
                }
            }
            $ids = array_column($preview['observations'], 'id');
            sort($ids);
            $stable = hash('sha256', 'ae07|'.$user->id.'|'.implode('|', $ids));
            $candidateId = substr($stable, 0, 8).'-'.substr($stable, 8, 4).'-4'.substr($stable, 13, 3).'-a'.substr($stable, 17, 3).'-'.substr($stable, 20, 12);
            if ($existing = SifiIssueCandidate::query()->find($candidateId)) {
                abort_unless((int) $existing->user_id === (int) $user->id, 409);
                Gate::forUser($user)->authorize('view', $existing);

                return $existing;
            }
            $uid = (int) $user->id;
            $inv = (string) Str::uuid();
            $now = now();
            $title = $isPad ? '[SIMULASI AE-08] Telaah record pajak daerah' : '[SIMULASI AE-07] Perubahan prakiraan, bukan kejadian';
            $gaps = 'SYNTHETIC FIXTURE ONLY. Tidak ada observasi lapangan, sumber independen, custody AQ01 atau izin live BMKG. Evidence NOT_EVALUATED. Tidak membuktikan hujan, dampak, atau kebutuhan respons nyata.';
            if ($isPad) {
                $gaps = 'SYNTHETIC FIXTURE ONLY. Schema API resmi, hak reuse komersial dan waktu pembaruan sumber belum terbukti. Tidak ada custody AQ01 atau evidence admitted. Revisi angka sumber bukan bukti kegagalan fiskal atau tren ekonomi.';
            }
            $next = $isPad ? 'Periksa periode, versi, atribusi, freshness dan hak sumber; jangan menyimpulkan kondisi ekonomi dari angka simulasi.' : 'Periksa setiap record, provenance, konflik dan versi prakiraan; tentukan data primer yang diperlukan sebelum keputusan.';
            if ($isWb) {
                $title = '[ARSIP NYATA AE-09] Konteks pertumbuhan PDB Indonesia 2022–2024';
                $gaps = $preview['source']['limitation'];
                $next = 'Telaah definisi dan periode; periksa rilis primer terbaru sebelum keputusan. Jangan ekstrapolasi nasional ke wilayah lokal.';
            }
            if ($isAcquisition) {
                $title = $isMock ? '[MOCK/SIMULASI AE-10] Uji pengambilan paket PDB' : '[ARSIP NYATA AE-10] Pengambilan satu paket PDB historis';
                $gaps = $preview['source']['limitation'];
            }
            if ($isResearch) {
                $title = ($preview['feed_connector'] ?? false) ? '[SYNTHETIC AE14] Telaah item feed' : '[SYNTHETIC AE14] Telaah dokumen riset';
                $gaps = $preview['source']['limitation'];
                $next = ($preview['feed_connector'] ?? false) ? 'Tinjau metadata, hash feed/item, lineage, konteks objek dan unknowns; ringkasan feed bukan dokumen lengkap.' : 'Tinjau hash/span, konteks objek dan unknowns; sumber nyata belum diambil.';
            }
            $origin = $isAcquisition ? ($isMock ? 'AE10_MOCK_ACQUISITION' : 'AE10_REAL_ACQUISITION') : ($isWb ? 'AE09_REAL_PUBLIC_ARCHIVE' : ($isPad ? 'AE08_SYNTHETIC_IMPORT' : 'AE07_SYNTHETIC_TEMPLATE'));
            if ($isResearch) {
                $origin = ($preview['feed_connector'] ?? false) ? 'AE14_FEED_FIXTURE' : 'AE14_RESEARCH_FIXTURE';
            }
            $action = $isWb && ! $isMock ? 'SOURCE_SNAPSHOT_IMPORTED' : 'SYNTHETIC_TEMPLATE_CREATED';
            $lineage = ['origin' => $origin, 'packet_hash' => $preview['packet_hash'], 'source' => $preview['source'],
                'duplicates' => $preview['duplicates'], 'conflicts' => $preview['conflicts'], 'changes' => $preview['changes'],
                'analyst_note' => $note, 'formal_review_performed' => false,
                ...(($isRegistered || $isResearch) ? ['pipeline_snapshot' => $preview] : [])];
            DB::table('sifi_investigations')->insert(['id' => $inv, 'user_id' => $uid, 'title' => $title, 'claim_hypothesis' => $preview['candidate_reason'],
                'evidence_gaps' => $gaps, 'next_steps' => $next, 'review_state' => 'DRAFT', 'revision' => 1, 'created_at' => $now, 'updated_at' => $now]);
            Gate::forUser($user)->authorize('create', [SifiIssueCandidate::class, SifiInvestigation::findOrFail($inv)]);
            $candidate = SifiIssueCandidate::create(['id' => $candidateId, 'investigation_id' => $inv, 'user_id' => $uid, 'title' => $title,
                'issue_hypothesis' => ($isWb && ! $isMock ? '[ARSIP NYATA / NOT VERIFIED] ' : '[SIMULASI] ').$preview['candidate_reason'], 'exposure_notes' => $note,
                'evidence_gaps' => $gaps, 'next_checks' => $next, 'review_state' => 'DRAFT', 'revision' => 1]);
            $this->audit('sifi_investigation_audit', 'investigation_id', $inv, $uid, $lineage, $action);
            $this->audit('sifi_issue_candidate_audit', 'candidate_id', $candidate->id, $uid, $lineage, $action);
            foreach ($preview['observations'] as $observation) {
                // Local fixture reference is explicitly NOT an AQ01 signal/custody assertion.
                DB::table('sifi_investigation_signals')->insert(['investigation_id' => $inv, 'signal_id' => $observation['id'], 'added_by_user_id' => $uid, 'created_at' => $now]);
                $statement = $isPad ? '[SIMULASI / NOT VERIFIED] '.$observation['description'].'; '.$observation['period_label'].'; versi '.$observation['source_version'].'. Bukan angka resmi atau kesimpulan fiskal.' : '[SIMULASI / NOT VERIFIED] '.$observation['description'].' pada slot '.$observation['valid_at'].'; terbit '.$observation['issued_at'].'. Bukan cuaca yang teramati.';
                if ($isResearch) {
                    $statement = '[SYNTHETIC / NOT VERIFIED] '.$observation['description'].(($preview['feed_connector'] ?? false) ? '; publikasi '.($observation['issued_at'] ?? 'UNKNOWN').'; evidence NOT_EVALUATED.' : '; publication UNKNOWN; evidence NOT_EVALUATED.');
                }
                if ($isWb) {
                    $statement = ($isMock ? '[MOCK/SIMULASI / NOT VERIFIED] ' : '[ARSIP NYATA / NOT VERIFIED] ').$observation['description'].'; waktu publikasi record UNKNOWN. Sumber: '.$preview['source']['attribution'];
                }
                $evidence = SifiEvidenceDraft::create(['id' => (string) Str::uuid(), 'candidate_id' => $candidate->id, 'owner_user_id' => $uid,
                    'signal_id' => $observation['id'], 'claim_text' => $statement, 'rationale' => json_encode([$isWb ? 'ae09_lineage' : ($isPad ? 'ae08_lineage' : 'ae07_lineage') => $observation], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'known_gaps' => $gaps, 'relationship' => 'CONTEXT', 'workflow_state' => 'DRAFT', 'revision' => 1]);
                $this->audit('sifi_evidence_draft_audit', 'evidence_id', $evidence->id, $uid, ['origin' => $origin, 'observation_id' => $observation['id'], 'evidence_admission' => 'NOT_EVALUATED'], $action);
                $claim = SifiClaimDraft::create(['id' => (string) Str::uuid(), 'candidate_id' => $candidate->id, 'evidence_id' => $evidence->id,
                    'owner_user_id' => $uid, 'statement' => $statement, 'attribution' => $isResearch ? ($preview['source']['attribution'] ?? 'SIFI self-authored document fixture; no external publication') : ($isWb ? $preview['source']['attribution'] : ($isPad ? 'SIFI synthetic PAD import; official schema/reuse unconfirmed' : 'SIFI synthetic fixture, BMKG-format documentation only')), 'modality' => 'UNDETERMINED', 'revision' => 1]);
                DB::table('sifi_claim_event_audit')->insert(['candidate_id' => $candidate->id, 'actor_user_id' => $uid, 'action' => $action,
                    'object_id' => $claim->id, 'snapshot_json' => json_encode(['origin' => $origin, 'verification_state' => 'NOT_VERIFIED', 'observation_id' => $observation['id']], JSON_THROW_ON_ERROR), 'created_at' => $now]);
            }
            $case = SifiIssueCaseDraft::create(['id' => (string) Str::uuid(), 'candidate_id' => $candidate->id, 'owner_user_id' => $uid,
                'title' => $title, 'issue_domain' => $isWb ? 'economy' : ($isPad ? 'public_finance' : 'environment'), 'geography_label' => $isMock ? 'MOCK pola Indonesia, bukan lokasi/peristiwa terverifikasi' : ($isWb ? 'Indonesia — nasional, bukan Kota Yogyakarta' : ($isPad ? 'Kota Yogyakarta — cakupan katalog; angka SIMULASI' : 'WILAYAH SIMULASI — bukan lokasi nyata')),
                'scope_note' => $isMock ? 'MOCK/SIMULASI. Tidak ada statistik nyata atau event terverifikasi.' : ($isWb ? 'Arsip tiga record nasional nyata; tanpa event/admission/keputusan terverifikasi.' : '[SIMULASI] Template untuk ditelaah analis; tidak ada event terverifikasi.'), 'lifecycle_state' => 'DRAFT', 'revision' => 1]);
            $direction = '[SIMULASI] '.count($preview['observations']).' record unik; '.count($preview['duplicates']).' duplikat diabaikan; '.count($preview['conflicts']).' pasangan bertentangan; '.count($preview['changes']).' pasangan versi berubah. '.($isPad ? 'Revisi record agregat bukan bukti perubahan kondisi ekonomi. Freshness saat impor: '.$preview['source']['freshness'] : 'Perbedaan prakiraan bukan bukti tren kejadian.');
            if ($isWb) {
                $direction = ($isMock ? '[MOCK/SIMULASI / NON_BINDING] ' : '[ARSIP NYATA / HIPOTESIS NON_BINDING] ').count($preview['observations']).' nilai tahunan 2022–2024. Perbandingan pertumbuhan dalam percentage points bukan revisi record, bukan pertumbuhan level PDB, bukan sebab akibat atau prediksi. Tidak menyatakan kondisi terkini.';
            }
            $trajectory = SifiTrajectoryDraft::create(['id' => (string) Str::uuid(), 'case_id' => $case->id, 'owner_user_id' => $uid,
                'horizon' => 'UNSPECIFIED', 'trajectory_hypothesis' => $direction, 'risk_hypothesis' => $isWb ? 'Risiko belum ditetapkan dari tiga angka makro tahunan.' : ($isPad ? '[SIMULASI] Risiko fiskal tidak ditetapkan dari angka fixture.' : '[SIMULASI] Dampak cuaca belum diketahui; jangan menganggap prakiraan sebagai bencana.'),
                'opportunity_hypothesis' => $isWb ? 'Gunakan sebagai konteks; cari indikator primer lokal dan terbaru.' : ($isPad ? '[SIMULASI] Telaah data primer resmi setelah izin dan schema tersedia.' : '[SIMULASI] Pertimbangkan pemeriksaan kesiapan setelah data primer dan izin tersedia.'),
                'watch_condition' => $isWb ? 'Periksa tanggal dataset dan periode yang sama; revisi nilai tidak sama dengan perubahan periode.' : ($isPad ? 'Periksa periode dan versi record yang sama; waktu publikasi yang hilang tetap UNKNOWN.' : 'Bandingkan issue time dan valid time yang sama; cari observasi nyata yang sah.'), 'uncertainty_note' => $gaps, 'revision' => 1]);
            $foresight = SifiForesightDraft::create(['id' => (string) Str::uuid(), 'trajectory_id' => $trajectory->id, 'owner_user_id' => $uid,
                'baseline_scenario' => $isWb ? 'Konteks historis nasional; kondisi terbaru belum dinilai.' : '[SIMULASI] Kondisi aktual tidak diketahui.', 'alternative_scenario' => $isWb ? 'Nilai dapat direvisi oleh penerbit; tidak ada ramalan dari tiga record.' : ($isPad ? '[SIMULASI] Angka atau metode pencatatan dapat direvisi; periksa versi dan definisi.' : '[SIMULASI] Prakiraan dapat direvisi.'),
                'stress_scenario' => $isWb ? 'Dampak masa depan belum dinilai; bukan peringatan.' : '[SIMULASI] Dampak buruk hanya hipotesis, bukan peringatan.', 'critical_drivers' => $isWb ? 'Definisi, periode, metode dan pembaruan data primer.' : '[SIMULASI] Ketersediaan observasi primer dan kesesuaian waktu.',
                'falsification_checks' => 'Cari observasi sah yang membantah interpretasi; jangan menilai kebenaran melalui AI.',
                'decision_options' => 'Tinjau manual; tunda keputusan substantif sampai bukti tersedia.', 'uncertainty_note' => $gaps, 'revision' => 1]);
            $this->audit('sifi_issue_case_audit', 'case_id', $case->id, $uid, $lineage, $action);
            $this->audit('sifi_trajectory_audit', 'draft_id', $trajectory->id, $uid, $lineage, $action);
            $this->audit('sifi_foresight_audit', 'draft_id', $foresight->id, $uid, $lineage, $action);
            if ($isResearch) {
                $candidate->update(['issue_hypothesis' => '[SYNTHETIC / NOT VERIFIED] '.$preview['candidate_reason']]);
                $case->update(['issue_domain' => 'research_fixture', 'geography_label' => 'Konteks objek riset, bukan lokasi terverifikasi', 'scope_note' => $gaps]);
                $trajectory->update(['trajectory_hypothesis' => '[SYNTHETIC] Materi riset sintetis terpilih; trajectory kondisi aktual tidak dinilai.', 'risk_hypothesis' => 'Belum dinilai', 'opportunity_hypothesis' => 'Belum dinilai', 'watch_condition' => 'Cari sumber nyata yang diizinkan dan counterevidence.']);
                $foresight->update(['baseline_scenario' => '[SYNTHETIC] Kondisi aktual UNKNOWN', 'alternative_scenario' => 'Belum dinilai', 'stress_scenario' => 'Belum dinilai', 'critical_drivers' => 'Kecukupan sumber, bukan prakiraan cuaca.']);
            }

            return $candidate;
        });
    }

    /**
     * @param  array<string,mixed>  $preview
     * @return array<string,mixed>
     */
    private function withImportHistory(array $preview, int $ownerId, string $origin = 'AE08_SYNTHETIC_IMPORT'): array
    {
        $rows = DB::table('sifi_issue_candidate_audit as a')->join('sifi_issue_candidates as c', 'c.id', '=', 'a.candidate_id')
            ->where('c.user_id', $ownerId)->where('a.actor_user_id', $ownerId)->where('a.revision', 1)
            ->whereIn('a.snapshot_json->origin', $origin === 'AE10_REAL_ACQUISITION' ? ['AE09_REAL_PUBLIC_ARCHIVE', 'AE10_REAL_ACQUISITION'] : [$origin])->orderByDesc('a.id')->limit(200)->get(['a.snapshot_json']);
        $seen = [];
        $latest = [];
        foreach ($rows as $row) {
            $j = json_decode($row->snapshot_json, true);
            foreach ($j['pipeline_snapshot']['observations'] ?? [] as $o) {
                $seen[$o['id']] = true;
                if (! isset($latest[$o['record_key']]) || $latest[$o['record_key']]['source_version'] < $o['source_version']) {
                    $latest[$o['record_key']] = $o;
                }
            }
        }
        $history = ['new' => 0, 'already_seen' => 0, 'updated' => 0, 'conflicting' => 0, 'out_of_order' => 0, 'audit_window_limit' => 200, 'imports_examined' => count($rows)];
        foreach ($preview['observations'] as $o) {
            if (isset($seen[$o['id']])) {
                $history['already_seen']++;

                continue;
            }
            $prior = $latest[$o['record_key']] ?? null;
            if (! $prior) {
                $history['new']++;
            } elseif ($o['source_version'] > $prior['source_version']) {
                $history['updated']++;
            } elseif ($o['source_version'] < $prior['source_version']) {
                $history['out_of_order']++;
            } else {
                $history['conflicting']++;
            }
        }
        $preview['history'] = $history;
        if (in_array($origin, ['AE10_MOCK_ACQUISITION', 'AE10_REAL_ACQUISITION'], true) && $history['conflicting'] > 0) {
            $preview['status'] = 'BLOCKED';
            $preview['reason'] = 'SOURCE_REVISION_CONFLICT';
        }
        if ($history['out_of_order'] > 0) {
            $preview['status'] = 'BLOCKED';
            $preview['reason'] = 'OUT_OF_ORDER_SOURCE_VERSION';
        }

        return $preview;
    }

    /** @param array<string,mixed> $snapshot */
    private function audit(string $table, string $foreign, string $id, int $uid, array $snapshot, string $action = 'SYNTHETIC_TEMPLATE_CREATED'): void
    {
        DB::table($table)->insert([$foreign => $id, 'actor_user_id' => $uid, 'revision' => 1, 'action' => $action,
            'snapshot_json' => json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'created_at' => now()]);
    }

    /** Reconstruct immutable fixture provenance from the creation audit, not editable analyst text. */
    /** @return array<string,mixed>|null */
    public function lineage(SifiIssueCandidate $candidate): ?array
    {
        $audit = DB::table('sifi_issue_candidate_audit')->where('candidate_id', $candidate->id)
            ->where('revision', 1)->whereIn('action', ['SYNTHETIC_TEMPLATE_CREATED', 'SOURCE_SNAPSHOT_IMPORTED'])->first();
        if (! $audit) {
            return null;
        }
        $snapshot = json_decode($audit->snapshot_json, true);
        $isFeed = ($snapshot['origin'] ?? null) === 'AE14_FEED_FIXTURE';
        $isResearch = in_array($snapshot['origin'] ?? null, ['AE14_RESEARCH_FIXTURE', 'AE14_FEED_FIXTURE'], true);
        $isPad = ($snapshot['origin'] ?? null) === 'AE08_SYNTHETIC_IMPORT';
        $isWb = ($snapshot['origin'] ?? null) === 'AE09_REAL_PUBLIC_ARCHIVE';
        $isAcquisition = in_array($snapshot['origin'] ?? null, ['AE10_MOCK_ACQUISITION', 'AE10_REAL_ACQUISITION'], true);
        $isMock = ($snapshot['origin'] ?? null) === 'AE10_MOCK_ACQUISITION';
        if (! $isResearch && ! $isPad && ! $isWb && ! $isAcquisition && ($snapshot['origin'] ?? null) !== 'AE07_SYNTHETIC_TEMPLATE') {
            return null;
        }
        $packet = $isResearch ? ($snapshot['pipeline_snapshot'] ?? null) : null;
        if ($isResearch && ! $isFeed) {
            abort_unless(is_array($packet) && hash_equals($packet['packet_hash'], (string) $snapshot['packet_hash']), 409);
            foreach ($packet['observations'] as $observation) {
                $doc = $observation['provenance']['document'];
                $q = $doc['quote'];
                abort_unless(hash_equals($doc['body_sha256'], hash('sha256', $doc['body'])) && substr($doc['body'], $q['byte_start'], $q['byte_end'] - $q['byte_start']) === $q['text'], 409);
            }
        }
        if ($isFeed) {
            abort_unless(is_array($packet) && hash_equals((string) $packet['packet_hash'], (string) $snapshot['packet_hash']) && hash_equals($packet['packet_hash'], app(MultiSourceResearchWorkspace::class)->digest($packet['feed_envelope'] ?? [])), 409, 'Feed provenance changed.');
            $entries = array_column($packet['feed_envelope']['entries'], null, 'id');
            foreach ($packet['observations'] as $observation) {
                $item = $observation['provenance']['feed_item'];
                abort_unless(isset($entries[$item['id']]) && hash_equals(app(MultiSourceResearchWorkspace::class)->digest($item), app(MultiSourceResearchWorkspace::class)->digest($entries[$item['id']])) && ($item['binding']['owner_id'] ?? null) === $candidate->user_id, 409, 'Feed item changed.');
            }
            // Derive exact duplicate hits from immutable selected-item lineage; republication pairs are not duplicate counts.
            $packet['duplicates'] = [];
            foreach ($packet['observations'] as $observation) {
                foreach (array_slice($observation['provenance']['feed_item']['lineage'], 1) as $hit) {
                    $packet['duplicates'][] = ['item_id' => $observation['id'], 'duplicate_lineage' => $hit];
                }
            }
        }
        if ($isAcquisition) {
            $saved = $snapshot['pipeline_snapshot'] ?? null;
            abort_unless(is_array($saved) && hash_equals((string) $saved['packet_hash'], (string) $snapshot['packet_hash']), 409, 'Acquisition provenance unavailable.');
            $packet = $saved; // historical immutable creation snapshot survives receipt retention expiry
        }
        if ($isWb) {
            $saved = $snapshot['pipeline_snapshot'] ?? null;
            $packet = app(WorldBankGdpSourceAdapter::class)->snapshot();
            abort_unless(is_array($saved) && hash_equals($packet['packet_hash'], (string) ($snapshot['packet_hash'] ?? '')), 409, 'Archive provenance changed.');
            $packet = $saved; // immutable creation audit, including original rights/freshness context
        }
        if ($isPad) {
            $saved = $snapshot['pipeline_snapshot'] ?? null;
            abort_unless(is_array($saved) && is_array($saved['import_packet'] ?? null), 409, 'Import provenance unavailable.');
            $time = strtotime($saved['source']['fetched_at'] ?? '');
            abort_unless($time !== false, 409, 'Import timestamp unavailable.');
            $packet = app(YogyakartaPadSourceAdapter::class)->normalize($saved['import_packet'], $time);
            abort_unless($packet['status'] === 'READY_FOR_ANALYST_DRAFT' && hash_equals($packet['packet_hash'], (string) $snapshot['packet_hash']), 409, 'Import provenance changed.');
            $packet['source'] = $saved['source'];
            $packet['history'] = $saved['history'] ?? null;
        }
        if (! $isResearch && ! $isPad && ! $isWb && ! $isAcquisition) {
            foreach (array_keys(self::SCENARIOS) as $scenario) {
                if (in_array($scenario, ['research_fixture', 'feed_fixture', 'wb_research_acquisition'], true)) {
                    continue;
                }
                $preview = $this->preview($scenario);
                if (hash_equals($preview['packet_hash'], (string) ($snapshot['packet_hash'] ?? ''))) {
                    $packet = $preview;
                    break;
                }
            }
        }
        abort_unless($packet !== null && $packet['status'] === 'READY_FOR_ANALYST_DRAFT', 409, 'Fixture provenance unavailable.');
        $links = [];
        foreach ($packet['observations'] as $observation) {
            $rows = DB::table('sifi_evidence_drafts as e')->where('e.candidate_id', $candidate->id)
                ->where('e.owner_user_id', $candidate->user_id)->where('e.signal_id', $observation['id'])
                ->leftJoin('sifi_claim_drafts as c', function ($join) use ($candidate) {
                    $join->on('c.evidence_id', '=', 'e.id')->where('c.candidate_id', $candidate->id)->where('c.owner_user_id', $candidate->user_id);
                })->orderBy('e.id')->orderBy('c.id')->get(['e.id as evidence_id', 'e.claim_text', 'e.known_gaps', 'e.workflow_state', 'c.id as claim_id', 'c.statement', 'c.verification_state']);
            $links[] = ['observation' => $observation, 'draft_links' => $rows->all()];
        }

        return ['mode' => $isAcquisition ? $packet['mode'] : ($isWb ? 'REAL_PUBLIC_ARCHIVE' : 'SYNTHETIC_FIXTURE_ONLY'), 'source' => $packet['source'], 'packet_hash' => $packet['packet_hash'],
            'candidate_reason' => $packet['candidate_reason'], 'duplicates' => $packet['duplicates'], 'conflicts' => $packet['conflicts'],
            'changes' => $packet['changes'], 'records' => $links, 'analyst_note_at_creation' => $snapshot['analyst_note'] ?? '',
            ...($isResearch ? ($isFeed ? ['connector' => 'feed_fixture', 'research_context' => 'AE14_FEED_METADATA_NOT_DOCUMENT_NOT_AQ01_CUSTODY', 'feed_envelope_hash' => $packet['packet_hash']] : ['connector' => 'research_fixture', 'research_context' => 'AE14_RESEARCH_FIXTURE_NOT_AQ01_CUSTODY', 'document_hash' => $packet['packet_hash']]) : []),
            'formal_review_performed' => false, 'reference_namespace' => $isAcquisition ? ($isMock ? 'AE10_MOCK_NOT_AQ01_CUSTODY' : 'AE10_PUBLIC_SOURCE_NOT_AQ01_CUSTODY') : ($isWb ? 'AE09_PUBLIC_ARCHIVE_NOT_AQ01_CUSTODY' : ($isPad ? 'AE08_LOCAL_SYNTHETIC_NOT_AQ01_CUSTODY' : 'AE07_LOCAL_SYNTHETIC_NOT_AQ01_CUSTODY')),
            ...(($isPad || $isWb || $isAcquisition) ? ['connector' => $isAcquisition ? 'wb_acquisition' : ($isWb ? 'wb_gdp' : 'yk_pad'), 'history' => $packet['history'] ?? null, 'pages_processed' => $packet['pages_processed']] : [])];
    }
}
