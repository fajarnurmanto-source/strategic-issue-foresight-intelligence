<?php

namespace App\Services\Sifi\Integration;

use App\Models\SifiMonitoringObject;
use App\Models\User;
use App\Services\Sifi\MultiSourceResearchWorkspace;
use App\Services\Sifi\ResearchRetrievalWorkspace;
use App\Services\Sifi\ResearchSessionWorkspace;
use App\Services\Sifi\ResearchSignalInsights;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Publisher-provided RSS as independent news metadata source, not GDELT fallback endpoint. */
final class ReviewedAntaraRss
{
    /**
     * @param array<string, mixed> $dateWindow
     * @param array<array-key, mixed> $keywords
     * @return array<string, mixed>
     */
    public function collect(User $owner, SifiMonitoringObject $object, string $contact, array $dateWindow = [], array $keywords = []): array
    {
        app(AntaraLocalPermission::class)->assertAllowed();
        app(AntaraLocalPermission::class)->assertContact($contact);
        if (! $owner->exists || ! $owner->hasVerifiedEmail()) {
            throw new \RuntimeException('IH03E_OWNER_NOT_VERIFIED');
        }
        Gate::forUser($owner)->authorize('update', $object);

        return DB::transaction(function () use ($owner, $object, $contact, $dateWindow, $keywords): array {
            User::query()->whereKey($owner->id)->lockForUpdate()->firstOrFail();
            $locked = SifiMonitoringObject::query()->whereKey($object->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($owner)->authorize('update', $locked);
            if ((int) $locked->user_id !== (int) $owner->id || $locked->state !== 'CONFIGURED'
                || (int) $locked->revision !== (int) $object->revision) {
                throw new \RuntimeException('IH03E_OBJECT_CHANGED_OR_PAUSED');
            }
            app(AntaraLocalPermission::class)->assertAllowed();
            $this->reserveBudget($owner->id);
            $sessionId = app(ResearchSessionWorkspace::class)->create($owner, $locked, [
                'question' => 'What headlines are present in ANTARA Indonesia latest news RSS?',
                'object_revision' => (int) $locked->revision,
                'query_limit' => 1,
                'result_limit' => 5,
                'source_type' => 'PUBLISHER_RSS',
            ]);
            $session = app(ResearchRetrievalWorkspace::class)->session($owner, $locked, $sessionId);
            $plan = json_decode($session->plan_json, true, 32, JSON_THROW_ON_ERROR);
            $binding = [
                'owner_id' => $owner->id,
                'object_id' => $locked->id,
                'session_id' => $sessionId,
                'object_revision' => $locked->revision,
                'plan_sha256' => hash('sha256', json_encode($plan, JSON_THROW_ON_ERROR)),
            ];

            $packet = app(AntaraRssTransport::class)->fetch($contact);
            $entries = $packet['entries'];
            $feedCount = count($entries);
            if ($dateWindow !== []) {
                if (array_keys($dateWindow) !== ['start', 'end'] || $keywords === []) {
                    throw new \RuntimeException('E4_ANTARA_SCOPE_REQUIRED');
                }
                $entries = array_values(array_filter($entries, static function (array $entry) use ($dateWindow, $keywords): bool {
                    $published = substr((string) ($entry['published_at'] ?? ''), 0, 10);
                    if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/D', $published) !== 1
                        || $published < $dateWindow['start'] || $published >= $dateWindow['end']) {
                        return false;
                    }
                    foreach ($keywords as $keyword) {
                        if (is_string($keyword) && trim($keyword) !== ''
                            && mb_stripos((string) ($entry['title'] ?? ''), trim($keyword)) !== false) {
                            return true;
                        }
                    }
                    return false;
                }));
            }
            foreach ($entries as &$entry) {
                $entry['lineage'][0]['response_sha256'] = $packet['response_sha256'];
                $entry['lineage'][0]['binding_sha256'] = hash('sha256', json_encode($binding, JSON_THROW_ON_ERROR));
            }
            unset($entry);
            $envelope = [
                'version' => 'sifi-antara-rss-metadata-1',
                'mode' => 'APPROVED_LIVE_CORPUS_METADATA',
                'entries' => $entries,
                'comparisons' => [],
                'calls' => [[
                    'provider' => 'ANTARA_RSS',
                    'state' => $packet['state'],
                    'network_requests' => $packet['network_requests'],
                    'response_bytes' => $packet['response_bytes'],
                    'response_sha256' => $packet['response_sha256'],
                    'http_status' => $packet['http_status'],
                    'failure_category' => $packet['failure_category'] ?? null,
                    'elapsed_ms' => $packet['elapsed_ms'],
                ]],
                'provider_ids' => ['ANTARA_RSS'],
                'plan_sha256' => $binding['plan_sha256'],
                'binding_sha256' => hash('sha256', json_encode($binding, JSON_THROW_ON_ERROR)),
                'budget' => ['network_request_limit' => 1, 'result_limit' => 5, 'cost_limit' => 0],
                'usage' => [
                    'network_requests' => $packet['network_requests'],
                    'documents_retrieved' => 0,
                    'cost' => 0,
                ],
                'raw_result_count' => $feedCount,
                'unique_result_count' => count($entries),
                'stopping_reason' => $packet['state'] === 'OK'
                    ? ($entries ? 'COMPLETED' : 'NO_RESULTS') : 'NO_RESULTS_PROVIDER_ERRORS',
                'coverage' => $dateWindow !== []
                    ? 'E4: latest available publisher RSS only; headline keyword and requested publication date bounded; not an archive or prevalence.'
                    : 'Five most recent available publisher RSS links; not keyword search or prevalence.',
                'e4_date_window' => $dateWindow ?: null,
                'independence' => 'NOT_VERIFIED',
                'claim_verification' => 'NOT_PERFORMED_METADATA_ONLY',
                'ai' => 'OFF',
                'fixture_scenario' => null,
            ];
            $envelope['insights'] = app(ResearchSignalInsights::class)->summarize($envelope);
            $digest = app(MultiSourceResearchWorkspace::class)->digest($envelope);
            $results = json_decode($session->results_json, true, 32, JSON_THROW_ON_ERROR);
            $results['multi_source'] = [
                'state' => 'DRAFT', 'envelope' => $envelope,
                'envelope_sha256' => $digest, 'saved_at' => null,
                'analyst_note' => null, 'selected_ids' => [],
            ];
            DB::table('sifi_research_sessions')->where('id', $sessionId)->update([
                'results_json' => json_encode($results, JSON_THROW_ON_ERROR),
                'stopping_reason' => $envelope['stopping_reason'],
                'updated_at' => now(),
            ]);
            $projected = app(ConnectorObservationProjector::class)->record($owner, $locked, $sessionId, $envelope);
            DB::table('sifi_research_session_audit')->insert([
                'session_id' => $sessionId,
                'actor_user_id' => $owner->id,
                'action' => 'IH03E_ANTARA_RSS_RECEIPT_SAVED',
                'snapshot_json' => json_encode([
                    'envelope_sha256' => $digest,
                    'state' => $packet['state'],
                    'news_links' => count($entries),
                    'evidence_admission' => 'NONE',
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);

            return [
                'session_id' => $sessionId, 'state' => $packet['state'],
                'news_links' => count($entries), 'shadow_observations' => $projected,
                'network_requests' => $packet['network_requests'],
                'failure_category' => $packet['failure_category'] ?? null,
                'http_status' => $packet['http_status'],
            ];
        });
    }

    private function reserveBudget(int $ownerId): void
    {
        $directory = storage_path('app/private/sifi-ih03e');
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new \RuntimeException('IH03E_BUDGET_STORAGE_UNAVAILABLE');
        }
        $path = $directory.'/antara-budget.enc';
        if (is_link($path)) {
            throw new \RuntimeException('IH03E_BUDGET_SYMLINK_DENIED');
        }
        $handle = fopen($path, 'c+');
        if ($handle === false || ! flock($handle, LOCK_EX | LOCK_NB)) {
            throw new \RuntimeException('IH03E_BUDGET_BUSY');
        }
        try {
            $raw = stream_get_contents($handle);
            $budget = $raw === '' ? ['day' => '', 'count' => 0, 'last' => 0]
                : json_decode(Crypt::decryptString($raw), true, 12, JSON_THROW_ON_ERROR);
            $today = now('Asia/Jakarta')->toDateString();
            $count = ($budget['day'] ?? '') === $today ? (int) $budget['count'] : 0;
            if ($count >= (int) config('sifi_ih03e.max_requests_per_day', 2)
                || microtime(true) - (float) ($budget['last'] ?? 0)
                    < (int) config('sifi_ih03e.min_interval_seconds', 120)) {
                throw new \RuntimeException('IH03E_LOCAL_RATE_LIMIT');
            }
            $cipher = Crypt::encryptString(json_encode([
                'day' => $today, 'count' => $count + 1, 'last' => microtime(true),
                'owner_id_sha256' => hash('sha256', (string) $ownerId),
            ], JSON_THROW_ON_ERROR));
            rewind($handle);
            if (fwrite($handle, $cipher) !== strlen($cipher)
                || ! ftruncate($handle, strlen($cipher)) || ! fflush($handle)) {
                throw new \RuntimeException('IH03E_BUDGET_WRITE_FAILED');
            }
            chmod($path, 0600);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
