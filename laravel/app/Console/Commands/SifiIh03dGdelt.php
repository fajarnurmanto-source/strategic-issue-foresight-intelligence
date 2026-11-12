<?php

namespace App\Console\Commands;

use App\Models\SifiMonitoringObject;
use App\Models\User;
use App\Services\Sifi\Integration\GdeltLocalPermission;
use App\Services\Sifi\Integration\ReviewedGdeltNews;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Explicit one-shot opt-in for isolated, locally reviewed public news links. */
final class SifiIh03dGdelt extends Command
{
    protected $signature = 'sifi:ih03d-gdelt
        {--owner= : Verified existing owner ID}
        {--object= : Configured object UUID owned by this user}
        {--question= : One bounded query without shell spaces}
        {--contact= : Real email for respectful API User-Agent}';

    protected $description = 'One GDELT DOC news-metadata request into SIFI shadow receipts, no evidence admission';

    public function handle(ReviewedGdeltNews $research): int
    {
        try {
            app(GdeltLocalPermission::class)->assertAllowed();
            $ownerId = $this->option('owner');
            $objectId = $this->option('object');
            if (! is_string($ownerId) || ! ctype_digit($ownerId) || (int) $ownerId < 1
                || ! is_string($objectId) || ! Str::isUuid($objectId)) {
                throw new \RuntimeException('IH03D_OWNER_OBJECT_REQUIRED');
            }
            $owner = User::query()->findOrFail((int) $ownerId);
            $object = SifiMonitoringObject::query()->where('user_id', $owner->id)
                ->where('state', 'CONFIGURED')->whereKey($objectId)->firstOrFail();
            $sessionId = $research->collect($owner, $object, [
                'question' => $this->option('question'),
                'object_revision' => (int) $object->revision,
                'submission_key' => (string) Str::uuid(),
                'contact_email' => $this->option('contact'),
            ]);
            $results = json_decode(DB::table('sifi_research_sessions')
                ->where('id', $sessionId)->value('results_json'), true, 32, JSON_THROW_ON_ERROR);
            $envelope = $results['multi_source']['envelope'];
            $state = $envelope['calls'][0]['state'] ?? 'NO_CALL';
            $this->info('IH03D_GDELT_RECEIPT_SAVED='.$sessionId);
            $this->line('GDELT_PROVIDER_STATE='.$state);
            $this->line('GDELT_NETWORK_REQUESTS='.($envelope['usage']['network_requests'] ?? 0));
            $this->line('GDELT_NEWS_LINKS='.count($envelope['entries'] ?? []));
            $this->line('SHADOW_OBSERVATIONS='.DB::table('sifi_connector_observations')
                ->where('session_id', $sessionId)->count());
            $this->line('ARTICLE_FULLTEXT_RETRIEVAL=NONE');
            $this->line('EVIDENCE_ADMISSION=NONE');
            $this->line('PRODUCTION_ADMISSION=DENIED');
            if ($state !== 'OK') {
                $this->displaySafeFailure($sessionId, $owner, $object);
            }

            return $state === 'OK' ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable $error) {
            $this->error('SIFI_IH03D=HELD: '.$error->getMessage());

            return self::FAILURE;
        }
    }

    /** Never display contact addresses, queried article content, or encrypted raw packets. */
    private function displaySafeFailure(string $sessionId, User $owner, SifiMonitoringObject $object): void
    {
        $session = DB::table('sifi_research_sessions')->where('id', $sessionId)
            ->where('user_id', $owner->id)->first();
        if ($session === null) {
            return;
        }
        $plan = json_decode($session->plan_json, true, 32, JSON_THROW_ON_ERROR);
        $binding = [
            'owner_id' => $owner->id,
            'object_id' => $object->id,
            'session_id' => $sessionId,
            'object_revision' => $object->revision,
            'plan_sha256' => hash('sha256', json_encode($plan, JSON_THROW_ON_ERROR)),
        ];
        $expected = hash('sha256', json_encode($binding, JSON_THROW_ON_ERROR));
        $paths = glob(storage_path('app/private/sifi-search-runtime/*.receipt.enc')) ?: [];
        foreach (array_slice($paths, -200) as $path) {
            if (is_link($path) || ! is_file($path) || filesize($path) > 131072) {
                continue;
            }
            try {
                $encrypted = file_get_contents($path);
                if ($encrypted === false) {
                    continue;
                }
                $packet = json_decode(Crypt::decryptString($encrypted), true, 32, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                continue;
            }
            if (($packet['provider'] ?? null) !== 'GDELT_DOC'
                || ($packet['binding_sha256'] ?? null) !== $expected) {
                continue;
            }
            $this->line('GDELT_FAILURE_STAGE='.($packet['failure_stage'] ?? 'UNKNOWN'));
            $this->line('GDELT_FAILURE_CATEGORY='.($packet['failure_category'] ?? 'UNKNOWN'));
            $this->line('GDELT_HTTP_STATUS='.($packet['http_status'] ?? 'NONE'));
            $this->line('GDELT_WIRE_DELIVERY_CONFIRMED='.(($packet['wire_delivery_confirmed'] ?? false) ? 'YES' : 'NO'));
            $this->line('GDELT_ELAPSED_MS='.(int) ($packet['elapsed_ms'] ?? 0));

            return;
        }
        $this->line('GDELT_SAFE_FAILURE_DETAILS=NOT_AVAILABLE');
    }
}
