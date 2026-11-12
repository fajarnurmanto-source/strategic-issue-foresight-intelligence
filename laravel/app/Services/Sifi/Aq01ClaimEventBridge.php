<?php

namespace App\Services\Sifi;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/** Strict loopback-only Python advisory; output is not an Issue Object or evidence admission. */
final class Aq01ClaimEventBridge
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function assess(array $input): array
    {
        $base = (string) config('sifi_aq01.base_url');
        $token = (string) config('sifi_aq01.token');
        if (strlen($token) < 32 || preg_match('#^http://127\.0\.0\.1:[0-9]{2,5}$#D', $base) !== 1) {
            throw new RuntimeException('loopback unavailable');
        }
        $payload = array_merge($input, ['request_nonce' => (string) Str::uuid()]);
        try {
            $response = Http::withToken($token)->asJson()->acceptJson()->withoutRedirecting()
                ->connectTimeout(1)->timeout(4)->post($base.'/v1/claim-event/assess', $payload);
        } catch (\Throwable $e) {
            throw new RuntimeException('Python unavailable', 0, $e);
        }
        if (! $response->successful() || ! is_array($out = $response->json())) {
            throw new RuntimeException('Python response rejected');
        }
        $fixed = ['schema_version' => 'sifi.claim_event.advisory.v1', 'mode' => 'OFFLINE_FIXTURE_ONLY',
            'kind' => 'STRUCTURAL_REVIEW_AID_ONLY', 'status' => 'NOT_VERIFIED',
            'claim_verified' => false, 'event_verified' => false,
            'evidence_admission' => 'NOT_EVALUATED', 'python_issue_created' => false,
            'candidate_id' => $input['candidate_id'], 'candidate_revision' => $input['candidate_revision'],
            'request_nonce' => $payload['request_nonce'], 'claim_count' => count($input['claims']),
            'event_count' => count($input['events']), 'link_count' => count($input['links'])];
        foreach ($fixed as $k => $v) {
            if (! array_key_exists($k, $out) || $out[$k] !== $v) {
                throw new RuntimeException('Python trust contract failed');
            }
        }
        $fields = array_merge(array_keys($fixed), ['distinct_source_count', 'signal_observations', 'verification_checks']);
        $actual = array_keys($out);
        sort($actual);
        sort($fields);
        if ($actual !== $fields || ! is_int($out['distinct_source_count']) || $out['distinct_source_count'] < 1 ||
            $out['distinct_source_count'] > count($input['claims']) || ! is_array($out['signal_observations']) ||
            ! array_is_list($out['signal_observations']) || ! is_array($out['verification_checks']) ||
            ! array_is_list($out['verification_checks']) || count($out['verification_checks']) < 1 ||
            count($out['verification_checks']) > 10) {
            throw new RuntimeException('Python schema mismatch');
        }
        $signalIds = array_values(array_unique(array_column($input['claims'], 'signal_id')));
        sort($signalIds);
        if (count($out['signal_observations']) !== count($signalIds)) {
            throw new RuntimeException('Python signal count mismatch');
        }
        foreach ($signalIds as $i => $sid) {
            $o = $out['signal_observations'][$i] ?? null;
            if (! is_array($o) || array_keys($o) !== ['signal_id', 'source_id', 'signal_version'] ||
                $o['signal_id'] !== $sid || ! is_string($o['source_id']) ||
                ! preg_match('/^[A-Za-z0-9_-]{1,100}$/D', $o['source_id']) ||
                ! is_int($o['signal_version']) || $o['signal_version'] < 1) {
                throw new RuntimeException('Python observation rejected');
            }
        }
        if (count(array_unique(array_column($out['signal_observations'], 'source_id'))) !== $out['distinct_source_count']) {
            throw new RuntimeException('Python source count mismatch');
        }
        foreach ($out['verification_checks'] as $check) {
            if (! is_string($check) || trim($check) === '' || mb_strlen($check) > 300) {
                throw new RuntimeException('Python check invalid');
            }
        }
        unset($out['request_nonce']);

        return $out;
    }
}
