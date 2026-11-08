<?php

namespace App\Console\Commands;

use App\Models\SifiMonitoringObject;
use App\Models\User;
use App\Services\Sifi\Integration\BlueskyLocalPermission;
use App\Services\Sifi\Integration\ConnectorObservationProjector;
use App\Services\Sifi\Integration\ReviewedBlueskySearch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One owner-bound, quota-capped scan per scheduler tick.
 * Selects the least recently scanned configured object; no per-post review.
 */
final class SifiSocialWatchTick extends Command
{
    protected $signature = 'sifi:social-watch:tick';

    protected $description = 'Run at most one opted-in Bluesky scan for the least recently monitored object';

    public function handle(ReviewedBlueskySearch $reader): int
    {
        if (config('sifi_social.auto_watch_enabled') !== true) {
            $this->line('SIFI_SOCIAL_AUTOWATCH=OFF_BY_DEFAULT');

            return self::SUCCESS;
        }

        try {
            app(BlueskyLocalPermission::class)->assertAllowed();
            if (! app(ConnectorObservationProjector::class)->eligible()) {
                throw new \RuntimeException('SIFI_SOCIAL_AUTOWATCH_SHADOW_REQUIRED');
            }
            $id = config('sifi_social.auto_watch_owner_id');
            if ((! is_string($id) && ! is_int($id))
                || ! ctype_digit((string) $id) || (int) $id < 1) {
                throw new \RuntimeException('SIFI_SOCIAL_AUTOWATCH_OWNER_REQUIRED');
            }
            $owner = User::query()->findOrFail((int) $id);
            if (! $owner->hasVerifiedEmail()) {
                throw new \RuntimeException('SIFI_SOCIAL_OWNER_NOT_VERIFIED');
            }
            $contact = config('sifi_social.contact_email');
            app(BlueskyLocalPermission::class)->assertContact((string) $contact);

            $objects = SifiMonitoringObject::query()
                ->where('user_id', $owner->id)
                ->where('state', 'CONFIGURED')
                ->orderBy('id')
                ->limit(30)
                ->get();
            $candidates = [];
            foreach ($objects as $object) {
                $terms = array_values(array_filter([
                    ...($object->keywords_json ?? []),
                    ...($object->aliases_json ?? []),
                    $object->name,
                ], 'is_string'));
                foreach ($terms as $term) {
                    try {
                        app(BlueskyLocalPermission::class)->assertQuery($term);
                        $candidates[] = ['object' => $object, 'query' => $term];
                        break;
                    } catch (\InvalidArgumentException $ignored) {
                        // Try the next configured keyword; never broaden a user's query.
                    }
                }
            }
            if ($candidates === []) {
                $this->line('SIFI_SOCIAL_AUTOWATCH=NO_ELIGIBLE_OBJECTS');

                return self::SUCCESS;
            }
            $lastScan = DB::table('sifi_research_session_audit as a')
                ->join('sifi_research_sessions as s', 's.id', '=', 'a.session_id')
                ->where('a.actor_user_id', $owner->id)
                ->where('a.action', 'SIFI_SOCIAL_BLUESKY_METADATA_RECEIPT_SAVED')
                ->whereIn('s.object_id', array_map(
                    static fn (array $item): string => $item['object']->id, $candidates
                ))
                ->selectRaw('s.object_id, MAX(a.created_at) AS latest_scan')
                ->groupBy('s.object_id')
                ->pluck('latest_scan', 's.object_id')
                ->all();

            usort($candidates, static function (array $left, array $right) use ($lastScan): int {
                $a = $lastScan[$left['object']->id] ?? '';
                $b = $lastScan[$right['object']->id] ?? '';

                return strcmp((string) $a, (string) $b)
                    ?: strcmp($left['object']->id, $right['object']->id);
            });

            $next = $candidates[0];
            $result = $reader->collect($owner, $next['object'], $next['query'], $contact);
            $this->line('SIFI_SOCIAL_AUTOWATCH='.($result['state'] === 'OK' ? 'RECEIPT_SAVED' : 'PROVIDER_UNAVAILABLE'));
            $this->line('SIFI_SOCIAL_AUTOWATCH_OBJECT='.$next['object']->id);
            $this->line('SIFI_SOCIAL_AUTOWATCH_SESSION='.$result['session_id']);
            $this->line('SIFI_SOCIAL_AUTOWATCH_POST_LINKS='.$result['post_links']);
            $this->line('SIFI_SOCIAL_AUTOWATCH_NETWORK_REQUESTS='.$result['network_requests']);
            $this->line('PRODUCTION_ADMISSION=DENIED');

            return $result['state'] === 'OK' ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable $error) {
            $code = $error->getMessage();
            if (! preg_match('/\A[A-Z][A-Z0-9_]{2,100}\z/D', $code)) {
                $code = 'SOCIAL_AUTOWATCH_UNAVAILABLE';
            }
            $this->error('SIFI_SOCIAL_AUTOWATCH=HELD_'.$code);

            return self::FAILURE;
        }
    }
}
