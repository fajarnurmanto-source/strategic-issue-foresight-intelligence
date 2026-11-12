<?php

// Local owner-operated, single-use approval generator for existing AE16 gateway.
// No HTTP requests are made here. Run only on the trusted local SIFI host.
declare(strict_types=1);
use App\Services\Sifi\OneShotSearchAuthority;
use App\Services\Sifi\SearchProviderRegistry;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

$root = '/home/fajar/projects/sifi-laravel-vnext';
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
function ask(string $q): string
{
    fwrite(STDOUT, $q);
    $s = fgets(STDIN);
    if ($s === false) {
        throw new RuntimeException('INPUT_ABORTED');
    }

return trim($s);
}
function stop(string $code): never
{
    fwrite(STDERR, "F04B_APPROVAL_BLOCKED=$code\n");
    exit(2);
}
try {
    $registry = app(SearchProviderRegistry::class);
    $sessions = DB::table('sifi_research_sessions')->where('expires_at', '>', now())->orderByDesc('created_at')->limit(12)->get();
    if ($sessions->isEmpty()) {
        stop('NO_ACTIVE_RESEARCH_SESSION');
    }
    echo "F04B LOCAL OPERATOR: metadata only, one approved request, no retry.\n";
    foreach ($sessions as $i => $s) {
        $q = mb_substr(preg_replace('/\s+/', ' ', (string) $s->question), 0, 85);
        echo sprintf("[%d] %s | %s | %s\n", $i + 1, substr($s->id, 0, 8), $q, $s->object_id);
    }
    $n = ask('Pilih nomor Research Session: ');
    if (! ctype_digit($n) || ($k = (int) $n - 1) < 0 || ! isset($sessions[$k])) {
        stop('SESSION_SELECTION');
    }
    $s = $sessions[$k];
    $object = DB::table('sifi_monitoring_objects')->where('id', $s->object_id)->first();
    if (! $object || $object->state !== 'CONFIGURED' || (int) $object->revision !== (int) $s->object_revision) {
        stop('OBJECT_REVISION_OR_STATE');
    }
    $previous = json_decode($s->results_json, true, 32, JSON_THROW_ON_ERROR);
    if (isset($previous['ae16_live_search'])) {
        stop('SESSION_ALREADY_HAS_LIVE_RESULT');
    }
    $provider = ask('Provider [CROSSREF/WIKIPEDIA_ID] (default CROSSREF): ');
    if ($provider === '') {
        $provider = 'CROSSREF';
    }if (! in_array($provider, ['CROSSREF', 'WIKIPEDIA_ID'], true)) {
        stop('PROVIDER_NOT_ALLOWED');
    }
    $p = $registry->get($provider);
    if (now()->toDateString() >= $p['expires_on']) {
        stop('PROVIDER_REVIEW_EXPIRED');
    }
    $query = ask('Query persis di form SIFI (default Yogyakarta): ');
    if ($query === '') {
        $query = 'Yogyakarta';
    }if (mb_strlen($query) < 2 || mb_strlen($query) > 200) {
        stop('QUERY_INVALID');
    }
    $start = ask('Mulai YYYY-MM-DD (default 2025-01-01): ');
    if ($start === '') {
        $start = '2025-01-01';
    }
    $end = ask('Akhir eksklusif YYYY-MM-DD (default 2026-01-01): ');
    if ($end === '') {
        $end = '2026-01-01';
    }
    foreach ([$start, $end] as $d) {
        if (! preg_match('/\A\d{4}-\d{2}-\d{2}\z/D', $d) || ! checkdate((int) substr($d, 5, 2), (int) substr($d, 8, 2), (int) substr($d, 0, 4))) {
            stop('DATE_INVALID');
        }
    }
    if ($start >= $end) {
        stop('DATE_ORDER');
    }
    $limit = 5;
    $context = ['mode' => 'LIVE', 'query' => $query, 'query_sha256' => hash('sha256', $query), 'limit' => $limit, 'date_window' => ['start' => $start, 'end' => $end],
        'binding' => ['owner_id' => $s->user_id, 'object_id' => $s->object_id, 'session_id' => $s->id, 'object_revision' => (int) $object->revision, 'plan_sha256' => hash('sha256', json_encode(json_decode($s->plan_json, true, 32, JSON_THROW_ON_ERROR), JSON_THROW_ON_ERROR))]];
    // Validate exact reviewed provider parameters before allowing approval.
    $registry->parameters($provider, $query, $limit, $context);
    $email = ask('Alamat email kontak operator untuk User-Agent provider: ');
    if (! filter_var($email, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $email) || str_ends_with($email, '.invalid') || str_ends_with($email, '.test')) {
        stop('CONTACT_REQUIRED');
    }
    echo "\nREVIEW: provider=$provider endpoint={$p['endpoint']}\nquery=$query | start=$start | end_exclusive=$end | limit=5\n";
    echo "session={$s->id} | object={$s->object_id}\n";
    echo "cost=0 | request_limit=1 | max_response=32768 bytes | browser will perform the network request later\n";
    $phrase = ask('Setujui SATU request metadata via gateway? Ketik SETUJU-1-REQUEST: ');
    if ($phrase !== 'SETUJU-1-REQUEST') {
        stop('NOT_APPROVED');
    }
    $authority = app(OneShotSearchAuthority::class);
    $path = $authority->path($provider, $context);
    if (is_file($path) || is_link($path)) {
        stop('AUTHORITY_ALREADY_EXISTS');
    }
    $dir = dirname($path);
    if (! is_dir($dir) && ! mkdir($dir, 0700, true) && ! is_dir($dir)) {
        stop('AUTHORITY_DIRECTORY');
    }
    $scope = hash('sha256', json_encode(['provider' => $provider, 'context' => $context, 'endpoint' => $p['endpoint'], 'contact' => $email, 'reviewed_on' => $p['reviewed_on']], JSON_THROW_ON_ERROR));
    $grant = ['version' => 'sifi-search-authority-1', 'context_hash' => $authority->contextHash($provider, $context), 'endpoint' => $p['endpoint'], 'method' => $p['method'] ?? 'GET',
        'cost_limit' => 0, 'request_limit' => 1, 'response_byte_limit' => 32768, 'consumed' => false, 'expires_at' => now()->addMinutes(15)->timestamp,
        'approval_scope_sha256' => $scope, 'nonce' => bin2hex(random_bytes(32)), 'contact_email' => $email];
    $cipher = Crypt::encryptString(json_encode($grant, JSON_THROW_ON_ERROR));
    $f = fopen($path, 'x');
    if ($f === false) {
        stop('AUTHORITY_RACE');
    }chmod($path, 0600);
    try {
        if (fwrite($f, $cipher) !== strlen($cipher) || ! fflush($f)) {
            throw new RuntimeException('WRITE_FAILED');
        }if (function_exists('fsync')) {
            fsync($f);
        }
    } finally {
        fclose($f);
    }
    echo "F04B_GRANT_READY=YES\nF04B_GRANT_EXPIRES_IN_MINUTES=15\nF04B_REQUESTS_SENT=0\n";
    echo "Open SIFI Gateway; select SAME session/provider/query/dates and click once. DO NOT rerun on failure.\n";
} catch (Throwable $e) {
    stop(preg_replace('/[^A-Za-z0-9_]/','_',mb_substr($e->getMessage(),0,90)));
}
