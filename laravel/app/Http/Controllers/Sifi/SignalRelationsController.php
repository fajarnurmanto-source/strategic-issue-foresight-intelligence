<?php

namespace App\Http\Controllers\Sifi;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

/** Read-only offline Python relationship triage; no Laravel DB mutation. */
final class SignalRelationsController extends Controller
{
    public function index(): Response
    {
        $base = (string) config('sifi_aq01.base_url');
        $token = (string) config('sifi_aq01.token');
        $state = 'API_UNAVAILABLE';
        $data = null;

        // Fail closed: do not forward bearer credentials to any non-loopback host.
        if (strlen($token) >= 32 && preg_match('#^http://127\.0\.0\.1:([0-9]{2,5})$#D', $base, $m)
            && (int) $m[1] > 0 && (int) $m[1] <= 65535) {
            try {
                $response = Http::withToken($token)->acceptJson()->connectTimeout(2)->timeout(8)
                    ->get($base.'/v1/signal-relations');
                if ($response->successful()) {
                    $payload = $response->json();
                    if (self::isValidProjection($payload)) {
                        $data = $payload;
                        $state = 'OFFLINE_FIXTURE_ONLY';
                    } else {
                        $state = 'INVALID_PROJECTION';
                    }
                } else {
                    $state = 'API_UNAVAILABLE';
                }
            } catch (\Throwable $e) {
                $state = 'API_UNAVAILABLE';
            }
        }

        return Inertia::render('sifi/signal-relations', [
            'apiStatus' => $state,
            'projection' => $data,
        ]);
    }

    private static function isValidProjection(mixed $p): bool
    {
        if (! is_array($p)
            || ($p['schema_version'] ?? null) !== 'sifi.signal.relations.v1'
            || ($p['mode'] ?? null) !== 'OFFLINE_FIXTURE_ONLY'
            || ($p['kind'] ?? null) !== 'SIMILARITY_TRIAGE_ONLY'
            || ! in_array($p['status'] ?? null, ['BOUNDED_COMPLETE', 'BOUNDED_PARTIAL'], true)
            || ($p['source_independence_verified'] ?? null) !== false
            || ($p['evidence_admission'] ?? null) !== 'NOT_EVALUATED'
            || ($p['claims_verified'] ?? null) !== false
            || ($p['issues_created'] ?? null) !== false
            || ($p['live_acquisition'] ?? null) !== false
            || ! isset($p['signals'], $p['relationships'], $p['exact_fingerprint_groups'], $p['human_checks'])
            || ! is_array($p['signals']) || ! is_array($p['relationships'])
            || ! is_array($p['exact_fingerprint_groups']) || ! is_array($p['human_checks'])
            || ! array_is_list($p['signals']) || ! array_is_list($p['relationships'])
            || ! array_is_list($p['exact_fingerprint_groups']) || ! array_is_list($p['human_checks'])
            || count($p['signals']) > 100 || count($p['relationships']) > 200
            || ! isset($p['analyzed_signals'], $p['total_in_projection'], $p['distinct_source_ids'])
            || ! is_int($p['analyzed_signals']) || ! is_int($p['total_in_projection'])
            || ! is_int($p['distinct_source_ids'])
            || $p['analyzed_signals'] !== count($p['signals'])
            || $p['analyzed_signals'] < 0 || $p['total_in_projection'] < $p['analyzed_signals']
            || $p['distinct_source_ids'] < 0 || $p['distinct_source_ids'] > $p['analyzed_signals']) {
            return false;
        }
        $known = [];
        foreach ($p['signals'] as $signal) {
            if (! is_array($signal) || ! is_string($signal['signal_id'] ?? null)
                || ! preg_match('/^[0-9a-f]{64}$/D', $signal['signal_id'])
                || isset($known[$signal['signal_id']])
                || ! is_string($signal['title'] ?? null) || mb_strlen($signal['title']) > 500
                || ! is_string($signal['source_id'] ?? null)
                || ! preg_match('/^[A-Za-z0-9_-]{1,100}$/D', $signal['source_id'])
                || ! is_int($signal['signal_version'] ?? null) || $signal['signal_version'] < 1) {
                return false;
            }
            $known[$signal['signal_id']] = $signal;
        }
        if (count(array_unique(array_column($p['signals'], 'source_id'))) !== $p['distinct_source_ids']
            || ($p['status'] === 'BOUNDED_COMPLETE' && $p['total_in_projection'] !== $p['analyzed_signals'])
            || ($p['status'] === 'BOUNDED_PARTIAL'
                && ($p['analyzed_signals'] !== 100 || $p['total_in_projection'] <= 100))) {
            return false;
        }
        $seenPairs = [];
        $exactPairs = [];
        foreach ($p['relationships'] as $edge) {
            if (! is_array($edge)
                || ! is_string($edge['left_signal_id'] ?? null)
                || ! is_string($edge['right_signal_id'] ?? null)
                || ! isset($known[$edge['left_signal_id']], $known[$edge['right_signal_id']])
                || ! in_array($edge['relation'] ?? null,
                    ['SAME_CONTENT_FINGERPRINT', 'SAME_TITLE_TOKENS', 'HIGH_TITLE_OVERLAP'], true)
                || ($edge['independence_verified'] ?? null) !== false
                || ($edge['corroboration_verified'] ?? null) !== false
                || ! is_bool($edge['same_source_id'] ?? null)) {
                return false;
            }
            $left = $edge['left_signal_id'];
            $right = $edge['right_signal_id'];
            $pair = [$left, $right];
            sort($pair, SORT_STRING);
            $key = implode(':', $pair);
            if (isset($seenPairs[$key])) {
                return false;
            }
            $seenPairs[$key] = true;
            if ($edge['relation'] === 'SAME_CONTENT_FINGERPRINT') {
                $exactPairs[$key] = true;
            }
            if ($left === $right
                || ($edge['left_source_id'] ?? null) !== $known[$left]['source_id']
                || ($edge['right_source_id'] ?? null) !== $known[$right]['source_id']
                || $edge['same_source_id'] !== ($known[$left]['source_id'] === $known[$right]['source_id'])) {
                return false;
            }
        }
        $groupForSignal = [];
        $groupPairs = [];
        foreach ($p['exact_fingerprint_groups'] as $groupIndex => $group) {
            if (! is_array($group) || ! array_is_list($group) || count($group) < 2) {
                return false;
            }
            foreach ($group as $id) {
                if (! is_string($id) || ! isset($known[$id]) || isset($groupForSignal[$id])) {
                    return false;
                }
                $groupForSignal[$id] = $groupIndex;
            }
            foreach ($group as $i => $left) {
                foreach (array_slice($group, $i + 1) as $right) {
                    $pair = [$left, $right];
                    sort($pair, SORT_STRING);
                    $key = implode(':', $pair);
                    if (! isset($exactPairs[$key])) {
                        return false;
                    }
                    $groupPairs[$key] = true;
                }
            }
        }
        if (count($groupPairs) !== count($exactPairs)) {
            return false;
        }
        foreach ($p['human_checks'] as $check) {
            if (! is_string($check) || mb_strlen($check) > 500) {
                return false;
            }
        }

        return true;
    }
}
