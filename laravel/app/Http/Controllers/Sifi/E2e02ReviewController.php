<?php

namespace App\Http\Controllers\Sifi;

use App\Http\Controllers\Controller;
use App\Models\SifiMonitoringObject;
use App\Services\Sifi\ResearchRetrievalWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** Real-source, session-bound image capture and candidate packet; NOT evidence admission. */
final class E2e02ReviewController extends Controller
{
    private const HOSTS = ['warta.jogjakota.go.id', 'lingkunganhidup.jogjakota.go.id', 'ppid-files.jogjakota.go.id'];

    private function fail(string $why): never
    {
        throw ValidationException::withMessages(['e2e02' => $why]);
    }

    private function session(Request $request, SifiMonitoringObject $object, string $id): \stdClass
    {
        Gate::authorize('update', $object);
        if ($object->state !== 'CONFIGURED') {
            $this->fail('Objek tidak configured.');
        }

        return app(ResearchRetrievalWorkspace::class)->session($request->user(), $object, $id);
    }

    /** @return array<string,mixed> */
    private function getPilot(\stdClass $session): array
    {
        $r = json_decode($session->results_json, true, 32, JSON_THROW_ON_ERROR);
        $p = $r['e2e01_pilot'] ?? null;
        if (! is_array($p) || ($p['state'] ?? '') !== 'RETRIEVED_EXTRACTED' || ! isset($p['brief'],$p['quote']['text'])) {
            $this->fail('Kutipan/brief E2E-01 belum tersedia.');
        }

        return $p;
    }

    /** @return array{0:string,1:string} */
    private function canonical(string $url): array
    {
        if (strlen($url) > 1000 || preg_match('/[\x00-\x20\\\\]/', $url) || str_contains($url, '%')) {
            $this->fail('URL gambar tidak didukung.');
        }
        $p = parse_url($url);
        if (! is_array($p) || ($p['scheme'] ?? null) !== 'https' || ! in_array(strtolower($p['host'] ?? ''), self::HOSTS, true)
            || isset($p['port']) || isset($p['user']) || isset($p['pass']) || isset($p['fragment'])) {
            $this->fail('Host/URL gambar di luar lingkup pilot.');
        }

        return [$url, strtolower((string) ($p['host'] ?? ''))];
    }

