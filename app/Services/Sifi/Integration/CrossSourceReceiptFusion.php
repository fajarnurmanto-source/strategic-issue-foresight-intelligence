<?php

namespace App\Services\Sifi\Integration;

use App\Models\SifiMonitoringObject;
use App\Models\User;
use App\Services\Sifi\MultiSourceResearchWorkspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Read-only: fuse one scholarly metadata receipt with one publisher RSS receipt. */
final class CrossSourceReceiptFusion
{
    private const STOP = [
        'a', 'an', 'and', 'are', 'as', 'at', 'about', 'by', 'for', 'from', 'in',
        'into', 'is', 'of', 'on', 'or', 'the', 'to', 'with', 'what', 'how',
        'dan', 'dari', 'di', 'dengan', 'ke', 'pada', 'tentang', 'untuk', 'yang',
    ];

    public function analyze(User $owner, string $crossrefId, string $antaraId): array
    {
        if (! app()->environment(['local', 'testing']) || config('database.default') !== 'sqlite') {
            throw new \RuntimeException('IH03F_LOCAL_SQLITE_ONLY');
        }
        $db = config('database.connections.sqlite.database');
        if ($db !== ':memory:' && (! is_string($db)
            || preg_match('#\A/mnt/d/Research/SIFI/05_VALIDATION/[^\r\n]+\.sqlite\z#D', $db) !== 1)) {
            throw new \RuntimeException('IH03F_LOCAL_SQLITE_ONLY');
        }
        if (! $owner->exists || ! $owner->hasVerifiedEmail() || $crossrefId === $antaraId) {
            throw new \RuntimeException('IH03F_OWNER_OR_RECEIPTS_INVALID');
        }

        $sources = [
            'CROSSREF' => $this->receipt($owner, $crossrefId, 'CROSSREF'),
            'ANTARA_RSS' => $this->receipt($owner, $antaraId, 'ANTARA_RSS'),
        ];
        if ($sources['CROSSREF']['object_id'] !== $sources['ANTARA_RSS']['object_id']
            || $sources['CROSSREF']['object_revision'] !== $sources['ANTARA_RSS']['object_revision']) {
            throw new \RuntimeException('IH03F_OBJECT_BINDING_MISMATCH');
        }

        $merged = [];
        $seen = [];
        $termCounts = [];
        foreach ($sources as $provider => $source) {
            foreach ($source['entries'] as $entry) {
                $url = $entry['canonical_url'];
                $seen[$url][$provider] = true;
                $words = $this->terms($entry['title']);
                foreach ($words as $word) {
                    $termCounts[$word] = ($termCounts[$word] ?? 0) + 1;
                }
                $merged[] = [
                    'provider' => $provider,
                    'title' => $entry['title'],
                    'canonical_url' => $url,
                    'published_at' => $entry['published_at'] ?? null,
                    'publication_precision' => $entry['publication_precision'] ?? 'UNKNOWN',
                    'evidence_admission' => 'NONE',
                ];
            }
        }
        uksort($termCounts, static fn ($a, $b) => ($termCounts[$b] <=> $termCounts[$a]) ?: strcmp($a, $b));

        $sameUrl = array_values(array_filter(array_keys($seen),
            static fn (string $url): bool => count($seen[$url]) > 1));
        sort($sameUrl, SORT_STRING);
        return [
            'schema_version' => 'sifi.ih03f.cross-source-shadow-fusion.v1',
            'mode' => 'LOCAL_READ_ONLY_EXISTING_LIVE_METADATA',
            'object_id' => $sources['CROSSREF']['object_id'],
            'owner_id' => $owner->id,
            'source_receipts' => [
                'CROSSREF' => $crossrefId,
                'ANTARA_RSS' => $antaraId,
            ],
            'source_counts' => [
                'CROSSREF' => count($sources['CROSSREF']['entries']),
                'ANTARA_RSS' => count($sources['ANTARA_RSS']['entries']),
            ],
            'total_metadata_rows' => count($merged),
            'distinct_canonical_urls' => count($seen),
            'same_url_cross_source_candidates' => $sameUrl,
            'recurring_title_terms' => array_slice($termCounts, 0, 12, true),
            'titles' => $merged,
            'temporal_comparison' => 'NOT_VALID_ACROSS_SCHOLARLY_AND_NEWS_SNAPSHOTS',
            'independent_origin_count' => null,
            'cross_platform_spread' => 'NOT_OBSERVED',
            'claim_confidence' => null,
            'news_prevalence' => null,
            'impact_severity' => null,
            'trajectory' => 'NOT_ASSESSABLE_TWO_DIFFERENT_SOURCE_TYPES',
            'ai' => 'OFF',
            'new_network_requests' => 0,
            'new_database_writes' => 0,
            'evidence_admission' => 'NONE',
            'production_admission' => 'DENIED',
        ];
    }

