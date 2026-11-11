<?php

namespace App\Console\Commands;

use App\Models\SifiMonitoringObject;
use App\Models\User;
use App\Services\Sifi\Integration\CrossrefLocalPermission;
use App\Services\Sifi\Integration\ReviewedCrossrefResearch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** Local-only CLI. The default is deny; never makes requests in normal operation. */
final class SifiIh03bCrossref extends Command
{
    protected $signature = 'sifi:ih03b-crossref
        {--owner= : Numeric owner user ID}
        {--bootstrap-preview : Create a synthetic owner and object in isolated SQLite}
        {--bootstrap-object : Create or reuse a synthetic monitoring object in isolated preview SQLite}
        {--list-objects : Display configured monitoring object IDs owned by this user}
        {--object= : UUID of configured monitoring object}
        {--question= : Bounded research question}
        {--contact= : Contact email required by polite Crossref API access}';

    protected $description = 'One reviewed, bounded Crossref metadata request on isolated local SIFI SQLite';

    public function handle(ReviewedCrossrefResearch $research): int
    {
        try {
            app(CrossrefLocalPermission::class)->assertAllowed();
            if ($this->option('bootstrap-preview')) {
                $preview = DB::transaction(function (): array {
                    $email = 'sifi-ih03b-synthetic@preview.invalid';
                    $ownerId = DB::table('users')->where('email', $email)->value('id');
                    if ($ownerId === null) {
                        $ownerId = DB::table('users')->insertGetId([
                            'name' => '[LOCAL PREVIEW] SIFI research owner',
                            'email' => $email,
                            'email_verified_at' => now(),
                            'password' => Hash::make(Str::random(48)),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                    $owner = User::query()->whereKey($ownerId)->firstOrFail();
                    if (! $owner->hasVerifiedEmail()) {
                        throw new \RuntimeException('IH03B_SYNTHETIC_OWNER_NOT_VERIFIED');
                    }
                    $object = $this->previewObject($owner);
                    return [$owner->id, $object->id];
                });
                $this->info('IH03B_PREVIEW_OWNER_ID='.$preview[0]);
                $this->info('IH03B_PREVIEW_OBJECT_ID='.$preview[1]);
                $this->line('SYNTHETIC_OWNER=NO_LOGIN_NO_EXTERNAL_CONTACT');
                return self::SUCCESS;
            }
            $id = $this->option('owner');
            if (! is_string($id) || ! ctype_digit($id) || (int) $id < 1) {
                throw new \RuntimeException('IH03B_OWNER_ID_REQUIRED');
            }
            $owner = User::query()->whereKey((int) $id)->firstOrFail();
            $objects = SifiMonitoringObject::query()
                ->where('user_id', $owner->id)->where('state', 'CONFIGURED');

            if ($this->option('bootstrap-object')) {
                if (! $owner->hasVerifiedEmail()) {
                    throw new \RuntimeException('IH03B_OWNER_NOT_VERIFIED');
                }
                $preview = $this->previewObject($owner);
                $this->info('IH03B_PREVIEW_OBJECT_ID='.$preview->id);
                return self::SUCCESS;
            }

            if ($this->option('list-objects')) {
                foreach ($objects->orderBy('name')->get(['id', 'name', 'revision']) as $object) {
                    $this->line($object->id.' | revision='.$object->revision.' | '.$object->name);
                }
                return self::SUCCESS;
            }

            $objectId = $this->option('object');
            if (! is_string($objectId) || ! Str::isUuid($objectId)) {
                throw new \RuntimeException('IH03B_OBJECT_ID_REQUIRED');
            }
            $object = $objects->whereKey($objectId)->firstOrFail();
            $sessionId = $research->collect($owner, $object, [
                'question' => $this->option('question'),
                'object_revision' => (int) $object->revision,
                'submission_key' => (string) Str::uuid(),
                'contact_email' => $this->option('contact'),
            ]);
            $this->info('IH03B_METADATA_RECEIPT_SAVED='.$sessionId);
            $this->line('EVIDENCE_ADMISSION=NONE');
            $this->line('PRODUCTION_ADMISSION=DENIED');
            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error('SIFI_IH03B=HELD: '.$exception->getMessage());
            return self::FAILURE;
        }
    }

    private function previewObject(User $owner): SifiMonitoringObject
    {
        $name = '[LOCAL PREVIEW] Crossref metadata research';
        $object = SifiMonitoringObject::query()->where('user_id', $owner->id)
            ->where('name', $name)->first();
        if ($object !== null) {
            return $object;
        }
        return SifiMonitoringObject::create([
            'id' => (string) Str::uuid(),
            'user_id' => $owner->id,
            'name' => $name,
            'description' => 'Synthetic monitoring configuration for isolated lawful metadata experiments.',
            'category' => 'Research',
            'objective' => 'Compare public bibliographic metadata without admitting evidence.',
            'horizon' => '90 hari',
            'geography' => null,
            'aliases_json' => [],
            'keywords_json' => ['research', 'publication'],
            'exclusions_json' => [],
            'state' => 'CONFIGURED',
            'revision' => 1,
        ]);
    }
}