    /** @return array{0:string,1:string,2:int,3:int} */
    private function imageFetch(string $url, string $host): array
    {
        if (! extension_loaded('curl')) {
            $this->fail('cURL belum tersedia.');
        }
        $ips = gethostbynamel($host);
        if (! is_array($ips) || ! count($ips)) {
            $this->fail('DNS tidak tersedia; tidak ada fetch.');
        }
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4|FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)) {
                $this->fail('DNS tidak aman.');
            }
        }
        $raw = '';
        $tooLarge = false;
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => false, CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 12,
            CURLOPT_MAXREDIRS => 0, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_RESOLVE => [$host.':443:'.$ips[0]],
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'SIFI-E2E02-image-review/1.0 (manual, bounded)',
            CURLOPT_HTTPHEADER => ['Accept: image/jpeg,image/png,image/webp', 'Accept-Encoding: identity'],
            CURLOPT_WRITEFUNCTION => static function ($h, string $chunk) use (&$raw, &$tooLarge): int {
                if (strlen($raw) + strlen($chunk) > 650000) {
                    $tooLarge = true;

                    return 0;
                }
                $raw .= $chunk;

                return strlen($chunk);
            }]);
        $ok = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = (string) curl_error($ch);
        curl_close($ch);
        if ($tooLarge) {
            $this->fail('Gambar lebih besar dari 650 KB; tidak disimpan.');
        }
        if ($ok === false) {
            $this->fail('Permintaan gambar gagal: '.mb_substr($err, 0, 120));
        }
        if ($code !== 200) {
            $this->fail('Gambar HTTP '.$code.'; tidak ada retry otomatis.');
        }
        $info = @getimagesizefromstring($raw);
        $mime = is_array($info) ? $info['mime'] : '';
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || $info[0] < 1 || $info[1] < 1 || $info[0] > 6000 || $info[1] > 6000) {
            $this->fail('Gambar tidak dikenali/ukuran piksel melebihi batas.');
        }

        return [$raw, $mime, (int) $info[0], (int) $info[1]];
    }

    private function mutate(Request $r, SifiMonitoringObject $object, string $id, callable $fn, string $action): void
    {
        DB::transaction(function () use ($r, $object, $id, $fn, $action) {
            $o = SifiMonitoringObject::whereKey($object->id)->lockForUpdate()->firstOrFail();
            if ($o->state !== 'CONFIGURED' || $o->revision !== $object->revision) {
                $this->fail('Objek berubah.');
            }
            $row = DB::table('sifi_research_sessions')->where('id', $id)->where('user_id', $r->user()->id)->where('object_id', $object->id)->lockForUpdate()->firstOrFail();
            if ((int) $row->object_revision !== (int) $object->revision || now()->gte($row->expires_at)) {
                $this->fail('Revisi sesi berubah/kedaluwarsa.');
            }
            $data = json_decode($row->results_json, true, 32, JSON_THROW_ON_ERROR);
            $p = $data['e2e01_pilot'] ?? null;
            if (! is_array($p) || ! isset($p['brief'],$p['quote'])) {
                $this->fail('Brief sumber tidak tersedia.');
            }
            $fn($data, $p);
            DB::table('sifi_research_sessions')->where('id', $id)->update(['results_json' => json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
            DB::table('sifi_research_session_audit')->insert(['session_id' => $id, 'actor_user_id' => $r->user()->id, 'action' => $action,
                'snapshot_json' => json_encode(['source_sha256' => $p['body_sha256'], 'image_sha256' => $data['e2e02_image']['sha256'] ?? null,
                    'evidence_admission' => 'NONE', 'claim_verified' => false, 'stage' => 'ANALYST_CANDIDATE'], JSON_THROW_ON_ERROR), 'created_at' => now()]);
        });
    }

    public function acquireImage(Request $r, SifiMonitoringObject $object): RedirectResponse
    {
        $v = $r->validate(['session_id' => ['required', 'uuid'], 'object_revision' => ['required', 'integer'],
            'image_url' => ['required', 'url', 'max:1000'], 'consent' => ['required', 'accepted']]);
        if ($object->revision !== (int) $v['object_revision']) {
            $this->fail('Revisi objek berbeda.');
        }
        $session = $this->session($r, $object, $v['session_id']);
        $p = $this->getPilot($session);
        $old = json_decode($session->results_json, true, 32, JSON_THROW_ON_ERROR);
        if (isset($old['e2e02_image'])) {
            $this->fail('Satu gambar sudah diambil untuk sesi ini.');
        }
        [$url,$host] = $this->canonical($v['image_url']);
        $allowed = array_column($p['extraction']['images'] ?? [], 'src');
        if (! in_array($url, $allowed, true)) {
            $this->fail('URL gambar bukan referensi dari snapshot artikel ini.');
        }
        [$raw,$mime,$w,$h] = $this->imageFetch($url, $host);
        $sha = hash('sha256', $raw);
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime];
        $path = 'sifi/e2e02/'.$r->user()->id.'/'.$v['session_id'].'/'.$sha.'.'.$ext;
        if (! Storage::disk('local')->put($path, $raw)) {
            $this->fail('Penyimpanan privat gagal.');
        }
        try {
            $this->mutate($r, $object, $v['session_id'], static function (&$data, $p) use ($url, $mime, $w, $h, $sha, $path, $raw) {
                if (isset($data['e2e02_image'])) {
                    throw ValidationException::withMessages(['e2e02' => 'Gambar sudah ada; tidak ditimpa.']);
                }
                $data['e2e02_image'] = ['source_url' => $url, 'mime' => $mime, 'width' => $w, 'height' => $h, 'bytes' => strlen($raw),
                    'sha256' => $sha, 'private_path' => $path, 'retrieved_at' => now()->toIso8601String(),
                    'visual_interpretation' => 'NOT_PERFORMED', 'rights' => 'UNASSESSED'];
            }, 'E2E02_IMAGE_CAPTURED');
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }

        return back()->with('status', 'Satu gambar disimpan privat untuk inspeksi manusia; belum ditafsirkan/diakui sebagai evidence.');
    }

    public function image(Request $r, SifiMonitoringObject $object, string $sessionId): Response
    {
        $s = $this->session($r, $object, $sessionId);
        $data = json_decode($s->results_json, true, 32, JSON_THROW_ON_ERROR);
        $im = $data['e2e02_image'] ?? null;
        if (! $im) {
            abort(404);
        }
        $path = $im['private_path'] ?? '';
        if (! str_starts_with($path, 'sifi/e2e02/'.$r->user()->id.'/'.$sessionId.'/') || ! Storage::disk('local')->exists($path)) {
            abort(404);
        }
        $bytes = Storage::disk('local')->get($path);
        abort_unless(hash_equals($im['sha256'], hash('sha256', $bytes)), 409, 'Image hash mismatch.');

        return response($bytes, 200, ['Content-Type' => $im['mime'], 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox", 'Content-Disposition' => 'inline; filename="sifi-reviewed-image"']);
    }

    public function packet(Request $r, SifiMonitoringObject $object): RedirectResponse
    {
        $v = $r->validate(['session_id' => ['required', 'uuid'], 'object_revision' => ['required', 'integer'],
            'visual_notes' => ['required', 'string', 'min:15', 'max:1200'],
            'claim_hypothesis' => ['required', 'string', 'min:20', 'max:1200']]);
        if ($object->revision !== (int) $v['object_revision']) {
            $this->fail('Objek berubah.');
        }
        $s = $this->session($r, $object, $v['session_id']);
        $p = $this->getPilot($s);
        $this->mutate($r, $object, $v['session_id'], static function (&$data, $p) use ($v) {
            if (isset($data['e2e02_review_packet'])) {
                throw ValidationException::withMessages(['e2e02' => 'Paket sudah dibuat.']);
            }
            $quote = $p['quote'];
            $text = $p['extraction']['text'];
            if (mb_substr($text, (int) $quote['character_offset'], (int) $quote['character_length']) !== $quote['text']) {
                throw ValidationException::withMessages(['e2e02' => 'Offset kutipan tidak cocok dengan snapshot.']);
            }
            $data['e2e02_review_packet'] = ['schema' => 'sifi-e2e02-review-packet-1', 'state' => 'CANDIDATE_REVIEW_REQUIRED',
                'source_url' => $p['source_url'], 'source_sha256' => $p['body_sha256'],
                'quote' => $quote, 'claim_hypothesis' => trim($v['claim_hypothesis']),
                'analyst_interpretation' => $p['analyst_interpretation'] ?? '',
                'visual_notes' => trim($v['visual_notes']),
                'image_sha256' => $data['e2e02_image']['sha256'] ?? null,
                'limitations' => $p['analyst_limitations'] ?? '',
                'evidence_admission' => 'NONE', 'claim_verified' => false, 'independent_review' => 'NOT_PERFORMED',
                'handoff' => 'ISSUE_CANDIDATE_AND_SIGNAL_MAPPING_REQUIRED',
                'created_at' => now()->toIso8601String()];
        }, 'E2E02_REVIEW_PACKET_DRAFTED');

        return back()->with('status','Kandidat claim/evidence tersimpan dalam sesi. Belum ditautkan ke Issue Candidate/signal atau direview.');
    }
}
