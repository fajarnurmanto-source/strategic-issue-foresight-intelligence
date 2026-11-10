<?php

namespace App\Services\Sifi;

use Illuminate\Support\Facades\{DB,Http};

/**
 * Strictly read-only bridge to the AQ01 offline fixture projection.
 * Never reads the Python SQLite file, fetches public URLs or admits evidence.
 */
final class Aq01SignalProjection
{
    public function find(string $signalId, ?int $ownerUserId = null): ?array
    {
        if (preg_match('/^[0-9a-f]{64}$/D', $signalId) !== 1) {
            return null;
        }

        // Real-source signals are owner-scoped and NOT fixture projections or admitted evidence.
        if ($ownerUserId !== null) {
            $real = DB::table('sifi_real_source_signals')->where('id',$signalId)->where('owner_user_id',$ownerUserId)->first();
            if ($real !== null) {
                return [
                    'signal_id'=>$signalId, 'title'=>'Sumber nyata: '.mb_substr($real->source_url,0,195),
                    'source_id'=>'E2E04_REAL_SOURCE', 'publisher'=>parse_url($real->source_url,PHP_URL_HOST),
                    'published_at'=>null, 'evidence_admission'=>'NOT_EVALUATED',
                    'provenance_mode'=>'REAL_SOURCE_SNAPSHOT', 'independence_verified'=>false,
                ];
            }
        }
        $base = (string) config('sifi_aq01.base_url');
        $token = (string) config('sifi_aq01.token');
        if (strlen($token) < 32 || preg_match('#^http://127\.0\.0\.1:[0-9]{2,5}$#D', $base) !== 1) {
            return null;
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->connectTimeout(1)
                ->timeout(3)
                ->get($base . '/v1/signals/' . $signalId . '/history');
            if (! $response->successful() || $response->json('mode') !== 'OFFLINE_FIXTURE_ONLY') {
                return null;
            }
            $versions = $response->json('versions');
            if (! is_array($versions) || count($versions) < 1 || count($versions) > 1000) {
                return null;
            }
            $latest = $versions[count($versions) - 1];
            if (! is_array($latest)
                || ($latest['signal_id'] ?? null) !== $signalId
                || ($latest['provenance_mode'] ?? null) !== 'OFFLINE_FIXTURE'
                || ($latest['evidence_admission'] ?? null) !== 'NOT_EVALUATED'
                || ($latest['evidence_ids'] ?? null) !== []
                || ($latest['issue_ids'] ?? null) !== []
                || ! is_string($latest['title'] ?? null)
                || trim($latest['title']) === '') {
                return null;
            }
            // Projection for UI only: no raw body, secrets or arbitrary fields.
            return [
                'signal_id' => $signalId,
                'title' => mb_substr($latest['title'], 0, 240),
                'source_id' => is_string($latest['source_id'] ?? null) ? $latest['source_id'] : 'UNKNOWN',
                'publisher' => is_string($latest['publisher_or_owner'] ?? null) ? $latest['publisher_or_owner'] : null,
                'published_at' => $latest['published_at'] ?? null,
                'evidence_admission' => 'NOT_EVALUATED',
                'provenance_mode' => 'OFFLINE_FIXTURE',
                'independence_verified' => false,
            ];
        } catch (\Throwable $exception) {
            // Fail closed for new references; existing drafts remain locally readable.
            return null;
        }
    }
}
