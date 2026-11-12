<?php

namespace App\Http\Controllers\Sifi;

use App\Http\Controllers\Controller;
use App\Models\SifiMonitoringObject;
use App\Services\Sifi\DiscoveryCatalog;
use App\Services\Sifi\OneShotSearchTransport;
use App\Services\Sifi\ResearchRetrievalWorkspace;
use App\Services\Sifi\ResearchSessionWorkspace;
use App\Services\Sifi\SearchProviderRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** AE16 F04B: authorized, single-provider metadata search in an existing owned research session.
 * No network call unless live flag AND consumed-once authorization already exist.
 */
final class Ae16UniversalSearchController extends Controller
{
    private const PROVIDERS = ['WIKIPEDIA_ID', 'CROSSREF'];

    public function index(Request $r, SifiMonitoringObject $object): Response
    {
        Gate::authorize('view', $object);
        $history = app(ResearchSessionWorkspace::class)->history($r->user(), $object);
        $sessions = array_map(static fn ($s) => ['id' => $s['id'], 'question' => $s['question'], 'expired' => $s['expired'], 'created_at' => $s['created_at']], $history);
        $savedResults = array_values(array_map(static fn ($s) => ['session_id' => $s['id'], 'question' => $s['question'], 'packet' => $s['results']['ae16_live_search']], array_filter($history, static fn ($s) => ! $s['expired'] && isset($s['results']['ae16_live_search']))));
        $registry = app(SearchProviderRegistry::class);
        $providers = [];
        foreach (self::PROVIDERS as $id) {
            $p = $registry->get($id);
            $providers[] = ['id' => $id, 'corpus' => $p['corpus'], 'review_expires' => $p['expires_on'], 'review_current' => now()->toDateString() < $p['expires_on']];
        }

        return Inertia::render('sifi/ae16-universal-search', [
            'object' => $object->only(['id', 'name', 'revision', 'state']),
            'researchSessions' => $sessions, 'researchSessionCount' => count($sessions), 'savedResults' => $savedResults, 'providers' => $providers,
            'live_enabled' => config('sifi.real_search_live') === true,
            'note' => 'Hanya metadata, satu provider dan satu request per grant. GDELT/social belum terdaftar/diotorisasi. Tidak ada fixture fallback.',
        ]);
    }

