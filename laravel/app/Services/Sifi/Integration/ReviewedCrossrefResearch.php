<?php

namespace App\Services\Sifi\Integration;

use App\Models\SifiMonitoringObject;
use App\Models\User;
use App\Services\Sifi\MultiSourceResearchWorkspace;
use App\Services\Sifi\OneShotSearchAuthority;
use App\Services\Sifi\RealSearchWorkspace;
use App\Services\Sifi\ResearchRetrievalWorkspace;
use App\Services\Sifi\ResearchSessionWorkspace;
use App\Services\Sifi\ResearchSignalInsights;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/**
 * IH03B: one bounded, free Crossref *metadata-only* request for an owned object.
 * Reuses SIFI's one-shot authority, pin-to-public-IP transport, provider adapter,
 * research history, provenance, and IH03A shadow projection. No scheduler or AI.
 */
final class ReviewedCrossrefResearch
{
    /** @param array<string, mixed> $input */
    public function collect(User $owner, SifiMonitoringObject $object, array $input): string
    {
        app(CrossrefLocalPermission::class)->assertAllowed();
        $values = Validator::make($input, [
            'question' => ['required', 'string', 'min:8', 'max:600'],
            'object_revision' => ['required', 'integer', 'min:1'],
            'submission_key' => ['required', 'uuid'],
            'contact_email' => ['required', 'string', 'max:254'],
            'date_window' => ['sometimes', 'required', 'array:start,end'],
            'date_window.start' => ['required_with:date_window', 'date_format:Y-m-d'],
            'date_window.end' => ['required_with:date_window', 'date_format:Y-m-d', 'after:date_window.start'],
        ])->validate();
        app(CrossrefLocalPermission::class)->assertContact($values['contact_email']);
        if (! $owner->hasVerifiedEmail() || ! $owner->exists) {
            throw new \RuntimeException('IH03B_OWNER_NOT_VERIFIED');
        }
        Gate::forUser($owner)->authorize('update', $object);

        return DB::transaction(function () use ($owner, $object, $values): string {
            // Recheck under the same owner/object lock used by the existing research workflow.
            User::query()->whereKey($owner->id)->lockForUpdate()->firstOrFail();
            $locked = SifiMonitoringObject::query()->whereKey($object->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($owner)->authorize('update', $locked);
            if ((int) $locked->user_id !== (int) $owner->id
                || $locked->state !== 'CONFIGURED'
                || (int) $locked->revision !== (int) $values['object_revision']) {
                throw new \RuntimeException('IH03B_OBJECT_CHANGED_OR_PAUSED');
            }
            app(CrossrefLocalPermission::class)->assertAllowed();

            $existing = DB::table('sifi_research_session_audit')
                ->where('actor_user_id', $owner->id)
                ->where('action', 'IH03B_CROSSREF_COMPLETED')
                ->where('snapshot_json->submission_key', $values['submission_key'])
                ->first();
            if ($existing !== null) {
                throw new \RuntimeException('IH03B_SUBMISSION_ALREADY_USED');
            }

            $this->reserveBudget($owner, $locked, $values['submission_key']);

            $windowLabel = 'UNSPECIFIED';
            if (isset($values['date_window'])) {
                $endTime = strtotime($values['date_window']['end'].' -1 day');
                if ($endTime === false) {
                    throw new \RuntimeException('IH03B_DATE_WINDOW_INVALID');
                }
                $windowLabel = $values['date_window']['start'].' / '.date('Y-m-d', $endTime);
            }
            $sessionId = app(ResearchSessionWorkspace::class)->create($owner, $locked, [
                'question' => $values['question'],
                'object_revision' => (int) $locked->revision,
                'query_limit' => 1,
                'result_limit' => 5,
                'time_window' => $windowLabel,
            ]);
            $session = app(ResearchRetrievalWorkspace::class)->session($owner, $locked, $sessionId);
            $plan = json_decode($session->plan_json, true, 32, JSON_THROW_ON_ERROR);
            $query = $plan['queries'][0]['query'] ?? null;
            if (! is_string($query) || trim($query) === '' || mb_strlen($query) > 600) {
                throw new \RuntimeException('IH03B_RESEARCH_QUERY_UNAVAILABLE');
            }
            $binding = [
                'owner_id' => $owner->id,
                'object_id' => $locked->id,
                'session_id' => $sessionId,
                'object_revision' => $locked->revision,
                'plan_sha256' => hash('sha256', json_encode($plan, JSON_THROW_ON_ERROR)),
            ];
            $context = [
                'mode' => 'LIVE',
                'query' => $query,
                'query_sha256' => hash('sha256', $query),
                'binding' => $binding,
                'limit' => 5,
            ];
            if (isset($values['date_window'])) {
                $context['date_window'] = $values['date_window'];
            }

            $this->issueOneShotAuthority($owner, $locked, $values, $context);
            $previous = config('sifi.real_search_live');
            try {
                config(['sifi.real_search_live' => true]);
                $envelope = app(RealSearchWorkspace::class)->execute(
                    $locked, $plan, $binding, 'approved_live', ['CROSSREF'], 'LIVE', 0, false,
                    $values['date_window'] ?? []
                );
            } finally {
                config(['sifi.real_search_live' => $previous]);
            }

            $envelope['insights'] = app(ResearchSignalInsights::class)->summarize($envelope);
            $digest = app(MultiSourceResearchWorkspace::class)->digest($envelope);
            $results = json_decode($session->results_json, true, 32, JSON_THROW_ON_ERROR);
            $results['multi_source'] = [
                'state' => 'DRAFT',
                'envelope' => $envelope,
                'envelope_sha256' => $digest,
                'saved_at' => null,
                'analyst_note' => null,
                'selected_ids' => [],
            ];
            DB::table('sifi_research_sessions')->where('id', $sessionId)->update([
                'results_json' => json_encode($results, JSON_THROW_ON_ERROR),
                'stopping_reason' => $envelope['stopping_reason'],
                'updated_at' => now(),
            ]);
            app(ConnectorObservationProjector::class)->record($owner, $locked, $sessionId, $envelope);
            DB::table('sifi_research_session_audit')->insert([
                'session_id' => $sessionId,
                'actor_user_id' => $owner->id,
                'action' => 'IH03B_CROSSREF_COMPLETED',
                'snapshot_json' => json_encode([
                    'submission_key' => $values['submission_key'],
                    'envelope_sha256' => $digest,
                    'network_requests' => $envelope['usage']['network_requests'] ?? 0,
                    'evidence_admission' => 'NONE',
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);

            return $sessionId;
        });
    }

    private function reserveBudget(User $owner, SifiMonitoringObject $object, string $submissionKey): void
    {
        $directory = storage_path('app/private/sifi-ih03b');
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new \RuntimeException('IH03B_BUDGET_STORAGE_UNAVAILABLE');
        }
        $path = $directory.'/crossref-budget.enc';
        if (is_link($path)) {
            throw new \RuntimeException('IH03B_BUDGET_SYMLINK_DENIED');
        }
        $handle = fopen($path, 'c+');
        if ($handle === false || ! flock($handle, LOCK_EX | LOCK_NB)) {
            throw new \RuntimeException('IH03B_BUDGET_BUSY');
        }
        try {
            $raw = stream_get_contents($handle);
            if ($raw === false) {
                throw new \RuntimeException('IH03B_BUDGET_READ_FAILED');
            }
            $budget = $raw === '' ? ['day' => '', 'count' => 0, 'last' => 0, 'keys' => []]
                : json_decode(Crypt::decryptString($raw), true, 12, JSON_THROW_ON_ERROR);
            if (! is_array($budget) || ! is_array($budget['keys'] ?? null)) {
                throw new \RuntimeException('IH03B_BUDGET_INVALID');
            }
            $key = hash('sha256', $owner->id.'|'.$object->id.'|'.$submissionKey);
            if (in_array($key, $budget['keys'], true)) {
                throw new \RuntimeException('IH03B_SUBMISSION_ALREADY_RESERVED');
            }
            $today = now('Asia/Jakarta')->toDateString();
            $count = $budget['day'] === $today ? (int) $budget['count'] : 0;
            if ($count >= (int) config('sifi_ih03b.max_requests_per_day', 5)
                || microtime(true) - (float) $budget['last']
                    < (int) config('sifi_ih03b.min_interval_seconds', 60)) {
                throw new \RuntimeException('IH03B_LOCAL_QUOTA_REACHED');
            }
            $budget = [
                'day' => $today,
                'count' => $count + 1,
                'last' => microtime(true),
                'keys' => array_slice([...$budget['keys'], $key], -100),
            ];
            $cipher = Crypt::encryptString(json_encode($budget, JSON_THROW_ON_ERROR));
            rewind($handle);
            if (fwrite($handle, $cipher) !== strlen($cipher)
                || ! ftruncate($handle, strlen($cipher)) || ! fflush($handle)) {
                throw new \RuntimeException('IH03B_BUDGET_RESERVATION_FAILED');
            }
            chmod($path, 0600);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** @param array<string, mixed> $values
     * @param  array<string, mixed>  $context
     */
    private function issueOneShotAuthority(
        User $owner, SifiMonitoringObject $object, array $values, array $context
    ): void {
        $authority = app(OneShotSearchAuthority::class);
        $path = $authority->path('CROSSREF', $context);
        if (is_file($path) || is_link($path)) {
            throw new \RuntimeException('IH03B_AUTHORITY_ALREADY_EXISTS');
        }
        if (! is_dir(dirname($path)) && ! mkdir(dirname($path), 0700, true)) {
            throw new \RuntimeException('IH03B_AUTHORITY_STORAGE_UNAVAILABLE');
        }
        $permit = [
            'version' => 'sifi-search-authority-1',
            'context_hash' => $authority->contextHash('CROSSREF', $context),
            'endpoint' => 'https://api.crossref.org/works',
            'method' => 'GET',
            'cost_limit' => 0,
            'request_limit' => 1,
            'response_byte_limit' => 32768,
            'consumed' => false,
            // Laravel's clock is authoritative (including frozen test time).
            'expires_at' => (int) now()->timestamp + 30,
            'approval_scope_sha256' => hash('sha256', 'IH03B_LOCAL_CROSSREF_20261107|'.$owner->id.'|'.$object->id.'|'.$values['submission_key']),
            'nonce' => bin2hex(random_bytes(32)),
            'contact_email' => $values['contact_email'],
        ];
        $cipher = Crypt::encryptString(json_encode($permit, JSON_THROW_ON_ERROR));
        if (file_put_contents($path, $cipher, LOCK_EX) !== strlen($cipher)) {
            throw new \RuntimeException('IH03B_AUTHORITY_WRITE_FAILED');
        }
        chmod($path, 0600);
    }
}