    private function receipt(User $owner, string $id, string $provider): array
    {
        $row = DB::table('sifi_research_sessions')->where('id', $id)
            ->where('user_id', $owner->id)->first();
        if ($row === null || now()->gte($row->expires_at)) {
            throw new \RuntimeException('IH03F_RECEIPT_NOT_AVAILABLE');
        }
        $object = SifiMonitoringObject::query()->whereKey($row->object_id)
            ->where('user_id', $owner->id)->firstOrFail();
        Gate::forUser($owner)->authorize('view', $object);
        if ($object->state !== 'CONFIGURED' || (int) $object->revision !== (int) $row->object_revision) {
            throw new \RuntimeException('IH03F_OBJECT_CHANGED_OR_PAUSED');
        }
        $results = json_decode($row->results_json, true, 32, JSON_THROW_ON_ERROR);
        $multi = $results['multi_source'] ?? null;
        $envelope = $multi['envelope'] ?? null;
        if (! is_array($envelope) || ($envelope['mode'] ?? null) !== 'APPROVED_LIVE_CORPUS_METADATA'
            || ! is_string($multi['envelope_sha256'] ?? null)
            || ! hash_equals($multi['envelope_sha256'], app(MultiSourceResearchWorkspace::class)->digest($envelope))
            || ($envelope['provider_ids'] ?? null) !== [$provider]
            || ! is_array($envelope['calls'] ?? null) || count($envelope['calls']) !== 1
            || ($envelope['calls'][0]['provider'] ?? null) !== $provider
            || ($envelope['calls'][0]['state'] ?? null) !== 'OK'
            || ! is_array($envelope['entries'] ?? null) || ! array_is_list($envelope['entries'])
            || count($envelope['entries']) > 5) {
            throw new \RuntimeException('IH03F_RECEIPT_INVALID_OR_UNREVIEWED');
        }
        $entries = [];
        foreach ($envelope['entries'] as $entry) {
            $url = $entry['canonical_url'] ?? null;
            $title = $entry['title'] ?? null;
            if (! is_string($url) || strlen($url) > 1000
                || ! str_starts_with($url, $provider === 'CROSSREF'
                    ? 'https://doi.org/' : 'https://www.antaranews.com/')
                || ! is_string($title) || trim($title) === '' || mb_strlen($title) > 300
                || ($entry['id'] ?? null) !== hash('sha256', $url)
                || ($entry['evidence_admission'] ?? null) !== 'NONE') {
                throw new \RuntimeException('IH03F_ENTRY_INTEGRITY_FAILED');
            }
            $entries[] = $entry;
        }
        return [
            'object_id' => $object->id,
            'object_revision' => (int) $object->revision,
            'entries' => $entries,
        ];
    }

    private function terms(string $title): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($title)) ?: [];
        return array_values(array_unique(array_filter($words,
            static fn (string $word): bool => mb_strlen($word) >= 4 && ! in_array($word, self::STOP, true))));
    }
}