    public function execute(Request $r, SifiMonitoringObject $object): RedirectResponse
    {
        Gate::authorize('update', $object);
        $v = $r->validate([
            'session_id' => ['required', 'uuid'],
            'object_revision' => ['required', 'integer', 'min:1'],
            'provider' => ['required', Rule::in(self::PROVIDERS)],
            'query' => ['required', 'string', 'min:2', 'max:200'],
            'limit' => ['required', 'integer', 'min:1', 'max:5'],
            'start' => ['required', 'date_format:Y-m-d'],
            'end' => ['required', 'date_format:Y-m-d', 'after:start'],
        ]);
        if ($object->state !== 'CONFIGURED' || $object->revision !== (int) $v['object_revision']) {
            throw ValidationException::withMessages(['session_id' => 'Objek berubah atau dijeda; muat ulang.']);
        }
        $session = app(ResearchRetrievalWorkspace::class)->session($r->user(), $object, $v['session_id']);
        $plan = json_decode($session->plan_json, true, 32, JSON_THROW_ON_ERROR);
        $previous = json_decode($session->results_json, true, 32, JSON_THROW_ON_ERROR);
        if (isset($previous['ae16_live_search'])) {
            throw ValidationException::withMessages(['session_id' => 'Sesi sudah memiliki hasil live; gunakan sesi baru agar tidak mengulang request.']);
        }
        $context = [
            'mode' => 'LIVE', 'query' => $v['query'], 'query_sha256' => hash('sha256', $v['query']),
            'limit' => (int) $v['limit'], 'date_window' => ['start' => $v['start'], 'end' => $v['end']],
            'binding' => [
                'owner_id' => $r->user()->id, 'object_id' => $object->id,
                'session_id' => $session->id, 'object_revision' => $object->revision,
                'plan_sha256' => hash('sha256', json_encode($plan, JSON_THROW_ON_ERROR)),
            ],
        ];
        // Fail before DNS unless live setting + exact consumed-once authority pass in transport.
        try {
            $packet = app(OneShotSearchTransport::class)->get($v['provider'], $context);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['provider' => 'Live acquisition ditolak: '.$e->getMessage().'. Tidak ada fallback sintetis.']);
        }
        $items = [];
        if (($packet['state'] ?? null) === 'OK') {
            $body = $packet['body'] ?? [];
            if ($v['provider'] === 'CROSSREF') {
                foreach (array_slice($body['message']['items'] ?? [], 0, 5) as $x) {
                    if (! is_array($x)) {
                        continue;
                    }
                    $url = $x['URL'] ?? null;
                    if (! is_string($url)) {
                        continue;
                    }
                    try {
                        $url = app(DiscoveryCatalog::class)->safeUrl($url, false);
                    } catch (\Throwable) {
                        continue;
                    }
                    $items[] = ['title' => mb_substr((string) ($x['title'][0] ?? 'Untitled'), 0, 300), 'url' => $url, 'publisher' => mb_substr((string) ($x['publisher'] ?? 'UNKNOWN'), 0, 150), 'publication_date' => 'UNKNOWN', 'platform' => 'RESEARCH', 'media_type' => 'DOCUMENT', 'sentiment' => 'UNASSESSED'];
                }
            } elseif ($v['provider'] === 'WIKIPEDIA_ID') {
                foreach (array_slice($body['pages'] ?? [], 0, 5) as $x) {
                    if (! is_array($x) || empty($x['key'])) {
                        continue;
                    }
                    $url = 'https://id.wikipedia.org/wiki/'.rawurlencode((string) $x['key']);
                    $items[] = ['title' => mb_substr((string) ($x['title'] ?? 'Untitled'), 0, 300), 'url' => $url, 'publisher' => 'Wikipedia ID', 'publication_date' => 'UNKNOWN', 'platform' => 'ENCYCLOPEDIA', 'media_type' => 'DOCUMENT', 'sentiment' => 'UNASSESSED'];
                }
            }
        }
        $saved = [
            'version' => 'ae16-f04b-one-shot-1', 'mode' => 'LIVE', 'provider' => $v['provider'],
            'query' => $v['query'], 'date_window' => $context['date_window'],
            'state' => $packet['state'] ?? 'UNAVAILABLE', 'http_status' => $packet['http_status'] ?? null,
            'response_sha256' => $packet['response_sha256'] ?? null, 'retrieved_at' => $packet['retrieved_at'] ?? null,
            'approval_scope_sha256' => $packet['approval_scope_sha256'] ?? null,
            'network_requests' => $packet['network_requests'] ?? 0, 'results' => $items,
            'rights' => 'METADATA_ONLY_ADMISSION_NONE', 'coverage' => 'PROVIDER_LIMITED_NOT_FULL_WEB',
            'evidence_admission' => 'NONE', 'claim_verification' => 'NOT_PERFORMED',
            'sentiment_filter' => 'NOT_APPLIED_UNREVIEWED', 'geography_filter' => 'NOT_VERIFIED',
        ];
        DB::transaction(function () use ($r, $object, $session, $saved) {
            $row = DB::table('sifi_research_sessions')->where('id', $session->id)->where('user_id', $r->user()->id)->where('object_id', $object->id)->lockForUpdate()->firstOrFail();
            $current = json_decode($row->results_json, true, 32, JSON_THROW_ON_ERROR);
            if (isset($current['ae16_live_search'])) {
                throw ValidationException::withMessages(['session_id' => 'Sesi telah diisi; tidak ditimpa.']);
            }
            $current['ae16_live_search'] = $saved;
            DB::table('sifi_research_sessions')->where('id', $session->id)->update(['results_json' => json_encode($current, JSON_THROW_ON_ERROR), 'updated_at' => now()]);
            DB::table('sifi_research_session_audit')->insert(['session_id' => $session->id, 'actor_user_id' => $r->user()->id, 'action' => 'AE16_EXTERNAL_METADATA_OBSERVED', 'snapshot_json' => json_encode(['provider' => $saved['provider'], 'state' => $saved['state'], 'network_requests' => $saved['network_requests'], 'response_sha256' => $saved['response_sha256'], 'result_count' => count($saved['results']), 'admission' => 'NONE'], JSON_THROW_ON_ERROR), 'created_at' => now()]);
        });

        return redirect()->route('sifi.ae16-universal-search.index',['object' => $object->id])->with('status','Satu hasil request telah direkam pada Research Session. Tidak ada evidence admission.');
    }
}
