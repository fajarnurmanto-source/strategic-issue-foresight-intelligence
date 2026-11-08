<?php

/**
 * One-shot, isolated SQLite + live-public-social demonstration.
 * Called only by sifi-bluesky-isolated-pilot.sh, not registered as a web route.
 */
declare(strict_types=1);

use App\Models\SifiMonitoringObject;
use App\Models\User;
use App\Services\Sifi\Integration\BlueskyLocalPermission;
use App\Services\Sifi\Integration\ReviewedBlueskySearch;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$appRoot = '/home/fajar/projects/sifi-laravel-vnext';
$validationRoot = '/mnt/d/Research/SIFI/05_VALIDATION';
$dbPath = getenv('DB_DATABASE');
$storagePath = getenv('SIFI_SOCIAL_PILOT_STORAGE');

// No bootstrap, migrations, or network requests without verified isolated paths.
if (! is_string($dbPath) || ! is_string($storagePath)
    || preg_match('#\A/mnt/d/Research/SIFI/05_VALIDATION/SIFI_BLUESKY_LIVE_[0-9]{8}_[0-9]{6}_[A-Za-z0-9]{8}\.sqlite\z#D', $dbPath) !== 1
    || $storagePath !== substr($dbPath, 0, -7).'_storage'
    || ! is_file($dbPath) || is_link($dbPath) || filesize($dbPath) !== 0
    || ! is_dir($storagePath) || is_link($storagePath)
    || realpath(dirname($dbPath)) !== realpath($validationRoot)
    || ! is_file($appRoot.'/vendor/autoload.php')
    || is_file($appRoot.'/bootstrap/cache/config.php')) {
    fwrite(STDERR, "SIFI_PILOT=HELD: Isolated database path preflight failed.\n");
    exit(1);
}

chdir($appRoot);
require $appRoot.'/vendor/autoload.php';
$app = require $appRoot.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    $configured = config('database.connections.sqlite.database');
    $url = config('database.connections.sqlite.url');
    if (app()->environment() !== 'local' || config('database.default') !== 'sqlite'
        || $configured !== $dbPath || ($url !== null && $url !== '')
        || config('sifi_social.bluesky_public_search_enabled') !== true
        || config('sifi_social.auto_watch_enabled') !== false
        || config('sifi_ih03.shadow_ingest_enabled') !== true) {
        throw new RuntimeException('SIFI_PILOT_EFFECTIVE_CONFIGURATION_MISMATCH');
    }
    $app->useStoragePath($storagePath); // Includes encrypted request budget.
    app(BlueskyLocalPermission::class)->assertAllowed();
    $contact = getenv('SIFI_SOCIAL_CONTACT_EMAIL');
    app(BlueskyLocalPermission::class)->assertContact(is_string($contact) ? $contact : '');

    echo "DB_SCOPE=ISOLATED_VALIDATION\n";
    echo "APP_ENV=local\n";
    echo "SOCIAL_SOURCE=BLUESKY_ONE_SHOT\n";

    $code = Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);
    if ($code !== 0) {
        throw new RuntimeException('SIFI_PILOT_ISOLATED_MIGRATION_FAILED: '.trim(Artisan::output()));
    }
    echo "ISOLATED_MIGRATIONS=PASS\n";

    $owner = User::query()->create([
        'name' => 'SIFI Isolated Social Pilot',
        'email' => 'sifi-pilot-'.bin2hex(random_bytes(8)).'@example.org',
        'password' => bin2hex(random_bytes(32)),
    ]);
    $owner->forceFill(['email_verified_at' => now()])->save();
    $object = SifiMonitoringObject::query()->create([
        'id' => (string) Str::uuid(),
        'user_id' => $owner->id,
        'name' => 'SIFI Pilot: Persampahan',
        'description' => 'Isolated local real-source observation, not production evidence.',
        'category' => 'Research',
        'objective' => 'Identify public social-media signals about waste.',
        'horizon' => '90 hari',
        'geography' => null,
        'aliases_json' => [],
        'keywords_json' => ['sampah'],
        'exclusions_json' => [],
        'state' => 'CONFIGURED',
        'revision' => 1,
    ]);

    $result = app(ReviewedBlueskySearch::class)->collect($owner, $object, 'sampah', $contact);
    echo 'PROVIDER_STATE='.$result['state']."\n";
    echo 'NETWORK_REQUESTS='.$result['network_requests']."\n";
    echo 'PUBLIC_POST_LINKS='.$result['post_links']."\n";
    echo 'SHADOW_OBSERVATIONS='.$result['shadow_observations']."\n";
    echo 'SESSION_ID='.$result['session_id']."\n";
    echo 'PROVIDER_HTTP_STATUS='.($result['http_status'] ?? 'NONE')."\n";

    $resultsJson = DB::table('sifi_research_sessions')
        ->where('id', $result['session_id'])->value('results_json');
    $saved = is_string($resultsJson) ? json_decode($resultsJson, true, 32, JSON_THROW_ON_ERROR) : [];
    $envelope = $saved['multi_source']['envelope'] ?? [];
    $history = $envelope['social_watch_history'] ?? [];
    echo 'FIRST_OBSERVED_IN_LOCAL_INDEX='.($history['first_observed_count'] ?? 'NA')."\n";
    echo 'PREVIOUSLY_OBSERVED='.($history['previously_observed_count'] ?? 'NA')."\n";
    foreach (array_slice($envelope['entries'] ?? [], 0, 3) as $i => $entry) {
        $url = $entry['canonical_url'] ?? null;
        if (is_string($url) && str_starts_with($url, 'https://bsky.app/profile/')) {
            echo 'PUBLIC_POST_LINK_'.($i + 1).'='.$url."\n";
        }
    }
    if ($result['state'] !== 'OK') {
        echo 'FAILURE_CATEGORY='.($result['failure_category'] ?? 'UNKNOWN')."\n";
    }
    echo "FULL_POST_STORAGE=NONE\nEVIDENCE_ADMISSION=NONE\nPRODUCTION_ADMISSION=DENIED\n";
    echo 'SIFI_PILOT='.($result['state'] === 'OK' ? 'PASS' : 'PROVIDER_UNAVAILABLE')."\n";
    exit($result['state'] === 'OK' ? 0 : 1);
} catch (Throwable $error) {
    fwrite(STDERR, 'SIFI_PILOT=HELD: '.$error->getMessage()."\n");
    fwrite(STDERR, "ISOLATED_DB_RETAINED_FOR_DIAGNOSIS=$dbPath\n");
    exit(1);
}
