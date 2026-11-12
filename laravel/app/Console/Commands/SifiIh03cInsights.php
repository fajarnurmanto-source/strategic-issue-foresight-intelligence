<?php

namespace App\Console\Commands;

use App\Models\SifiMonitoringObject;
use App\Models\User;
use App\Services\Sifi\Integration\ResearchReceiptSignalIntelligence;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/** IH03C: owner-bound snapshot from existing real metadata; no HTTP or writes. */
final class SifiIh03cInsights extends Command
{
    protected $signature = 'sifi:ih03c-insights
        {--owner= : Verified owner ID}
        {--session= : Existing real Crossref receipt UUID}
        {--limit=10 : Maximum displayed publication entries (1..20)}';

    protected $description = 'Read-only IH03C signal snapshot from isolated reviewed metadata';

    public function handle(ResearchReceiptSignalIntelligence $intelligence): int
    {
        try {
            if (! app()->environment(['local', 'testing'])
                || config('database.default') !== 'sqlite') {
                throw new \RuntimeException('IH03C_ISOLATED_SQLITE_REQUIRED');
            }
            $database = config('database.connections.sqlite.database');
            if ($database !== ':memory:' && (! is_string($database)
                || preg_match('#\A/mnt/d/Research/SIFI/05_VALIDATION/[^\r\n]+\.sqlite\z#D', $database) !== 1)) {
                throw new \RuntimeException('IH03C_ISOLATED_SQLITE_REQUIRED');
            }
            $ownerId = $this->option('owner');
            $sessionId = $this->option('session');
            $limit = $this->option('limit');
            if (! is_string($ownerId) || ! ctype_digit($ownerId) || (int) $ownerId < 1
                || ! is_string($sessionId) || ! Str::isUuid($sessionId)
                || ! is_string($limit) || ! ctype_digit($limit)
                || (int) $limit < 1 || (int) $limit > 20) {
                throw new \RuntimeException('IH03C_ARGUMENTS_INVALID');
            }

            $owner = User::query()->findOrFail((int) $ownerId);
            if (! $owner->hasVerifiedEmail()) {
                throw new \RuntimeException('IH03C_OWNER_NOT_VERIFIED');
            }
            $session = DB::table('sifi_research_sessions')
                ->where('id', $sessionId)->where('user_id', $owner->id)->first();
            if ($session === null || now()->gte($session->expires_at)) {
                throw new \RuntimeException('IH03C_SESSION_NOT_AVAILABLE');
            }
            $object = SifiMonitoringObject::query()
                ->whereKey($session->object_id)->where('user_id', $owner->id)->firstOrFail();
            Gate::forUser($owner)->authorize('view', $object);
            if ($object->state !== 'CONFIGURED'
                || (int) $session->object_revision !== (int) $object->revision) {
                throw new \RuntimeException('IH03C_OBJECT_CHANGED_OR_PAUSED');
            }

            $results = json_decode($session->results_json, true, 32, JSON_THROW_ON_ERROR);
            $envelope = $results['multi_source']['envelope'] ?? null;
            if (! is_array($envelope)) {
                throw new \RuntimeException('IH03C_RECEIPT_NOT_AVAILABLE');
            }
            $snapshot = $intelligence->project($envelope, (int) $limit);
            $snapshot['receipt_session_id'] = $sessionId;
            $snapshot['owner_id'] = $owner->id;
            $snapshot['object_id'] = $object->id;
            $snapshot['shadow_observation_count'] = DB::table('sifi_connector_observations')
                ->where('session_id', $sessionId)->where('owner_user_id', $owner->id)->count();

            $this->line(json_encode($snapshot,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (\Throwable $error) {
            $this->error('SIFI_IH03C=HELD: '.$error->getMessage());

            return self::FAILURE;
        }
    }
}
