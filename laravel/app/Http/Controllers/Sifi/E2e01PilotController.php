<?php

namespace App\Http\Controllers\Sifi;

use App\Http\Controllers\Controller;
use App\Models\SifiMonitoringObject;
use App\Services\Sifi\ResearchRetrievalWorkspace;
use App\Services\Sifi\ResearchSessionWorkspace;
use App\Sifi\CoreV2\ShadowPipeline;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Single-session, operator-requested live document pilot.
 * Restricted, reviewed host allowlist; no automatic evidence admission or AI.
 * Deliberately independent from synthetic SourceObservationPipeline.
 */
final class E2e01PilotController extends Controller
{
    private const HOSTS = ['warta.jogjakota.go.id', 'lingkunganhidup.jogjakota.go.id', 'ppid-files.jogjakota.go.id'];

    private const MAX_BYTES = 1500000;

    public function index(Request $request, SifiMonitoringObject $object): Response
    {
        Gate::authorize('view', $object);
        $sessions = app(ResearchSessionWorkspace::class)->history($request->user(), $object);
        $sessions = array_values(array_filter($sessions, static fn ($session) => ! $session['expired'] && $session['object_revision'] === $object->revision));
        $shadowSessions = [];
        foreach ($sessions as $session) {
            $sessionId = $session['id'] ?? null;
            $revision = $session['object_revision'];
            $results = $session['results'] ?? null;
            if (! is_string($sessionId) || ! is_array($results)) {
                continue;
            }
            // A JSON result object, not an arbitrary list, is required by Core V2.
            $validResults = true;
            foreach (array_keys($results) as $key) {
                if (! is_string($key)) {
                    $validResults = false;
                    break;
                }
            }
            if (! $validResults) {
                continue;
            }
            /** @var array<string, mixed> $results */
            $shadowSessions[] = [
                'id' => $sessionId,
                'object_revision' => $revision,
                'results' => $results,
            ];
        }
        $timeline = config('sifi_core_v2.shadow_workbench') === true
            ? app(ShadowPipeline::class)->forSessions($request->user(), $object, $shadowSessions)
            : [];
        $sessions = array_map(static function ($s) use ($timeline) {
            $p = $s['results']['e2e01_pilot'] ?? null;

            return ['id' => $s['id'], 'question' => $s['question'], 'expired' => $s['expired'],
                'object_revision' => $s['object_revision'], 'pilot' => $p,
                'shadow_timeline' => $timeline[$s['id']] ?? null,
                'e2e02_image' => $s['results']['e2e02_image'] ?? null,
                'e2e02_review_packet' => $s['results']['e2e02_review_packet'] ?? null,
                'e2e03_intelligence' => $s['results']['e2e03_intelligence'] ?? null,
                'e2e04_handoff' => $s['results']['e2e04_handoff'] ?? null];
        }, $sessions);

        return Inertia::render('sifi/e2e01-pilot', [
            'object' => $object->only(['id', 'name', 'revision', 'state']),
            'sessions' => $sessions, 'hosts' => self::HOSTS,
            'notice' => 'Pilot publikasi pemerintah: pengambilan SATU halaman HTML/PDF secara manual; kutipan dan ringkasan adalah DRAFT, bukan evidence terverifikasi. Tidak ada crawling lanjutan, scheduler, atau AI.']);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['pilot' => $message]);
    }

    private function session(Request $r, SifiMonitoringObject $o, string $id): \stdClass
    {
        if ($o->state !== 'CONFIGURED') {
            $this->fail('Objek tidak CONFIGURED.');
        }

        return app(ResearchRetrievalWorkspace::class)->session($r->user(), $o, $id);
    }

    /** @return array{0:string,1:string} */
    private function canonical(string $raw): array
    {
        $url = trim($raw);
        if (strlen($url) > 1000 || (preg_match('/[\x00-\x20]/', $url) || str_contains($url, chr(92)))) {
            $this->fail('URL tidak valid.');
        }
        $p = parse_url($url);
        if (! is_array($p) || ($p['scheme'] ?? '') !== 'https' || ! in_array(strtolower($p['host'] ?? ''), self::HOSTS, true)
            || isset($p['user']) || isset($p['pass']) || isset($p['port']) || isset($p['fragment'])) {
            $this->fail('Pilot hanya mendukung tiga domain pemerintah yang direview, HTTPS tanpa redirect.');
        }
        if (str_contains($url, '%')) {
            $this->fail('URL dengan encoding belum didukung pilot.');
        }

        return [$url, strtolower((string) ($p['host'] ?? ''))];
    }

    /** @return array{0:string,1:string,2:int} */
    private function fetch(string $url, string $host): array
    {
        if (! extension_loaded('curl')) {
            $this->fail('PHP curl belum tersedia.');
        }
        $ipv4s = gethostbynamel($host);
        if (! is_array($ipv4s) || count($ipv4s) < 1) {
            $this->fail('DNS host tidak tersedia. Tidak ada request konten.');
        }
        foreach ($ipv4s as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4|FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)) {
                $this->fail('DNS host tidak aman.');
            }
        }
        $ip = $ipv4s[0];
        $handle = curl_init($url);
        $bytes = '';
        $tooLarge = false;
        curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => false, CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 12, CURLOPT_MAXREDIRS => 0, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_USERAGENT => 'SIFI-E2E01-research-pilot/1.0 (manual; metadata+bounded document)',
            CURLOPT_HTTPHEADER => ['Accept: text/html,application/pdf;q=0.8', 'Accept-Encoding: identity'],
            CURLOPT_RESOLVE => [$host.':443:'.$ip], CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_WRITEFUNCTION => static function ($ch, string $chunk) use (&$bytes, &$tooLarge): int {
                if (strlen($bytes) + strlen($chunk) > self::MAX_BYTES) {
                    $tooLarge = true;

                    return 0;
                }
                $bytes .= $chunk;

                return strlen($chunk);
            }]);
        $ok = curl_exec($handle);
        $http = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $ctype = (string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE);
        $err = (string) curl_error($handle);
        curl_close($handle);
        if ($tooLarge) {
            $this->fail('Respons melebihi 1,5 MB; tidak disimpan.');
        }
        if ($ok === false) {
            $this->fail('Gagal menghubungi sumber: '.mb_substr($err, 0, 130));
        }
        if ($http !== 200) {
            $this->fail('Sumber memberi HTTP '.$http.'; tidak ada auto-retry atau redirect.');
        }
        if (strlen($bytes) < 20) {
            $this->fail('Respons terlalu kecil untuk ekstraksi.');
        }

        return [$bytes, $ctype, $http];
    }

    /** @return array{kind:string,text:string,images:list<array{src:string,alt:string}>,limitations:string} */
    private function extract(string $raw, string $ctype, string $url): array
    {
        $isPdf = str_starts_with(strtolower($ctype), 'application/pdf') || str_starts_with($raw, '%PDF-');
        if ($isPdf) {
            $exe = trim((string) shell_exec('command -v pdftotext 2>/dev/null'));
            if (! $exe || ! is_executable($exe)) {
                $this->fail('PDF diterima, tetapi pdftotext/Poppler belum tersedia. Tidak ada klaim ekstraksi.');
            }
            $tmp = tempnam(sys_get_temp_dir(), 'sifi_pdf_');
            $out = tempnam(sys_get_temp_dir(), 'sifi_text_');
            try {
                file_put_contents($tmp, $raw);
                $proc = proc_open([$exe, '-f', '1', '-l', '5', '-layout', $tmp, $out], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
                if (! is_resource($proc)) {
                    $this->fail('Ekstraktor PDF tidak berjalan.');
                }
                foreach ($pipes as $p) {
                    fclose($p);
                }
                $exit = proc_close($proc);
                if ($exit !== 0) {
                    $this->fail('Ekstraksi PDF gagal.');
                }
                $text = (string) file_get_contents($out);
            } finally {
                if (is_file($tmp)) {
                    unlink($tmp);
                }if (is_file($out)) {
                    unlink($out);
                }
            }
            $text = preg_replace('/\s+/u', ' ', $text) ?? '';

            return ['kind' => 'PDF_PAGE_TEXT_1_TO_5', 'text' => mb_substr($text, 0, 16000), 'images' => [],
                'limitations' => 'Hanya teks halaman 1–5. Gambar/tabel dalam PDF belum diinterpretasikan; OCR tidak dilakukan.'];
        }
        if (! preg_match('~(?:text/html|application/xhtml\+xml)~i', $ctype)) {
            $this->fail('Konten bukan HTML/PDF yang didukung.');
        }
        $dom = new \DOMDocument;
        $prior = libxml_use_internal_errors(true);
        try {
            $dom->loadHTML('<?xml encoding="UTF-8"?>'.$raw, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($prior);
        }
        $xp = new \DOMXPath($dom);
        $removeNodes = $xp->query('//script|//style|//nav|//footer|//header|//form|//noscript');
        if ($removeNodes === false) {
            $this->fail('Struktur HTML tidak dapat dianalisis.');
        }
        foreach ($removeNodes as $node) {
            if ($node instanceof \DOMNode) {
                $node->parentNode?->removeChild($node);
            }
        }
        $parts = [];
        $contentNodes = $xp->query('//h1|//h2|//h3|//p|//li');
        if ($contentNodes === false) {
            $this->fail('Struktur HTML tidak dapat dianalisis.');
        }
        foreach ($contentNodes as $node) {
            if (! $node instanceof \DOMNode) {
                continue;
            }
            $s = trim(preg_replace('/\s+/u', ' ', $node->textContent) ?? '');
            if (mb_strlen($s) >= 30 && mb_strlen($s) <= 1000) {
                $parts[] = $s;
            }
            if (count($parts) >= 100) {
                break;
            }
        }
        $images = [];
        $imageNodes = $xp->query('//img[@src]');
        if ($imageNodes === false) {
            $this->fail('Referensi gambar tidak dapat dianalisis.');
        }
        foreach ($imageNodes as $img) {
            if (! $img instanceof \DOMElement) {
                continue;
            }
            $src = trim($img->getAttribute('src'));
            if ($src === '' || str_starts_with($src, 'data:')) {
                continue;
            }
            $images[] = ['src' => mb_substr($src, 0, 400), 'alt' => mb_substr($img->getAttribute('alt'), 0, 200)];
            if (count($images) >= 8) {
                break;
            }
        }

        return ['kind' => 'HTML_TEXT_AND_IMAGE_REFERENCES', 'text' => mb_substr(implode("\n", array_unique($parts)), 0, 16000),
            'images' => $images, 'limitations' => 'Gambar hanya direkam atribut src/alt; berkas gambar tidak diunduh atau dianalisis. Tanggal publikasi dan identitas penerbit belum diverifikasi.'];
    }

    /** @param array<string,mixed> $packet */
    private function persist(Request $r, SifiMonitoringObject $object, \stdClass $session, array $packet, string $action): void
    {
        DB::transaction(function () use ($r, $object, $session, $packet, $action) {
            $freshObject = SifiMonitoringObject::whereKey($object->id)->lockForUpdate()->firstOrFail();
            if ($freshObject->state !== 'CONFIGURED' || (int) $freshObject->revision !== (int) $object->revision) {
                $this->fail('Objek direvisi selama pemrosesan.');
            }
            $row = DB::table('sifi_research_sessions')->where('id', $session->id)->where('user_id', $r->user()->id)->where('object_id', $object->id)->lockForUpdate()->firstOrFail();
            if ((int) $row->object_revision !== $object->revision || now()->gte($row->expires_at)) {
                $this->fail('Sesi berubah/kedaluwarsa.');
            }
            $data = json_decode($row->results_json, true, 32, JSON_THROW_ON_ERROR);
            if ($action === 'E2E01_ACQUIRED' && isset($data['e2e01_pilot'])) {
                $this->fail('Sesi sudah memiliki pilot, buat sesi baru untuk sumber lain.');
            }
            if ($action === 'E2E01_BRIEF_DRAFTED' && (! isset($data['e2e01_pilot']) || isset($data['e2e01_pilot']['brief']))) {
                $this->fail('Dokumen belum diambil atau brief telah dibuat.');
            }
            $data['e2e01_pilot'] = $packet;
            DB::table('sifi_research_sessions')->where('id', $session->id)->update(['results_json' => json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
            DB::table('sifi_research_session_audit')->insert(['session_id' => $session->id, 'actor_user_id' => $r->user()->id, 'action' => $action,
                'snapshot_json' => json_encode(['url_sha256' => hash('sha256', $packet['source_url']), 'body_sha256' => $packet['body_sha256'],
                    'document_kind' => $packet['extraction']['kind'], 'review_state' => 'NOT_EVALUATED', 'evidence_admission' => 'NONE', 'brief_sha256' => isset($packet['brief']) ? hash('sha256', $packet['brief']) : null], JSON_THROW_ON_ERROR), 'created_at' => now()]);
        });
    }

    public function acquire(Request $r, SifiMonitoringObject $object): RedirectResponse
    {
        Gate::authorize('update', $object);
        $v = $r->validate(['session_id' => ['required', 'uuid'], 'object_revision' => ['required', 'integer'], 'url' => ['required', 'url', 'max:1000'],
            'consent' => ['required', 'accepted']]);
        if ($object->revision !== (int) $v['object_revision']) {
            $this->fail('Revisi objek berubah.');
        }
        $s = $this->session($r, $object, $v['session_id']);
        $prior = json_decode($s->results_json, true, 32, JSON_THROW_ON_ERROR);
        if (isset($prior['e2e01_pilot'])) {
            $this->fail('Sesi ini sudah digunakan untuk pilot; tidak ada pengulangan otomatis.');
        }
        [$url,$host] = $this->canonical($v['url']);
        [$raw,$ctype,$http] = $this->fetch($url, $host);
        $extract = $this->extract($raw, $ctype, $url);
        if (mb_strlen($extract['text']) < 60) {
            $this->fail('Ekstraksi tidak cukup untuk membuat kutipan; dokumen tidak disimpan.');
        }
        $packet = ['schema' => 'sifi-e2e01-pilot-1', 'state' => 'RETRIEVED_EXTRACTED', 'source_url' => $url, 'source_host' => $host,
            'http_status' => $http, 'content_type' => $ctype, 'retrieved_at' => now()->toIso8601String(), 'raw_bytes' => strlen($raw),
            'body_sha256' => hash('sha256', $raw), 'network_requests' => 1, 'extraction' => $extract,
            'evidence_admission' => 'NONE', 'claim_verification' => 'NOT_PERFORMED', 'rights' => 'UNASSESSED', 'ai' => 'OFF'];
        $this->persist($r, $object, $s, $packet, 'E2E01_ACQUIRED');

        return back()->with('status', 'Dokumen nyata berhasil diambil dan diekstraksi; belum menjadi bukti terverifikasi.');
    }

    public function brief(Request $r, SifiMonitoringObject $object): RedirectResponse
    {
        Gate::authorize('update', $object);
        $v = $r->validate(['session_id' => ['required', 'uuid'], 'object_revision' => ['required', 'integer'],
            'quote' => ['required', 'string', 'min:15', 'max:800'], 'analyst_interpretation' => ['required', 'string', 'min:20', 'max:1800'],
            'limitations' => ['required', 'string', 'min:10', 'max:1000']]);
        if ($object->revision !== (int) $v['object_revision']) {
            $this->fail('Objek berubah.');
        }
        $s = $this->session($r, $object, $v['session_id']);
        $data = json_decode($s->results_json, true, 32, JSON_THROW_ON_ERROR);
        $p = $data['e2e01_pilot'] ?? null;
        if (! $p || isset($p['brief'])) {
            $this->fail('Dokumen tidak tersedia atau brief telah disimpan.');
        }
        $text = $p['extraction']['text'];
        $pos = mb_strpos($text, $v['quote']);
        if ($pos === false) {
            $this->fail('Kutipan harus persis berasal dari teks hasil ekstraksi, bukan ditulis bebas.');
        }
        $out = "# SIFI — Intelligence Brief (DRAFT / BELUM TERVERIFIKASI)\n\n";
        $out .= '**Pertanyaan riset:** '.$s->question."\n\n";
        $out .= '**Sumber nyata:** '.$p['source_url']."\n\n";
        $out .= '**Akuisisi:** '.$p['retrieved_at'].' | SHA-256 konten: '.$p['body_sha256']."\n\n";
        $out .= "## Kutipan sumber (belum diverifikasi)\n\n> ".str_replace("\n", ' ', $v['quote'])."\n\n";
        $out .= "## Interpretasi analis (hipotesis, bukan fakta terverifikasi)\n\n".$v['analyst_interpretation']."\n\n";
        $out .= "## Keterbatasan dan tindak lanjut\n\n".$v['limitations']."\n\n";
        $out .= "## Status dan provenance\n\nEvidence admission: NONE. Claim verified: NO. Hak konten: UNASSESSED. AI: OFF. ";
        $out .= $p['extraction']['limitations']."\n";
        $p['brief'] = $out;
        $p['brief_state'] = 'ANALYST_DRAFT_NOT_EVIDENCE';
        $p['quote'] = ['text' => $v['quote'], 'character_offset' => $pos, 'character_length' => mb_strlen($v['quote'])];
        $p['analyst_interpretation'] = $v['analyst_interpretation'];
        $p['analyst_limitations'] = $v['limitations'];
        $this->persist($r,$object,$s,$p,'E2E01_BRIEF_DRAFTED');

        return back()->with('status','Draft intelligence brief tersimpan di sesi, bukan evidence/brief resmi.');
    }
}
