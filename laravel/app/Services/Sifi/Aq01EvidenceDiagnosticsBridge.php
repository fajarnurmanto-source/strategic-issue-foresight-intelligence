<?php

namespace App\Services\Sifi;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/** Loopback-only, strictly checked advisory. Never grants evidence authority. */
final class Aq01EvidenceDiagnosticsBridge
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function diagnose(array $input): array
    {
        $base = (string) config('sifi_aq01.base_url');
        $token = (string) config('sifi_aq01.token');
        if (strlen($token) < 32 || preg_match('#^http://127\.0\.0\.1:[0-9]{2,5}$#D', $base) !== 1) {
            throw new RuntimeException('AQ01 loopback unavailable');
        }
        $payload = array_merge($input, ['request_nonce' => (string) Str::uuid()]);
        try {
            $resp = Http::withToken($token)->acceptJson()->asJson()->withoutRedirecting()
                ->connectTimeout(1)->timeout(4)->post($base.'/v1/evidence/diagnose', $payload);
        } catch (\Throwable $e) {
            throw new RuntimeException('AQ01 diagnostic unavailable', 0, $e);
        }
        if (! $resp->successful()) {
            throw new RuntimeException('AQ01 diagnostic HTTP rejected');
        }
        $out = $resp->json();
        if (! is_array($out)) {
            throw new RuntimeException('AQ01 diagnostic invalid JSON');
        }
        $fixed = [
            'schema_version' => 'sifi.evidence.diagnostics.v1',
            'mode' => 'OFFLINE_FIXTURE_ONLY', 'kind' => 'HUMAN_REVIEW_AID_ONLY',
            'status' => 'INSUFFICIENT_FOR_QUALIFICATION',
            'evidence_admission' => 'NOT_EVALUATED',
            'independence_verified' => false, 'claim_verified' => false,
            'python_issue_created' => false, 'candidate_id' => $input['candidate_id'],
            'candidate_revision' => $input['candidate_revision'],
            'request_nonce' => $payload['request_nonce'], 'drafts' => $input['drafts'],
            'draft_count' => count($input['drafts']),
        ];
        foreach ($fixed as $key => $value) {
            if (! array_key_exists($key, $out) || $out[$key] !== $value) {
                throw new RuntimeException('AQ01 diagnostic trust contract violated');
            }
        }
        $fields = array_merge(array_keys($fixed), [
            'distinct_source_count', 'possible_syndication', 'duplicate_signal_groups',
            'relationship_counts', 'signal_observations', 'verification_checks',
        ]);
        $actual = array_keys($out);
        sort($actual);
        sort($fields);
        if ($actual !== $fields || ! is_int($out['distinct_source_count']) ||
            $out['distinct_source_count'] < 1 || $out['distinct_source_count'] > count($input['drafts']) ||
            ! is_bool($out['possible_syndication'])) {
            throw new RuntimeException('AQ01 diagnostic unexpected schema');
        }
        $expectedRels = ['UNKNOWN', 'SUPPORTS', 'CONTRADICTS', 'CONTEXT'];
        if (! is_array($out['relationship_counts']) || count($out['relationship_counts']) !== 4) {
            throw new RuntimeException('AQ01 diagnostic relationship counts malformed');
        }
        $sum = 0;
        foreach ($expectedRels as $rel) {
            $expected = count(array_filter($input['drafts'], fn ($d) => $d['relationship'] === $rel));
            if (! isset($out['relationship_counts'][$rel]) || $out['relationship_counts'][$rel] !== $expected) {
                throw new RuntimeException('AQ01 diagnostic relationship count mismatch');
            }
            $sum += $out['relationship_counts'][$rel];
        }
        if ($sum !== count($input['drafts']) || ! is_array($out['signal_observations']) ||
            count($out['signal_observations']) !== count($input['drafts'])) {
            throw new RuntimeException('AQ01 diagnostic observation mismatch');
        }
        foreach ($input['drafts'] as $i => $draft) {
            $obs = $out['signal_observations'][$i] ?? null;
            if (! is_array($obs) || array_keys($obs) !== ['evidence_id', 'signal_id', 'source_id', 'signal_version', 'relationship'] ||
                $obs['evidence_id'] !== $draft['evidence_id'] || $obs['signal_id'] !== $draft['signal_id'] ||
                $obs['relationship'] !== $draft['relationship'] || ! is_string($obs['source_id']) ||
                ! preg_match('/^[A-Za-z0-9_-]{1,100}$/D', $obs['source_id']) ||
                ! is_int($obs['signal_version']) || $obs['signal_version'] < 1) {
                throw new RuntimeException('AQ01 diagnostic invalid observation');
            }
        }
        if (! is_array($out['duplicate_signal_groups']) || count($out['duplicate_signal_groups']) > 5) {
            throw new RuntimeException('AQ01 diagnostic invalid duplicate groups');
        }
        $known = array_column($input['drafts'], 'signal_id');
        $seen = [];
        foreach ($out['duplicate_signal_groups'] as $group) {
            if (! is_array($group) || ! array_is_list($group) || count($group) < 2 ||
                count($group) > count($known)) {
                throw new RuntimeException('AQ01 diagnostic duplicate group invalid');
            }
            $sorted = $group;
            sort($sorted);
            if ($group !== $sorted || count(array_unique($group)) !== count($group) ||
                array_diff($group, $known)) {
                throw new RuntimeException('AQ01 diagnostic duplicate group invalid');
            }
            foreach ($group as $sid) {
                if (in_array($sid, $seen, true)) {
                    throw new RuntimeException('AQ01 diagnostic overlap');
                }
                $seen[] = $sid;
            }
        }
        if ($out['possible_syndication'] !== (count($out['duplicate_signal_groups']) > 0)) {
            throw new RuntimeException('AQ01 diagnostic syndication mismatch');
        }
        if (! is_array($out['verification_checks']) || ! array_is_list($out['verification_checks']) ||
            count($out['verification_checks']) < 1 || count($out['verification_checks']) > 8) {
            throw new RuntimeException('AQ01 diagnostic invalid checks');
        }
        foreach ($out['verification_checks'] as $check) {
            if (! is_string($check) || mb_strlen($check) > 300 || trim($check) === '') {
                throw new RuntimeException('AQ01 diagnostic invalid check content');
            }
        }
        unset($out['request_nonce']);

        return $out;
    }
}
