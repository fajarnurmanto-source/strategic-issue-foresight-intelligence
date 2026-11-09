<?php

namespace App\Services\Sifi;

use App\Services\Sifi\Integration\AntaraLocalPermission;
use App\Services\Sifi\Integration\CrossrefLocalPermission;
use App\Services\Sifi\Integration\GdeltLocalPermission;
use App\Services\Sifi\Integration\BlueskyLocalPermission;
use App\Services\Sifi\Integration\GoogleTrendsLocalPermission;

/**
 * Canonical, read-only provider taxonomy for E4 and Source & Coverage.
 *
 * Catalogued != connected; a reviewed connector != an E4 historical adapter.
 * Adding a row here NEVER grants network access, provider rights, or evidence.
 */
final class SourceModuleCatalog
{
    public function forE4(string $endDate): array
    {
        $today = now('Asia/Jakarta')->toDateString();
        $crossref = app(CrossrefLocalPermission::class)->eligible();
        $gdelt = app(GdeltLocalPermission::class)->eligible();
        $bluesky = $this->permissionPasses(BlueskyLocalPermission::class);
        $googleTrends = $this->permissionPasses(GoogleTrendsLocalPermission::class);
        $antara = false;
        try {
            app(AntaraLocalPermission::class)->assertAllowed();
            $antara = true;
        } catch (\RuntimeException) {
            // Rights / runtime limitations remain visible as source status.
        }
        $live = config('sifi_e4.live_enabled') === true
            && app()->environment(['local', 'testing'])
            && config('database.default') === 'sqlite'
            && ($db = config('database.connections.sqlite.database'))
            && ($db === ':memory:' || (is_string($db)
                && preg_match('#\A/mnt/d/Research/SIFI/05_VALIDATION/[^\r\n]+\.sqlite\z#D', $db) === 1));

        $modules = [
            $this->module('MEDIA_ONLINE', 'Media online & RSS', 'Situs berita, redaksi, RSS/Atom dan portal media.', [
                $this->source('ANTARA_RSS', 'ANTARA News', 'NEWS',
                    ! $antara ? 'PERMISSION_DISABLED' : ($endDate !== $today ? 'HISTORICAL_COVERAGE_UNAVAILABLE' : 'ELIGIBLE_LATEST_ONLY'),
                    'LATEST_FEED_NO_ARCHIVE', $antara && $live && $endDate === $today,
                    'REVIEWED_OPT_IN', 'PILOT_E4', 'RSS terbaru; tidak membuktikan arsip historis.'),
                $this->source('MEDIA_RSS_ATOM', 'RSS / Atom media lain', 'NEWS',
                    'ADAPTER_NOT_IMPLEMENTED', 'FEED_PER_PUBLISHER_REQUIRED', false,
                    'NOT_CONNECTED', 'PLANNED', 'Registrasi feed, pemeriksaan robots/ketentuan, deduplikasi.'),
                $this->source('GOOGLE_NEWS_RSS', 'Google News RSS', 'NEWS_AGGREGATOR',
                    'RIGHTS_REVIEW_REQUIRED', 'FEED_NOT_HISTORICAL_ARCHIVE', false,
                    'NOT_CONNECTED', 'PLANNED', 'Aggregator; bukan lisensi akses konten penerbit.'),
            ]),
            $this->module('GDELT', 'GDELT & indeks berita', 'Indeks publikasi lintas negara; indeksasi tidak setara asal berita independen.', [
                $this->source('GDELT_DOC', 'GDELT DOC API', 'NEWS_INDEX',
                    'PERIOD_BACKFILL_NOT_IN_E4', 'HISTORICAL_REVIEW_REQUIRED', false,
                    'REVIEWED_OPT_IN', 'PILOT_OUTSIDE_E4', 'Konektor satu kali sudah ada; adapter rentang tanggal E4 belum diterima.'),
            ]),
            $this->module('WEB_SEARCH', 'Mesin pencari & penelusuran web', 'Hasil pencarian/indeks web; bukan sensor opini atau jaminan cakupan semua halaman.', [
                $this->source('GOOGLE_WEB_SEARCH', 'Google Search', 'WEB_SEARCH',
                    'API_ACCESS_NOT_CONFIGURED', 'SEARCH_API_LICENSE_AND_QUOTA_REQUIRED', false,
                    'NOT_CONNECTED', 'PLANNED', 'Jangan scraping halaman hasil pencarian; API resmi memerlukan akses sesuai ketentuan.'),
                $this->source('BING_WEB_SEARCH', 'Bing Search', 'WEB_SEARCH',
                    'API_ACCESS_NOT_CONFIGURED', 'SEARCH_API_LICENSE_AND_QUOTA_REQUIRED', false,
                    'NOT_CONNECTED', 'PLANNED', 'Perlu pemeriksaan produk API, kredensial, dan biaya.'),
                $this->source('BRAVE_SEARCH_API', 'Brave Search API', 'WEB_SEARCH',
                    'API_ACCESS_NOT_CONFIGURED', 'SEARCH_API_LICENSE_AND_QUOTA_REQUIRED', false,
                    'NOT_CONNECTED', 'PLANNED', 'Kandidat setelah evaluasi lisensi dan batas gratis aktual.'),
                $this->source('TAVILY_KEYLESS', 'Tavily / keyless candidate', 'WEB_SEARCH',
                    'CONNECTOR_NOT_ADMITTED_E4', 'UNREVIEWED_SEARCH_CANDIDATE', false,
                    'CANDIDATE_ONLY', 'OTHER_WORKFLOW', 'Terdaftar dalam riset internal; bukan izin pencarian web umum.'),
            ]),
            $this->module('SOCIAL_MEDIA', 'Media sosial', 'Percakapan publik sesuai ruang lingkup API dan persetujuan tiap platform.', [
                $this->source('BLUESKY_PUBLIC_SEARCH', 'Bluesky', 'SOCIAL',
                    'HISTORICAL_COVERAGE_NOT_ESTABLISHED', 'PUBLIC_SEARCH_NOT_BACKFILL', false,
                    'REVIEWED_OPT_IN', 'PILOT_OUTSIDE_E4', 'Konektor metadata publik satu kali; tidak menjanjikan arsip.'),
                $this->source('MASTODON_PUBLIC', 'Mastodon', 'SOCIAL',
                    'ADAPTER_NOT_IMPLEMENTED', 'INSTANCE_SCOPED_PUBLIC_API', false,
                    'NOT_CONNECTED', 'PLANNED', 'Cakupan terbatas per-instance dan aturan server.'),
                $this->source('INSTAGRAM_GRAPH', 'Instagram', 'SOCIAL',
                    'PLATFORM_PERMISSION_REQUIRED', 'OFFICIAL_API_LIMITED_SCOPE', false,
                    'NOT_CONNECTED', 'PLANNED', 'Memerlukan Meta API dan izin akun/fitur yang relevan.'),
                $this->source('FACEBOOK_GRAPH', 'Facebook', 'SOCIAL',
                    'PLATFORM_PERMISSION_REQUIRED', 'OFFICIAL_API_LIMITED_SCOPE', false,
                    'NOT_CONNECTED', 'PLANNED', 'Tidak mengizinkan pencarian semua unggahan warga.'),
                $this->source('TIKTOK_API', 'TikTok', 'SOCIAL',
                    'PLATFORM_PERMISSION_REQUIRED', 'APPROVED_API_SCOPE_ONLY', false,
                    'NOT_CONNECTED', 'PLANNED', 'Persyaratan API dan hak akses sesuai tujuan penggunaan.'),
                $this->source('X_API', 'X / Twitter', 'SOCIAL',
                    'PLATFORM_PERMISSION_REQUIRED', 'API_TIER_AND_QUOTA_DEPENDENT', false,
                    'NOT_CONNECTED', 'PLANNED', 'Akses pencarian tergantung tingkat API dan kuota.'),
                $this->source('YOUTUBE_DATA', 'YouTube', 'SOCIAL_VIDEO',
                    'PLATFORM_PERMISSION_REQUIRED', 'OFFICIAL_API_QUOTA_DEPENDENT', false,
                    'NOT_CONNECTED', 'PLANNED', 'Metadata video/kanal, bukan semua komentar dan riwayat.'),
                $this->source('THREADS_API', 'Threads', 'SOCIAL',
                    'PLATFORM_PERMISSION_REQUIRED', 'OFFICIAL_API_LIMITED_SCOPE', false,
                    'NOT_CONNECTED', 'PLANNED', 'Tinjau dukungan pencarian publik dan izin Meta yang berlaku.'),
                $this->source('LINKEDIN_API', 'LinkedIn', 'SOCIAL_PROFESSIONAL',
                    'PLATFORM_PERMISSION_REQUIRED', 'RESTRICTED_API_SCOPE', false,
                    'NOT_CONNECTED', 'PLANNED', 'Tidak tersedia untuk harvesting posting umum tanpa izin.'),
            ]),
            $this->module('RESEARCH', 'Riset & publikasi ilmiah', 'Metadata penelitian; tidak mengukur jumlah kejadian atau opini publik.', [
                $this->source('CROSSREF', 'Crossref', 'RESEARCH',
                    $crossref ? 'ELIGIBLE' : 'PERMISSION_DISABLED', 'HISTORICAL_PUBLISHED_DATE_FILTER',
                    $crossref && $live, 'REVIEWED_OPT_IN', 'PILOT_E4', 'REST metadata publik; tanggal terbit dapat difilter.'),
                $this->source('OPENALEX', 'OpenAlex', 'RESEARCH',
                    'ADAPTER_NOT_IMPLEMENTED', 'PUBLIC_RESEARCH_METADATA', false,
                    'NOT_CONNECTED', 'PLANNED', 'Kandidat API metadata ilmiah.'),
                $this->source('ARXIV', 'arXiv', 'RESEARCH',
                    'ADAPTER_NOT_IMPLEMENTED', 'PREPRINT_METADATA', false,
                    'NOT_CONNECTED', 'PLANNED', 'Kandidat feed/API dengan batas penggunaan.'),
                $this->source('WIKIPEDIA_ID', 'Wikipedia', 'ENCYCLOPEDIA',
                    'CONNECTOR_NOT_ADMITTED_E4', 'REFERENCE_NOT_EVENT_STREAM', false,
                    'EXISTING_OTHER_WORKFLOW', 'OTHER_WORKFLOW', 'Referensi latar; bukan publikasi berita historis.'),
            ]),
            $this->module('SEARCH_TRENDS', 'Tren & perhatian pencarian', 'Indikator atensi; bukan hasil Google Search, prevalensi, atau sentimen.', [
                $this->source('GOOGLE_TRENDS_RSS', 'Google Trends RSS', 'ATTENTION',
                    'ATTENTION_ONLY_NO_HISTORIC_POSTS', 'LATEST_TRENDS_ONLY', false,
                    'REVIEWED_OPT_IN', 'PILOT_OUTSIDE_E4', 'Konektor tren Indonesia satu kali; bukan pencarian semua kata kunci.'),
            ]),
            $this->module('OFFICIAL_OPEN_DATA', 'Pemerintah, regulasi & open data', 'Sumber primer pemerintah dan indikator statistik resmi.', [
                $this->source('OFFICIAL_REGULATION_OPEN_DATA', 'Portal pemerintah & regulasi', 'OFFICIAL_OPEN_DATA',
                    'NO_REVIEWED_PERIOD_ADAPTER', 'NOT_AVAILABLE', false,
                    'NOT_CONNECTED', 'PLANNED', 'Daftar portal dan standar metadata perlu verifikasi per instansi.'),
                $this->source('BPS_DATA', 'BPS / statistik Indonesia', 'OFFICIAL_STATISTICS',
                    'ADAPTER_NOT_IMPLEMENTED', 'DATASET_RELEASE_PERIOD', false,
                    'NOT_CONNECTED', 'PLANNED', 'Perlu akses dataset/API dan versi data.'),
                $this->source('WORLD_BANK_API', 'World Bank Open Data', 'OPEN_DATA',
                    'WORKFLOW_AVAILABLE_OUTSIDE_E4', 'INDICATOR_YEAR_NOT_NEWS_DATE', false,
                    'EXISTING_OTHER_WORKFLOW', 'OTHER_WORKFLOW', 'Akuisisi satu kali sudah ada pada Source & Coverage.'),
                $this->source('LEGAL_REGULATION_INDEX', 'JDIH / peraturan', 'REGULATION',
                    'ADAPTER_NOT_IMPLEMENTED', 'PROMULGATION_OR_EFFECTIVE_DATE', false,
                    'NOT_CONNECTED', 'PLANNED', 'Perlu provenance naskah, versi, dan yurisdiksi.'),
            ]),
            $this->module('DOCUMENTS_DATASETS', 'Dokumen & dataset milik pengguna', 'Materi lokal yang diunggah secara sah; berbeda dari perayapan internet.', [
                $this->source('OWNED_DOCUMENT_UPLOAD', 'Unggah PDF / dokumen', 'DOCUMENT',
                    'WORKFLOW_AVAILABLE_OUTSIDE_E4', 'DOCUMENT_DATE_UNVERIFIED', false,
                    'EXISTING_OTHER_WORKFLOW', 'OTHER_WORKFLOW', 'Gunakan jalur evidence intake dengan pemeriksaan hak.'),
                $this->source('OWNED_DATASET_IMPORT', 'Impor CSV / JSON', 'DATASET',
                    'WORKFLOW_AVAILABLE_OUTSIDE_E4', 'DATASET_DATE_UNVERIFIED', false,
                    'EXISTING_OTHER_WORKFLOW', 'OTHER_WORKFLOW', 'Impor terkontrol; tidak melakukan pengambilan web.'),
            ]),
        ];

        // These local runtime checks do not contact a remote provider, validate
        // credentials/contact, reserve quota, or authorize a real acquisition.
        $gates = [
            'CROSSREF' => $crossref,
            'ANTARA_RSS' => $antara,
            'GDELT_DOC' => $gdelt,
            'BLUESKY_PUBLIC_SEARCH' => $bluesky,
            'GOOGLE_TRENDS_RSS' => $googleTrends,
        ];
        foreach ($modules as &$module) {
            foreach ($module['sources'] as &$source) {
                $source['permission_gate'] = array_key_exists($source['id'], $gates)
                    ? ($gates[$source['id']] ? 'LOCAL_GATE_PASSED' : 'LOCAL_GATE_DENIED')
                    : 'NOT_APPLICABLE';
            }
            unset($source);
        }
        unset($module);

        $sources = array_merge([], ...array_map(static fn (array $module): array => $module['sources'], $modules));

        return [
            'schema' => 'sifi.source-modules.v1',
            'modules' => $modules,
            'sources' => $sources,
            'module_count' => count($modules),
            'source_count' => count($sources),
            'e4_dispatchable_count' => count(array_filter($sources, static fn (array $source): bool => $source['dispatchable'])),
            'local_permission_pass_count' => count(array_filter($gates)),
            'external_requests' => 0,
            'rights_granted_by_catalog' => false,
            'production_admission' => 'DENIED',
            'live_enabled' => $live,
        ];
    }

    private function permissionPasses(string $permissionClass): bool
    {
        try {
            app($permissionClass)->assertAllowed();
            return true;
        } catch (\\RuntimeException) {
            return false;
        }
    }

    private function module(string $id, string $label, string $description, array $sources): array
    {
        return [
            'id' => $id,
            'label' => $label,
            'description' => $description,
            'sources' => array_map(static fn (array $source): array => [
                ...$source,
                'module_id' => $id,
                'module_label' => $label,
            ], $sources),
        ];
    }

    private function source(
        string $id, string $platform, string $class, string $state, string $coverage,
        bool $dispatchable, string $connection, string $workflow, string $notes
    ): array {
        return compact('id', 'platform', 'class', 'state', 'coverage', 'dispatchable',
            'connection', 'workflow', 'notes');
    }
}
