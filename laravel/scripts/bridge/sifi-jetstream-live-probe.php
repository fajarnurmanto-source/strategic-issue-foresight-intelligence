<?php

/**
 * One bounded public Jetstream v2 live-tail diagnostic (no DB, no credentials).
 * Run from WSL: php scripts/bridge/sifi-jetstream-live-probe.php sampah
 * Public post text is inspected in memory and never stored or printed.
 */
declare(strict_types=1);

const HOST = 'jetstream.us-east.bsky.network';
const PATH = '/xrpc/network.bsky.jetstream.subscribeEvents?collections=app.bsky.feed.post&kinds=commit';
const MAX_SECONDS = 25;
const MAX_EVENTS = 4000;
const MAX_BYTES = 12582912; // 12 MiB, including unmatched frames.
const MAX_MATCHES = 10;
const MAX_FRAME_BYTES = 131072;

$term = $argv[1] ?? 'sampah';
if (! is_string($term) || preg_match('/\A[\p{L}\p{N} ._#-]{3,80}\z/uD', $term) !== 1) {
    fwrite(STDERR, "JETSTREAM_PROBE=HELD: Invalid local keyword.\n");
    exit(1);
}
if (! extension_loaded('openssl') || ! extension_loaded('mbstring')) {
    fwrite(STDERR, "JETSTREAM_PROBE=HELD: PHP openssl and mbstring extensions are required.\n");
    exit(1);
}

/** @param resource $stream */
function receiveBytes($stream, int $length, float $deadline, int &$wireBytes): string
{
    if ($length < 0 || $length > MAX_FRAME_BYTES || $wireBytes + $length > MAX_BYTES) {
        throw new RuntimeException('JETSTREAM_BYTE_BOUND');
    }
    $bytes = '';
    while (strlen($bytes) < $length) {
        $timeLeft = $deadline - microtime(true);
        if ($timeLeft <= 0) {
            throw new RuntimeException('JETSTREAM_TIME_BOUND');
        }
        $read = [$stream];
        $write = null;
        $except = null;
        $seconds = (int) floor(min($timeLeft, 2));
        $microseconds = (int) ((min($timeLeft, 2) - $seconds) * 1000000);
        if (stream_select($read, $write, $except, $seconds, $microseconds) === false) {
            throw new RuntimeException('JETSTREAM_SOCKET_SELECT_FAILED');
        }
        if ($read === []) {
            continue;
        }
        $chunk = fread($stream, $length - strlen($bytes));
        if ($chunk === false || ($chunk === '' && feof($stream))) {
            throw new RuntimeException('JETSTREAM_STREAM_CLOSED');
        }
        $bytes .= $chunk;
    }
    $wireBytes += $length;

    return $bytes;
}

/** Client control frames must carry a masking key. @param resource $stream */
function sendControl($stream, int $opcode, string $payload = ''): void
{
    if (strlen($payload) > 125) {
        throw new RuntimeException('JETSTREAM_CONTROL_FRAME_BOUND');
    }
    $mask = random_bytes(4);
    $frame = chr(0x80 | $opcode).chr(0x80 | strlen($payload)).$mask;
    for ($i = 0; $i < strlen($payload); $i++) {
        $frame .= $payload[$i] ^ $mask[$i % 4];
    }
    if (fwrite($stream, $frame) !== strlen($frame)) {
        throw new RuntimeException('JETSTREAM_CONTROL_SEND_FAILED');
    }
}

/** @param resource $stream */
function nextTextFrame($stream, float $deadline, int &$wireBytes): ?string
{
    while (true) {
        $header = receiveBytes($stream, 2, $deadline, $wireBytes);
        $fin = (ord($header[0]) & 0x80) !== 0;
        $opcode = ord($header[0]) & 0x0F;
        $masked = (ord($header[1]) & 0x80) !== 0;
        $length = ord($header[1]) & 0x7F;
        if ($length === 126) {
            $length = unpack('n', receiveBytes($stream, 2, $deadline, $wireBytes))[1];
        } elseif ($length === 127) {
            $len = unpack('Nhigh/Nlow', receiveBytes($stream, 8, $deadline, $wireBytes));
            if ($len['high'] !== 0) {
                throw new RuntimeException('JETSTREAM_FRAME_TOO_LARGE');
            }
            $length = $len['low'];
        }
        if ($masked || $length > MAX_FRAME_BYTES || ! $fin
            || ($opcode >= 0x8 && $length > 125)) {
            throw new RuntimeException('JETSTREAM_UNSUPPORTED_FRAME');
        }
        $payload = receiveBytes($stream, $length, $deadline, $wireBytes);
        if ($opcode === 0x8) {
            throw new RuntimeException('JETSTREAM_PROVIDER_CLOSED_CONNECTION');
        }
        if ($opcode === 0x9) {
            sendControl($stream, 0xA, $payload);

            continue;
        }
        if ($opcode === 0xA) {
            continue;
        }
        if ($opcode !== 0x1) {
            throw new RuntimeException('JETSTREAM_UNEXPECTED_FRAME_KIND');
        }

        return $payload;
    }
}

