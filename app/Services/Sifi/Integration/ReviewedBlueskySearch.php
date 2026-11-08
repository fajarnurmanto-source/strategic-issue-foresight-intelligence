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

/** Bounded public social search; no login, fulltext retention or per-post approvals. */
final class ReviewedBlueskySearch
{
    public function collect(User $owner, SifiMonitoringObject $object, string $query, string $contact): array
    {
        $permission = app(BlueskyLocalPermission::class);
        $permission->assertAllowed();
        $permission->assertContact($contact);
        $permission->assertQuery($query);
        if (! $owner->exists || ! $owner->hasVerifiedEmail()) {
            throw new \RuntimeException('SIFI_SOCIAL_OWNER_NOT_VERIFIED');
        }
        Gate::forUser($owner)->authorize('update', $object);

        return DB::transaction(function () use ($owner, $object, $query, $contact): array {
            User::query()->whereKey($owner->id)->lockForUpdate()->firstOrFail();
            $locked = SifiMonitoringObject::query()->whereKey($object->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($owner)->authorize('update', $locked);
            if ((int) $locked->user_id !== (int) $owner->id || $locked->state !== 'CONFIGURED'
                || (int) $locked->revision !== (int) $object->revision) {
                throw new \RuntimeException('SIFI_SOCIAL_OBJECT_CHANGED_OR_PAUSED');
            }
            $terms = array_values(array_filter([
                $locked->name,
                ...($locked->keywords_json ?? []),
                ...($locked->aliases_json ?? []),
            ], 'is_string'));
            if (! in_array($query, $terms, true)) {
                throw new \RuntimeException('SIFI_SOCIAL_QUERY_NOT_BOUND_TO_MONITORING_OBJECT');
            }
            app(BlueskyLocalPermission::class)->assertAllowed();
            $this->reserveBudget($owner->id);

            $sessionId = app(ResearchSessionWorkspace::class)->create($owner, $locked, [
                'question' => 'Which recent public Bluesky posts match the configured term: '.$query.'?',
                'object_revision' => (int) $locked->revision,
                'query_limit' => 1,
                'result_limit' => 10,
                'source_type' => 'PUBLIC_SOCIAL_SEARCH',
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

            $packet = app(BlueskySearchTransport::class)->fetch($query, $contact);
            $history = app(SocialWatchHistory::class)->classify($owner->id, $locked, $packet['entries']);
            $entries = $history['entries'];
            foreach ($entries as &$entry) {
                $entry['lineage'][0]['response_sha256'] = $packet['response_sha256'];
                $entry['lineage'][0]['binding_sha256'] = hash('sha256', json_encode($binding, JSON_THROW_ON_ERROR));
            }
            unset($entry);

            $envelope = [
                'version' => 'sifi-bluesky-public-search-metadata-1',
                'mode' => 'APPROVED_LIVE_CORPUS_METADATA',
                'entries' => $entries,
                'social_watch_history' => $history['summary'],
                'comparisons' => [],
                'calls' => [[
                    'provider' => 'BLUESKY_PUBLIC_SEARCH',
                    'state' => $packet['state'],
                    'network_requests' => $packet['network_requests'],
                    'response_bytes' => $packet['response_bytes'],
                    'response_sha256' => $packet['response_sha256'],
                    'http_status' => $packet['http_status'],
                    'failure_category' => $packet['failure_category'] ?? null,
                    'elapsed_ms' => $packet['elapsed_ms'],
                ]],
                'provider_ids' => ['BLUESKY_PUBLIC_SEARCH'],
                'plan_sha256' => $binding['plan_sha256'],
                'binding_sha256' => hash('sha256', json_encode($binding, JSON_THROW_ON_ERROR)),
                'budget' => ['network_request_limit' => 1, 'result_limit' => 10, 'cost_limit' => 0],
                'usage' => [
                    'network_requests' => $packet['network_requests'],
                    'documents_retrieved' => 0,
                    'cost' => 0,
                ],
                'raw_result_count' => count($entries),
                'unique_result_count' => count($entries),
                'stopping_reason' => $packet['state'] === 'OK'
                    ? ($entries ? 'COMPLETED' : 'NO_RESULTS') : 'NO_RESULTS_PROVIDER_ERRORS',
                'coverage' => 'One latest-sort Bluesky query, at most ten posts; not exhaustive, independent origin or prevalence.',
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
            $observations = app(ConnectorObservationProjector::class)->record($owner, $locked, $sessionId, $envelope);
            DB::table('sifi_research_session_audit')->insert([
                'session_id' => $sessionId,
                'actor_user_id' => $owner->id,
                'action' => 'SIFI_SOCIAL_BLUESKY_METADATA_RECEIPT_SAVED',
                'snapshot_json' => json_encode([
                    'envelope_sha256' => $digest,
                    'query_sha256' => hash('sha256', $query),
                    'state' => $packet['state'],
                    'public_post_links' => count($entries),
                    'first_observed_in_local_history' => $history['summary']['first_observed_count'],
                    'previously_observed_in_local_history' => $history['summary']['previously_observed_count'],
                    'evidence_admission' => 'NONE',
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);

            return [
                'session_id' => $sessionId,
                'state' => $packet['state'],
                'post_links' => count($entries),
                'shadow_observations' => $observations,
                'network_requests' => $packet['network_requests'],
                'failure_category' => $packet['failure_category'] ?? null,
                'http_status' => $packet['http_status'],
            ];
        });
    }

    private function reserveBudget(int $ownerId): void
    {
        $directory = storage_path('app/private/sifi-social');
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new \RuntimeException('SIFI_SOCIAL_BUDGET_STORAGE_UNAVAILABLE');
        }
        $path = $directory.'/bluesky-budget.enc';
        if (is_link($path)) {
            throw new \RuntimeException('SIFI_SOCIAL_BUDGET_SYMLINK_DENIED');
        }
        $handle = fopen($path, 'c+');
        if ($handle === false || ! flock($handle, LOCK_EX | LOCK_NB)) {
            throw new \RuntimeException('SIFI_SOCIAL_BUDGET_BUSY');
        }
        try {
            $raw = stream_get_contents($handle);
            $budget = $raw === '' ? ['day' => '', 'count' => 0, 'last' => 0]
                : json_decode(Crypt::decryptString($raw), true, 12, JSON_THROW_ON_ERROR);
            $today = now('Asia/Jakarta')->toDateString();
            $count = ($budget['day'] ?? '') === $today ? (int) $budget['count'] : 0;
            if ($count >= (int) config('sifi_social.max_requests_per_day', 3)
                || microtime(true) - (float) ($budget['last'] ?? 0)
                    < (int) config('sifi_social.min_interval_seconds', 180)) {
                throw new \RuntimeException('SIFI_SOCIAL_LOCAL_RATE_LIMIT');
            }
            $cipher = Crypt::encryptString(json_encode([
                'day' => $today,
                'count' => $count + 1,
                'last' => microtime(true),
                'owner_id_sha256' => hash('sha256', (string) $ownerId),
            ], JSON_THROW_ON_ERROR));
            rewind($handle);
            if (fwrite($handle, $cipher) !== strlen($cipher)
                || ! ftruncate($handle, strlen($cipher)) || ! fflush($handle)) {
                throw new \RuntimeException('SIFI_SOCIAL_BUDGET_WRITE_FAILED');
            }
            chmod($path, 0600);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
