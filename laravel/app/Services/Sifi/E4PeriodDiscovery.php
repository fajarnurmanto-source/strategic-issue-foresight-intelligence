<?php

namespace App\Services\Sifi;

use App\Models\SifiMonitoringObject;
use App\Models\User;
use App\Services\Sifi\Integration\AntaraLocalPermission;
use App\Services\Sifi\Integration\CrossrefLocalPermission;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * E4: object-registered, date-bound source discovery plan.
 * Read-only planning, never a grant to use any remote provider.
 */
final class E4PeriodDiscovery
{
    /** @return array<string, mixed> */
    public function plan(User $owner, SifiMonitoringObject $object, string $start, string $end): array
    {
        Gate::forUser($owner)->authorize('view', $object);
        if ((int) $object->user_id !== (int) $owner->id
            || $object->state !== 'CONFIGURED') {
            throw ValidationException::withMessages(['object_id' => 'Objek bukan milik akun atau tidak aktif.']);
        }

        $keywords = array_values(array_filter($object->keywords_json ?? [],
            static fn ($keyword) => is_string($keyword) && trim($keyword) !== ''));
        if ($keywords === []) {
            throw ValidationException::withMessages(['object_id' => 'Daftarkan sedikitnya satu keyword pada objek sebelum memilih periode.']);
        }

        foreach (['start_date' => $start, 'end_date' => $end] as $field => $value) {
            if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/D', $value) !== 1
                || ! checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4))) {
                throw ValidationException::withMessages([$field => 'Tanggal harus valid dan berformat YYYY-MM-DD.']);
            }
        }
        $today = now('Asia/Jakarta')->toDateString();
        $days = (new \DateTimeImmutable($start))->diff(new \DateTimeImmutable($end))->days;
        if ($start > $end || $end > $today || $days > 365) {
            throw ValidationException::withMessages(['end_date' => 'Periode historis harus berurutan, tidak melampaui hari ini, dan maksimal 366 hari inklusif.']);
        }

        $query = trim(implode(' ', array_slice($keywords, 0, 6)));
        if (mb_strlen($query) < 8) {
            $query = trim($object->name.' '.$query);
        }
        $query = mb_substr($query, 0, 200);
        $antara = false;
        try {
            app(AntaraLocalPermission::class)->assertAllowed();
            $antara = true;
        } catch (\RuntimeException) {
            // Availability is a source-state, not a reason to bypass restrictions.
        }
        $crossref = app(CrossrefLocalPermission::class)->eligible();
        $live = config('sifi_e4.live_enabled') === true
            && app()->environment(['local', 'testing'])
            && config('database.default') === 'sqlite'
            && ($db = config('database.connections.sqlite.database'))
            && ($db === ':memory:' || (is_string($db)
                && preg_match('#\A/mnt/d/Research/SIFI/05_VALIDATION/[^\r\n]+\.sqlite\z#D', $db) === 1));

        $sources = [
            ['id' => 'CROSSREF', 'class' => 'RESEARCH', 'platform' => 'Crossref', 'state' => $crossref ? 'ELIGIBLE' : 'PERMISSION_DISABLED', 'coverage' => 'HISTORICAL_PUBLISHED_DATE_FILTER', 'dispatchable' => $crossref && $live],
            ['id' => 'ANTARA_RSS', 'class' => 'NEWS', 'platform' => 'ANTARA News', 'state' => ! $antara ? 'PERMISSION_DISABLED' : ($end !== $today ? 'HISTORICAL_COVERAGE_UNAVAILABLE' : 'ELIGIBLE_LATEST_ONLY'), 'coverage' => 'LATEST_FEED_NO_ARCHIVE', 'dispatchable' => $antara && $live && $end === $today],
            ['id' => 'GDELT_DOC', 'class' => 'NEWS_INDEX', 'platform' => 'GDELT', 'state' => 'PERIOD_BACKFILL_NOT_IN_E4', 'coverage' => 'HISTORICAL_REVIEW_REQUIRED', 'dispatchable' => false],
            ['id' => 'BLUESKY_PUBLIC_SEARCH', 'class' => 'SOCIAL', 'platform' => 'Bluesky', 'state' => 'HISTORICAL_COVERAGE_NOT_ESTABLISHED', 'coverage' => 'PUBLIC_SEARCH_NOT_BACKFILL', 'dispatchable' => false],
            ['id' => 'GOOGLE_TRENDS_RSS', 'class' => 'ATTENTION', 'platform' => 'Google Trends', 'state' => 'ATTENTION_ONLY_NO_HISTORIC_POSTS', 'coverage' => 'LATEST_TRENDS_ONLY', 'dispatchable' => false],
            ['id' => 'OFFICIAL_REGULATION_OPEN_DATA', 'class' => 'OFFICIAL_OPEN_DATA', 'platform' => 'Official/Regulation/Open Data', 'state' => 'NO_REVIEWED_PERIOD_ADAPTER', 'coverage' => 'NOT_AVAILABLE', 'dispatchable' => false],
        ];

        return [
            'schema' => 'sifi.e4.period-source-plan.v1',
            'object_id' => $object->id,
            'object_name' => $object->name,
            'object_revision' => (int) $object->revision,
            'keyword_count' => count($keywords),
            'keyword_scope_sha256' => hash('sha256', json_encode($keywords, JSON_THROW_ON_ERROR)),
            'query' => $query,
            'start_date' => $start,
            'end_date' => $end,
            'end_exclusive' => (new \DateTimeImmutable($end))->modify('+1 day')->format('Y-m-d'),
            'sources' => $sources,
            'eligible_dispatch_count' => count(array_filter($sources, static fn ($s) => $s['dispatchable'])),
            'live_enabled' => $live,
            'source_coverage' => 'PARTIAL_PER_SOURCE_RIGHTS_AND_HISTORICAL_AVAILABILITY',
            'production_admission' => 'DENIED',
            'evidence_admission' => 'NONE',
        ];
    }
}
