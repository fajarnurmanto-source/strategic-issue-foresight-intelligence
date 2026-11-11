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

/** One explicit owner-bound Google Trends receipt, with automatic relevance triage. */
final class ReviewedGoogleTrends
{
    /** @return array<string, mixed> */
    public function collect(User $owner, SifiMonitoringObject $object): array
    {
        app(GoogleTrendsLocalPermission::class)->assertAllowed();
        if (! $owner->exists || ! $owner->hasVerifiedEmail()) {
            throw new \RuntimeException('SIFI_GOOGLE_OWNER_NOT_VERIFIED');
        }
        Gate::forUser($owner)->authorize('update', $object);

        return DB::transaction(function () use ($owner, $object): array {
            User::query()->whereKey($owner->id)->lockForUpdate()->firstOrFail();
            $locked = SifiMonitoringObject::query()->whereKey($object->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($owner)->authorize('update', $locked);
            if ((int) $locked->user_id !== (int) $owner->id || $locked->state !== 'CONFIGURED'
                || (int) $locked->revision !== (int) $object->revision) {
                throw new \RuntimeException('SIFI_GOOGLE_OBJECT_CHANGED_OR_PAUSED');
            }
            $terms = array_values(array_unique(array_filter([
                $locked->name,
                ...($locked->keywords_json ?? []),
                ...($locked->aliases_json ?? []),
            ], static fn (string $t): bool => $t !== '' && mb_strlen($t) <= 80)));
            if (count($terms) > 20) {
                $terms = array_slice($terms, 0, 20);
            }
            app(GoogleTrendsLocalPermission::class)->assertAllowed();
            $this->reserveBudget($owner->id);

            $sessionId = app(ResearchSessionWorkspace::class)->create($owner, $locked, [
                'question' => 'Which top Indonesian Google search trends align with this monitoring object?',
                'object_revision' => (int) $locked->revision,
                'query_limit' => 1,
                'result_limit' => 10,
                'source_type' => 'PUBLIC_SEARCH_ATTENTION_TRENDS',
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
            // Outbound transport has ZERO arguments and a constant public URL.
            // Private watch configuration never crosses the Google boundary.
            $packet = app(GoogleTrendsRssTransport::class)->fetch();
            $localEntries = $packet['entries'];
            foreach ($localEntries as &$entry) {
                $matchedTerms = [];
                foreach ($terms as $watch) {
                    if (mb_stripos($entry['google_trend_term'], $watch) !== false) {
                        $matchedTerms[] = $watch;
                    }
                }
                $matchedTerms = array_slice($matchedTerms, 0, 10);
                $entry['watch_term_matches'] = $matchedTerms;
                $entry['watch_relevance'] = $matchedTerms
                    ? 'KEYWORD_MATCH_CANDIDATE' : 'NOT_MATCHED_TO_CURRENT_WATCH';
                $entry['relevance']['question_hits'] = $matchedTerms;
                $entry['relevance_group'] = $matchedTerms
                    ? 'PUBLIC_SEARCH_INTEREST_MATCH_UNVERIFIED' : 'GENERAL_SEARCH_TREND_ONLY';
            }
            unset($entry);
            $history = app(SocialWatchHistory::class)->classify($owner->id, $locked, $localEntries);
            $entries = $history['entries'];
            $matched = 0;
            foreach ($entries as &$entry) {
                $entry['lineage'][0]['response_sha256'] = $packet['response_sha256'];
                $entry['lineage'][0]['binding_sha256'] = hash('sha256', json_encode($binding, JSON_THROW_ON_ERROR));
                if ($entry['watch_relevance'] === 'KEYWORD_MATCH_CANDIDATE') {
                    $matched++;
                }
            }
            unset($entry);

            $envelope = [
                'version' => 'sifi-google-trends-rss-id-attention-1',
                'mode' => 'APPROVED_LIVE_CORPUS_METADATA',
                'entries' => $entries,
                'social_watch_history' => $history['summary'],
                'comparisons' => [],
                'calls' => [[
                    'provider' => 'GOOGLE_TRENDS_RSS_ID',
                    'state' => $packet['state'],
                    'network_requests' => $packet['network_requests'],
                    'response_bytes' => $packet['response_bytes'],
                    'response_sha256' => $packet['response_sha256'],
                    'http_status' => $packet['http_status'],
                    'failure_category' => $packet['failure_category'] ?? null,
                    'elapsed_ms' => $packet['elapsed_ms'],
                ]],
                'provider_ids' => ['GOOGLE_TRENDS_RSS_ID'],
                'plan_sha256' => $binding['plan_sha256'],
                'binding_sha256' => hash('sha256', json_encode($binding, JSON_THROW_ON_ERROR)),
                'budget' => ['network_request_limit' => 1, 'result_limit' => 10, 'cost_limit' => 0],
                'usage' => ['network_requests' => $packet['network_requests'], 'documents_retrieved' => 0, 'cost' => 0],
                'raw_result_count' => $packet['feed_item_count'],
                'unique_result_count' => count($entries),
                'keyword_match_candidate_count' => $matched,
                'stopping_reason' => $packet['state'] === 'OK'
                    ? ($entries ? 'COMPLETED' : 'NO_RESULTS') : 'NO_RESULTS_PROVIDER_ERRORS',
                'coverage' => 'Top ten Indonesian Trending Now RSS terms only; not Google Search results, all searches, independent news origins, public opinion, or event prevalence.',
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
                'action' => 'SIFI_GOOGLE_TRENDS_RSS_METADATA_RECEIPT_SAVED',
                'snapshot_json' => json_encode([
                    'envelope_sha256' => $digest,
                    'provider_state' => $packet['state'],
                    'feed_item_count' => $packet['feed_item_count'],
                    'terms_retained' => count($entries),
                    'watch_term_match_candidates' => $matched,
                    'independence' => 'NOT_VERIFIED',
                    'evidence_admission' => 'NONE',
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);

            return [
                'session_id' => $sessionId,
                'state' => $packet['state'],
                'feed_item_count' => $packet['feed_item_count'],
                'trend_terms' => count($entries),
                'keyword_match_candidates' => $matched,
                'shadow_observations' => $observations,
                'network_requests' => $packet['network_requests'],
                'http_status' => $packet['http_status'],
                'failure_category' => $packet['failure_category'] ?? null,
            ];
        });
    }

    private function reserveBudget(int $ownerId): void
    {
        $directory = storage_path('app/private/sifi-google');
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new \RuntimeException('SIFI_GOOGLE_BUDGET_STORAGE_UNAVAILABLE');
        }
        $path = $directory.'/trends-budget.enc';
        if (is_link($path)) {
            throw new \RuntimeException('SIFI_GOOGLE_BUDGET_SYMLINK_DENIED');
        }
        $handle = fopen($path, 'c+');
        if ($handle === false || ! flock($handle, LOCK_EX | LOCK_NB)) {
            throw new \RuntimeException('SIFI_GOOGLE_BUDGET_BUSY');
        }
        try {
            $raw = stream_get_contents($handle);
            $budget = $raw === '' ? ['day' => '', 'count' => 0, 'last' => 0]
                : json_decode(Crypt::decryptString($raw), true, 12, JSON_THROW_ON_ERROR);
            $today = now('Asia/Jakarta')->toDateString();
            $count = ($budget['day'] ?? '') === $today ? (int) $budget['count'] : 0;
            if ($count >= (int) config('sifi_google.max_requests_per_day', 3)
                || microtime(true) - (float) ($budget['last'] ?? 0)
                    < (int) config('sifi_google.min_interval_seconds', 300)) {
                throw new \RuntimeException('SIFI_GOOGLE_LOCAL_RATE_LIMIT');
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
                throw new \RuntimeException('SIFI_GOOGLE_BUDGET_WRITE_FAILED');
            }
            chmod($path, 0600);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
