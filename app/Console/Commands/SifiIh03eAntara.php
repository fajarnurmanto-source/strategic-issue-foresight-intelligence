<?php

namespace App\Console\Commands;

use App\Models\SifiMonitoringObject;
use App\Models\User;
use App\Services\Sifi\Integration\AntaraLocalPermission;
use App\Services\Sifi\Integration\ReviewedAntaraRss;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/** One explicit local ANTARA RSS read, not a background news crawler. */
final class SifiIh03eAntara extends Command
{
    protected $signature = 'sifi:ih03e-antara
        {--owner= : Existing verified user ID}
        {--object= : Configured owned monitoring object UUID}
        {--contact= : Real contact email for the feed reader}';

    protected $description = 'Save up to five real publisher RSS headline links in isolated SIFI shadow research';

    public function handle(ReviewedAntaraRss $reader): int
    {
        try {
            app(AntaraLocalPermission::class)->assertAllowed();
            $id = $this->option('owner');
            $objectId = $this->option('object');
            if (! is_string($id) || ! ctype_digit($id) || (int) $id < 1
                || ! is_string($objectId) || ! Str::isUuid($objectId)) {
                throw new \RuntimeException('IH03E_OWNER_OBJECT_REQUIRED');
            }
            $owner = User::query()->findOrFail((int) $id);
            $object = SifiMonitoringObject::query()->where('user_id', $owner->id)
                ->where('state', 'CONFIGURED')->whereKey($objectId)->firstOrFail();
            $result = $reader->collect($owner, $object, (string) $this->option('contact'));
            $this->info('IH03E_ANTARA_RECEIPT_SAVED='.$result['session_id']);
            $this->line('ANTARA_PROVIDER_STATE='.$result['state']);
            $this->line('ANTARA_NETWORK_REQUESTS='.$result['network_requests']);
            $this->line('ANTARA_NEWS_LINKS='.$result['news_links']);
            $this->line('SHADOW_OBSERVATIONS='.$result['shadow_observations']);
            if ($result['state'] !== 'OK') {
                $this->line('ANTARA_FAILURE_CATEGORY='.($result['failure_category'] ?? 'UNKNOWN'));
                $this->line('ANTARA_HTTP_STATUS='.($result['http_status'] ?? 'NONE'));
            }
            $this->line('ARTICLE_FULLTEXT_RETRIEVAL=NONE');
            $this->line('EVIDENCE_ADMISSION=NONE');
            $this->line('PRODUCTION_ADMISSION=DENIED');
            return $result['state'] === 'OK' && $result['news_links'] > 0
                ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable $error) {
            $this->error('SIFI_IH03E=HELD: '.$error->getMessage());
            return self::FAILURE;
        }
    }
}