$context = stream_context_create(['ssl' => [
    'verify_peer' => true,
    'verify_peer_name' => true,
    'allow_self_signed' => false,
    'peer_name' => HOST,
    'SNI_enabled' => true,
]]);
$stream = @stream_socket_client('tls://'.HOST.':443', $errno, $errstr, 8, STREAM_CLIENT_CONNECT, $context);
if ($stream === false) {
    fwrite(STDERR, "JETSTREAM_PROBE=UNAVAILABLE: TLS_CONNECTION_FAILED\n");
    exit(1);
}

$deadline = microtime(true) + MAX_SECONDS;
$key = base64_encode(random_bytes(16));
$request = 'GET '.PATH." HTTP/1.1\r\n"
    .'Host: '.HOST."\r\n"
    ."Upgrade: websocket\r\nConnection: Upgrade\r\n"
    .'Sec-WebSocket-Key: '.$key."\r\nSec-WebSocket-Version: 13\r\n"
    ."Sec-WebSocket-Protocol: xrpc.v1.json\r\n"
    ."User-Agent: SIFI-Isolated-Public-Jetstream-Probe/1.0\r\n\r\n";

try {
    stream_set_timeout($stream, 8);
    if (fwrite($stream, $request) !== strlen($request)) {
        throw new RuntimeException('JETSTREAM_HANDSHAKE_SEND_FAILED');
    }
    $headers = '';
    while (! str_contains($headers, "\r\n\r\n")) {
        if (strlen($headers) >= 8192 || microtime(true) >= $deadline) {
            throw new RuntimeException('JETSTREAM_HANDSHAKE_BOUND');
        }
        $line = fgets($stream, 2048);
        if ($line === false) {
            throw new RuntimeException('JETSTREAM_HANDSHAKE_UNAVAILABLE');
        }
        $headers .= $line;
    }
    if (! preg_match('#\AHTTP/1\.[01] 101\b#', $headers)) {
        preg_match('#\AHTTP/1\.[01] (\d{3})\b#', $headers, $status);
        throw new RuntimeException('JETSTREAM_HTTP_'.($status[1] ?? 'UNKNOWN'));
    }
    $accept = base64_encode(sha1($key.'258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
    if (! preg_match('/^Sec-WebSocket-Accept:\s*'.preg_quote($accept, '/').'\s*$/mi', $headers)) {
        throw new RuntimeException('JETSTREAM_HANDSHAKE_INTEGRITY');
    }

    stream_set_blocking($stream, true);
    $events = 0;
    $wireBytes = 0;
    $matches = [];
    $seen = [];
    $finishReason = 'TIME_BOUND';
    while (microtime(true) < $deadline && $events < MAX_EVENTS && $wireBytes < MAX_BYTES) {
        try {
            $json = nextTextFrame($stream, $deadline, $wireBytes);
        } catch (RuntimeException $error) {
            if ($error->getMessage() === 'JETSTREAM_TIME_BOUND') {
                break;
            }
            throw $error;
        }
        $events++;
        $item = json_decode((string) $json, true, 24);
        $post = is_array($item) ? ($item['payload'] ?? null) : null;
        if (! is_array($post) || ($post['collection'] ?? null) !== 'app.bsky.feed.post'
            || ($post['operation'] ?? null) !== 'create'
            || ! is_array($post['record'] ?? null)) {
            continue;
        }
        $did = $post['did'] ?? null;
        $rkey = $post['rkey'] ?? null;
        $body = $post['record']['text'] ?? null;
        if (! is_string($did) || ! is_string($rkey) || ! is_string($body)
            || preg_match('/\Adid:(?:plc:[a-z2-7]{24}|web:[A-Za-z0-9._%:-]{1,180})\z/D', $did) !== 1
            || preg_match('/\A[A-Za-z0-9]{5,64}\z/D', $rkey) !== 1
            || mb_strlen($body) > 3000 || mb_stripos($body, $term) === false) {
            continue;
        }
        $url = 'https://bsky.app/profile/'.$did.'/post/'.$rkey;
        if (isset($seen[$url])) {
            continue;
        }
        $seen[$url] = true;
        $matches[] = $url;
        if (count($matches) >= MAX_MATCHES) {
            $finishReason = 'MAX_MATCHES';
            break;
        }
    }
    if ($events >= MAX_EVENTS) {
        $finishReason = 'MAX_EVENTS';
    } elseif ($wireBytes >= MAX_BYTES) {
        $finishReason = 'MAX_BYTES';
    }
    echo "JETSTREAM_PROBE=PASS\n";
    echo "SOURCE=BLUESKY_JETSTREAM_V2_PUBLIC_LIVE\n";
    echo "AUTH=NONE\n";
    echo 'KEYWORD='.$term."\n";
    echo 'EVENTS_INSPECTED='.$events."\n";
    echo 'MATCHING_PUBLIC_POST_LINKS='.count($matches)."\n";
    echo 'NETWORK_BYTES='.$wireBytes."\n";
    echo 'STOPPING_REASON='.$finishReason."\n";
    foreach (array_slice($matches, 0, 3) as $index => $url) {
        echo 'PUBLIC_POST_LINK_'.($index + 1).'='.$url."\n";
    }
    echo "POST_BODY_PERSISTED=NO\nDATABASE_WRITES=NONE\nPRODUCTION_ADMISSION=DENIED\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'JETSTREAM_PROBE=UNAVAILABLE: '.$error->getMessage()."\n");
    exit(1);
} finally {
    fclose($stream);
}
