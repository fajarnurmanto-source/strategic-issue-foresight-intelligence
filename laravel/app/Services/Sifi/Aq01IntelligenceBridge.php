<?php

namespace App\Services\Sifi;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/** Trusted loopback server-side adapter. No remote URLs from browser input. */
final class Aq01IntelligenceBridge
{
    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function assess(array $input): array
    {
        $base = (string) config('sifi_aq01.base_url');
        $token = (string) config('sifi_aq01.token');
        if (strlen($token) < 32 || preg_match('#^http://127\.0\.0\.1:[0-9]{2,5}$#D', $base) !== 1) {
            throw new RuntimeException('AQ01 bridge configuration unavailable');
        }
        $nonce = (string) Str::uuid();
        $payload = [
            'candidate_id' => $input['candidate_id'],
            'candidate_revision' => $input['candidate_revision'],
            'request_nonce' => $nonce,
            'issue_hypothesis' => $input['issue_hypothesis'],
            'evidence_gaps' => $input['evidence_gaps'],
            'next_checks' => $input['next_checks'],
            'signal_ids' => $input['signal_ids'],
        ];
        try {
            $response = Http::withToken($token)->acceptJson()->asJson()
                ->withoutRedirecting()->connectTimeout(1)->timeout(4)
                ->post($base . '/v1/intelligence/assess', $payload);
        } catch (\Throwable $e) {
            throw new RuntimeException('AQ01 engine unavailable', 0, $e);
        }
        if (! $response->successful()) {
            throw new RuntimeException('AQ01 engine returned a non-success response');
        }
        $data = $response->json();
        $fixed = [
            'schema_version' => 'sifi.intelligence.advisory.v1',
            'mode' => 'OFFLINE_FIXTURE_ONLY',
            'kind' => 'REVIEW_AID_ONLY',
            'status' => 'NOT_VERIFIED',
            'evidence_admission' => 'NOT_EVALUATED',
            'independence_verified' => false,
            'python_issue_created' => false,
            'candidate_id' => $input['candidate_id'],
            'candidate_revision' => $input['candidate_revision'],
            'request_nonce' => $nonce,
            'signal_ids' => $input['signal_ids'],
            'signal_count' => count($input['signal_ids']),
        ];
        if (! is_array($data)) {
            throw new RuntimeException('AQ01 response schema invalid');
        }
        foreach ($fixed as $field => $expected) {
            if (! array_key_exists($field, $data) || $data[$field] !== $expected) {
                throw new RuntimeException('AQ01 response rejected by trust contract');
            }
        }
        $expectedFields = array_merge(array_keys($fixed), [
            'distinct_source_count', 'possible_syndication', 'observations', 'verification_checks',
        ]);
        $actualFields = array_keys($data);
        sort($actualFields);
        sort($expectedFields);
        if ($actualFields !== $expectedFields) {
            throw new RuntimeException('AQ01 response contains unapproved fields');
        }
        if (! is_int($data['distinct_source_count']) || $data['distinct_source_count'] < 1 ||
            $data['distinct_source_count'] > count($input['signal_ids']) ||
            ! is_bool($data['possible_syndication'])) {
            throw new RuntimeException('AQ01 response invalid metadata');
        }
        foreach (['observations' => 5, 'verification_checks' => 8] as $field => $maxCount) {
            if (! is_array($data[$field]) || ! array_is_list($data[$field]) ||
                count($data[$field]) < 1 || count($data[$field]) > $maxCount) {
                throw new RuntimeException('AQ01 response invalid checklist');
            }
            foreach ($data[$field] as $item) {
                if (! is_string($item) || trim($item) === '' || mb_strlen($item) > 300) {
                    throw new RuntimeException('AQ01 response invalid checklist item');
                }
            }
        }
        // Drop one-time nonce before persistence. Output never includes raw titles or bodies.
        unset($data['request_nonce']);
        return $data;
    }
}
