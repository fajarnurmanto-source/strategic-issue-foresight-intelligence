<?php

namespace App\Http\Controllers\Sifi;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/** Authenticated local view of the previously rendered public-metadata snapshot. */
final class Ih03hSnapshotController extends Controller
{
    public function show(Request $request)
    {
        if (! app()->environment(['local', 'testing'])
            || ! in_array(strtolower($request->getHost()), ['localhost', '127.0.0.1', '::1'], true)
            || config('database.default') !== 'sqlite') {
            abort(404);
        }

        $user = $request->user();
        if ($user === null || ! $user->hasVerifiedEmail()) {
            abort(403);
        }

        $root = '/mnt/d/Research/SIFI/05_VALIDATION';
        $filename = config('sifi_ih03h.preview_filename');
        if (! is_string($filename) || preg_match('/\ASIFI_IH03G_FUSION_UI_\d{8}_\d{6}_[a-f0-9]{12}\.html\z/D', $filename) !== 1
            || ! is_dir($root) || is_link($root)) {
            abort(404);
        }

        $file = $root.DIRECTORY_SEPARATOR.$filename;
        if (! is_file($file) || is_link($file) || filesize($file) > 200 * 1024) {
            abort(404);
        }

        $contents = file_get_contents($file);
        if (! is_string($contents) || ! str_contains($contents, 'SIFI — Multi-Source Research Snapshot')
            || ! str_contains($contents, 'SHADOW • READ ONLY')
            || ! str_contains($contents, 'DATABASE WRITES: 0')) {
            abort(422, 'IH03H_PREVIEW_INTEGRITY_INVALID');
        }

        return response($contents, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'no-referrer',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'; object-src 'none'",
        ]);
    }
}
