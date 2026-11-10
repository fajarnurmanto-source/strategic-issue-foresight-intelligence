<?php

namespace App\Console\Commands;

use App\Models\SifiMonitoringObject;
use App\Models\User;
use App\Services\Sifi\Integration\BlueskyLocalPermission;
use App\Services\Sifi\Integration\ReviewedBlueskySearch;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/** One explicit bounded public-social scan, with automated item triage. */
final class SifiSocialBluesky extends Command
{
    protected $signature = 'sifi:social-bluesky
        {--owner= : Existing verified user ID}
        {--object= : Configured owned monitoring object UUID}
        {--query= : Optional exact configured keyword/alias/name}
        {--contact= : Contact email, or SIFI_SOCIAL_CONTACT_EMAIL from local .env}';

    protected $description = 'Capture up to ten public Bluesky social signal links in isolated SIFI research';

    public function handle(ReviewedBlueskySearch $reader): int
    {
        try {
            app(BlueskyLocalPermission::class)->assertAllowed();
            $id = $this->option('owner');
            $objectId = $this->option('object');
            if (! is_string($id) || ! ctype_digit($id) || (int) $id < 1
                || ! is_string($objectId) || ! Str::isUuid($objectId)) {
                throw new \RuntimeException('SIFI_SOCIAL_OWNER_OBJECT_REQUIRED');
            }
            $owner = User::query()->findOrFail((int) $id);
            $object = SifiMonitoringObject::query()->where('user_id', $owner->id)
                ->where('state', 'CONFIGURED')->whereKey($objectId)->firstOrFail();
            $configured = array_values(array_filter([
                ...($object->keywords_json ?? []),
                ...($object->aliases_json ?? []),
                $object->name,
            ], 'is_string'));
            $query = $this->option('query');
            if ($query === null || $query === '') {
                $query = $configured[0] ?? '';
            }
            if (! is_string($query) || ! in_array($query, $configured, true)) {
                throw new \RuntimeException('SIFI_SOCIAL_CONFIGURED_QUERY_REQUIRED');
            }
            $contact = (string) ($this->option('contact') ?: config('sifi_social.contact_email', ''));
            $result = $reader->collect($owner, $object, $query, $contact);
            $this->info('SIFI_SOCIAL_BLUESKY_RECEIPT_SAVED='.$result['session_id']);
            $this->line('SOCIAL_PROVIDER_STATE='.$result['state']);
            $this->line('SOCIAL_NETWORK_REQUESTS='.$result['network_requests']);
            $this->line('PUBLIC_POST_LINKS='.$result['post_links']);
            $this->line('SHADOW_OBSERVATIONS='.$result['shadow_observations']);
            if ($result['state'] !== 'OK') {
                $this->line('SOCIAL_FAILURE_CATEGORY='.($result['failure_category'] ?? 'UNKNOWN'));
                $this->line('SOCIAL_HTTP_STATUS='.($result['http_status'] ?? 'NONE'));
            }
            $this->line('FULL_POST_STORAGE=NONE');
            $this->line('EVIDENCE_ADMISSION=NONE');
            $this->line('PRODUCTION_ADMISSION=DENIED');

            return $result['state'] === 'OK' ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable $error) {
            $this->error('SIFI_SOCIAL=HELD: '.$error->getMessage());

            return self::FAILURE;
        }
    }
}
