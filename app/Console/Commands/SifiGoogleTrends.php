<?php

namespace App\Console\Commands;

use App\Models\SifiMonitoringObject;
use App\Models\User;
use App\Services\Sifi\Integration\GoogleTrendsLocalPermission;
use App\Services\Sifi\Integration\ReviewedGoogleTrends;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/** Explicit, one-shot Google search-attention ingestion; no background scheduling. */
final class SifiGoogleTrends extends Command
{
    protected $signature = 'sifi:google-trends
        {--owner= : Existing verified user ID}
        {--object= : Configured owned monitoring object UUID}';

    protected $description = 'Save top Indonesian Google Trends RSS metadata in isolated research';

    public function handle(ReviewedGoogleTrends $reader): int
    {
        try {
            app(GoogleTrendsLocalPermission::class)->assertAllowed();
            $id = $this->option('owner');
            $objectId = $this->option('object');
            if (! is_string($id) || ! ctype_digit($id) || (int) $id < 1
                || ! is_string($objectId) || ! Str::isUuid($objectId)) {
                throw new \RuntimeException('SIFI_GOOGLE_OWNER_OBJECT_REQUIRED');
            }
            $owner = User::query()->findOrFail((int) $id);
            $object = SifiMonitoringObject::query()->where('user_id', $owner->id)
                ->where('state', 'CONFIGURED')->whereKey($objectId)->firstOrFail();
            $result = $reader->collect($owner, $object);
            $this->info('SIFI_GOOGLE_TRENDS_RECEIPT_SAVED='.$result['session_id']);
            $this->line('GOOGLE_PROVIDER_STATE='.$result['state']);
            $this->line('GOOGLE_NETWORK_REQUESTS='.$result['network_requests']);
            $this->line('GOOGLE_TREND_TERMS='.$result['trend_terms']);
            $this->line('GOOGLE_KEYWORD_MATCH_CANDIDATES='.$result['keyword_match_candidates']);
            $this->line('GOOGLE_SHADOW_OBSERVATIONS='.$result['shadow_observations']);
            if ($result['state'] !== 'OK') {
                $this->line('GOOGLE_HTTP_STATUS='.($result['http_status'] ?? 'NONE'));
                $this->line('GOOGLE_FAILURE_CATEGORY='.($result['failure_category'] ?? 'UNKNOWN'));
            }
            $this->line('FULL_ARTICLE_STORAGE=NONE');
            $this->line('EVIDENCE_ADMISSION=NONE');
            $this->line('PRODUCTION_ADMISSION=DENIED');

            return $result['state'] === 'OK' ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable $error) {
            $this->error('SIFI_GOOGLE=HELD: '.$error->getMessage());

            return self::FAILURE;
        }
    }
}
