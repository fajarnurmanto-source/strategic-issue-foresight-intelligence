<?php

/** Run only through the matching shell wrapper; never invoked by a web route. */
declare(strict_types=1);

use App\Models\SifiMonitoringObject;
use App\Models\User;
use App\Services\Sifi\Integration\GoogleTrendsLocalPermission;
use App\Services\Sifi\Integration\ReviewedGoogleTrends;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$appRoot = '/home/fajar/projects/sifi-laravel-vnext';
$validationRoot = '/mnt/d/Research/SIFI/05_VALIDATION';
$dbPath = getenv('DB_DATABASE');
$storagePath = getenv('SIFI_GOOGLE_PILOT_STORAGE');

// Absolutely no Laravel bootstrap, DB migration or network without isolated scope.
if (! is_string($dbPath) || ! is_string($storagePath)
    || preg_match('#\A/mnt/d/Research/SIFI/05_VALIDATION/SIFI_GOOGLE_TRENDS_LIVE_[0-9]{8}_[0-9]{6}_[A-Za-z0-9]{8}\.sqlite\z#D', $dbPath) !== 1
    || $storagePath !== substr($dbPath, 0, -7).'_storage'
    || ! is_file($dbPath) || is_link($dbPath) || filesize($dbPath) !== 0
    || ! is_dir($storagePath) || is_link($storagePath)
    || realpath(dirname($dbPath)) !== realpath($validationRoot)
    || ! is_file($appRoot.'/vendor/autoload.php')
    || is_file($appRoot.'/bootstrap/cache/config.php')) {
    fwrite(STDERR, "SIFI_GOOGLE_PILOT=HELD: Isolated database path preflight failed.\n");
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
        || config('sifi_google.trends_rss_enabled') !== true
        || config('sifi_social.auto_watch_enabled') !== false
        || config('sifi_ih03.shadow_ingest_enabled') !== true) {
        throw new RuntimeException('SIFI_GOOGLE_EFFECTIVE_CONFIGURATION_MISMATCH');
    }
    $app->useStoragePath($storagePath);
    app(GoogleTrendsLocalPermission::class)->assertAllowed();
    echo "DB_SCOPE=ISOLATED_VALIDATION\nAPP_ENV=local\nGOOGLE_SOURCE=TRENDING_NOW_RSS_ID\n";

    if (Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]) !== 0) {
        throw new RuntimeException('SIFI_GOOGLE_ISOLATED_MIGRATION_FAILED: '.trim(Artisan::output()));
    }
    echo "ISOLATED_MIGRATIONS=PASS\n";

    $owner = User::query()->create([
        'name' => 'SIFI Isolated Google Trends Pilot',
        'email' => 'sifi-google-'.bin2hex(random_bytes(8)).'@example.org',
        'password' => bin2hex(random_bytes(32)),
    ]);
    $owner->forceFill(['email_verified_at' => now()])->save();
    $object = SifiMonitoringObject::query()->create([
        'id' => (string) Str::uuid(),
        'user_id' => $owner->id,
        'name' => 'SIFI Pilot: Persampahan',
        'description' => 'Indonesian public search-interest trends; not independently verified events.',
        'category' => 'Research',
        'objective' => 'Find early keywords and attention trends for monitoring.',
        'horizon' => '90 hari',
        'geography' => null,
        'aliases_json' => [],
        'keywords_json' => ['sampah', 'banjir', 'jalan'],
        'exclusions_json' => [],
        'state' => 'CONFIGURED',
        'revision' => 1,
    ]);

    $result = app(ReviewedGoogleTrends::class)->collect($owner, $object);
    echo 'PROVIDER_STATE='.$result['state']."\n";
    echo 'NETWORK_REQUESTS='.$result['network_requests']."\n";
    echo 'PROVIDER_HTTP_STATUS='.($result['http_status'] ?? 'NONE')."\n";
    echo 'FEED_ITEMS_INSPECTED='.$result['feed_item_count']."\n";
    echo 'GOOGLE_TREND_TERMS='.$result['trend_terms']."\n";
    echo 'WATCH_KEYWORD_MATCH_CANDIDATES='.$result['keyword_match_candidates']."\n";
    echo 'SHADOW_OBSERVATIONS='.$result['shadow_observations']."\n";
    echo 'SESSION_ID='.$result['session_id']."\n";

    $resultsJson = DB::table('sifi_research_sessions')
        ->where('id', $result['session_id'])->value('results_json');
    $saved = is_string($resultsJson) ? json_decode($resultsJson, true, 32, JSON_THROW_ON_ERROR) : [];
    $envelope = $saved['multi_source']['envelope'] ?? [];
    foreach (array_slice($envelope['entries'] ?? [], 0, 3) as $i => $entry) {
        echo 'TREND_'.($i + 1).'='.($entry['google_trend_term'] ?? 'UNKNOWN')."\n";
        echo 'TREND_URL_'.($i + 1).'='.($entry['canonical_url'] ?? 'NONE')."\n";
    }
    if ($result['state'] !== 'OK') {
        echo 'FAILURE_CATEGORY='.($result['failure_category'] ?? 'UNKNOWN')."\n";
    }
    echo "RAW_RSS_PERSISTED=NO\nFULL_ARTICLE_STORAGE=NONE\n";
    echo "SEARCH_TRENDS_NOT_VERIFIED_EVENTS=YES\nEVIDENCE_ADMISSION=NONE\nPRODUCTION_ADMISSION=DENIED\n";
    echo 'SIFI_GOOGLE_PILOT='.($result['state'] === 'OK' && $result['trend_terms'] > 0
        ? 'PASS' : 'PROVIDER_UNAVAILABLE_OR_EMPTY')."\n";
    exit($result['state'] === 'OK' && $result['trend_terms'] > 0 ? 0 : 1);
} catch (Throwable $error) {
    fwrite(STDERR, 'SIFI_GOOGLE_PILOT=HELD: '.$error->getMessage()."\n");
    fwrite(STDERR, "ISOLATED_DB_RETAINED_FOR_DIAGNOSIS=$dbPath\n");
    exit(1);
}
