<?php
declare(strict_types=1);

/**
 * GitHub Actions-only deterministic loopback simulator.
 * Never bound into Laravel's application, never proxies an external request.
 * The two servers must bind to 127.0.0.1 (ports 39588 and 39601).
 */
$host = (string) ($_SERVER['HTTP_HOST'] ?? '');
$port = (int) ($_SERVER['SERVER_PORT'] ?? 0);
$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
if ($ip !== '127.0.0.1' || !in_array($port, [39588, 39601], true) ||
    !in_array($host, ['127.0.0.1:39588', '127.0.0.1:39601'], true)) {
    http_response_code(403);
    exit;
}
if (($_SERVER['REQUEST_URI'] ?? '') === '/health') {
    header('Content-Type: text/plain');
    echo 'SIFI_CI_LOOPBACK_ONLY';
    return;
}
if ($port === 39588) {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        return;
    }
    $body = json_decode((string) file_get_contents('php://input'), true);
    $uri = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    $protocol = str_contains($uri, ':generateContent') ? 'gemini'
        : ($uri === '/api/chat' ? 'ollama' : ($uri === '/chat/completions' ? 'chat' : 'unknown'));
    if ($protocol === 'unknown' || !is_array($body)) {
        http_response_code(400);
        return;
    }
    $model = $protocol === 'gemini'
        ? substr(basename($uri), 0, -strlen(':generateContent'))
        : (string) ($body['model'] ?? 'fixture-a');
    if ($model === 'slow') {
        usleep(1400000); // local bounded timeout testcase
    }
    if ($model === 'http-error') {
        http_response_code(503);
        return;
    }
    if ($model === 'oversized') {
        header('Content-Type: application/json');
        echo str_repeat('X', 70000);
        return;
    }
    if ($model === 'malformed') {
        header('Content-Type: application/json');
        echo '{"broken":';
        return;
    }
    $output = json_encode([
        'explanation' => 'SYNTHETIC ONLY: this is a local test fixture, not evidence.',
        'unknowns' => 'Unknown',
        'next_checks' => 'Check source',
    ], JSON_THROW_ON_ERROR);
    if ($model === 'invented-ref') {
        $output = json_encode([
            'explanation' => 'SYNTHETIC ONLY',
            'unknowns' => 'Unknown',
            'next_checks' => 'Check source',
            'evidence_admitted' => true,
        ], JSON_THROW_ON_ERROR);
    }
    if ($protocol === 'gemini') {
        $reply = [
            'modelVersion' => $model,
            'candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => $output]]]]],
            'usageMetadata' => ['totalTokenCount' => 31],
        ];
    } elseif ($protocol === 'ollama') {
        $reply = ['model' => $model, 'done' => true, 'message' => ['content' => $output], 'eval_count' => 31];
    } else {
        $reply = [
            'model' => $model,
            'choices' => [['finish_reason' => 'stop', 'message' => ['content' => $output]]],
            'usage' => ['total_tokens' => 31],
        ];
    }
    header('Content-Type: application/json');
    echo json_encode($reply, JSON_THROW_ON_ERROR);
    return;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET' ||
    (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) !== '/data') {
    http_response_code(404);
    return;
}
$root = getenv('R17_RESULT_DIR');
if (!$root || !is_dir($root.'/evidence/mock')) {
    http_response_code(503);
    return;
}
$scenario = trim((string) @file_get_contents($root.'/evidence/mock/scenario.txt'));
file_put_contents($root.'/evidence/mock/requests.jsonl',
    json_encode(['scenario' => $scenario, 'time' => microtime(true)], JSON_THROW_ON_ERROR)."\n", FILE_APPEND | LOCK_EX);
if ($scenario === 'slow_headers') {
    sleep(6);
}
if ($scenario === 'redirect') {
    header('Location: http://127.0.0.1/forbidden-redirect-target', true, 302);
    return;
}
if ($scenario === 'rate') {
    http_response_code(429);
    return;
}
if ($scenario === 'http_error') {
    http_response_code(503);
    return;
}
if ($scenario === 'html') {
    header('Content-Type: text/html');
    echo '<html>CI fixture only</html>';
    return;
}
if ($scenario === 'encoding') {
    header('Content-Encoding: gzip');
}
if ($scenario === 'disconnect') {
    // A deliberately incomplete 200 body emulates a broken wire, not an HTTP 503.
    // Guzzle/cURL must fail the transfer before a complete response can be accepted.
    header('Content-Type: application/json');
    header('Content-Length: 4096');
    echo '{"incomplete":';
    flush();
    return;
}
$fixture = dirname(__DIR__, 2).'/laravel/resources/sifi/ae09-wb-response.json';
$packet = json_decode((string) file_get_contents($fixture), true, 16, JSON_THROW_ON_ERROR);
$packet[0]['lastupdated'] = in_array($scenario, ['revised'], true) ? '2026-10-26' : '2026-10-25';
foreach ($packet[1] as $i => &$record) {
    $record['value'] = [1.25, 2.5, 3.75][$i];
}
unset($record);
if ($scenario === 'missing') unset($packet[0]['lastupdated']);
if ($scenario === 'stale') $packet[0]['lastupdated'] = '2020-01-01';
if ($scenario === 'schema') $packet[1][0]['indicator']['id'] = 'INVALID';
if ($scenario === 'too_many') $packet[0]['pages'] = 2;
if ($scenario === 'duplicate') $packet[1][1] = $packet[1][0];
if ($scenario === 'revised') $packet[1][0]['value'] = 1.5;
if ($scenario === 'same_version_conflict') $packet[1][0]['value'] = 1.5;
$raw = json_encode($packet, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
if ($scenario === 'malformed') $raw = '{broken';
if ($scenario === 'oversize_length') $raw = str_repeat('x', 65537);
if ($scenario === 'oversize_chunked') $raw = str_repeat('x', 70000);
header('Content-Type: application/json');
if ($scenario === 'slow_body') {
    echo substr($raw, 0, 50);
    @ob_flush();
    flush();
    sleep(6);
    echo substr($raw, 50);
    return;
}
echo $raw;
