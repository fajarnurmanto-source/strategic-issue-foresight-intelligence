<?php

namespace App\Http\Controllers\Sifi;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

/** AQ01 POC: server-to-server, read-only, loopback bridge. No live ingestion. */
final class SignalInboxController extends Controller
{
    public function index(Request $request): Response
    {
        $source = $request->query('source_id');
        $source = is_string($source) && preg_match('/^[A-Za-z0-9_-]{1,100}$/D', $source) ? $source : null;
        $rawPage = $request->query('page', '1');
        $page = is_string($rawPage) && preg_match('/^[1-9][0-9]{0,3}$/D', $rawPage)
            ? min((int) $rawPage, 1000) : 1;
        $selectedId = $request->query('signal');
        $selectedId = is_string($selectedId) && preg_match('/^[0-9a-f]{64}$/D', $selectedId)
            ? $selectedId : null;
        $limit = 25;
        $sources = [];
        $signals = [];
        $syndication = [];
        $signalCount = 0;
        $selectedHistory = [];
        $status = 'API_OFFLINE';
        $base = (string) config('sifi_aq01.base_url');
        $token = (string) config('sifi_aq01.token');

        // No user-supplied base URL, redirects, or browser-visible credentials.
        if (strlen($token) >= 32 && preg_match('#^http://127\.0\.0\.1:[0-9]{2,5}$#D', $base)) {
            try {
                $client = Http::withToken($token)->acceptJson()->connectTimeout(2)->timeout(5);
                $srcResponse = $client->get($base . '/v1/sources');
                $sigResponse = $client->get($base . '/v1/signals', array_filter([
                    'limit' => $limit,
                    'offset' => ($page - 1) * $limit,
                    'source_id' => $source,
                ], static fn ($v) => $v !== null));
                $synResponse = $client->get($base . '/v1/syndication');
                if ($srcResponse->successful() && $sigResponse->successful() && $synResponse->successful()
                    && $srcResponse->json('mode') === 'OFFLINE_FIXTURE_ONLY'
                    && $sigResponse->json('mode') === 'OFFLINE_FIXTURE_ONLY'
                    && $synResponse->json('mode') === 'OFFLINE_FIXTURE_ONLY') {
                    $sources = $srcResponse->json('sources') ?: [];
                    $signals = $sigResponse->json('signals') ?: [];
                    $signalCount = (int) ($sigResponse->json('count') ?: 0);
                    $syndication = $synResponse->json('possible_syndication') ?: [];
                    $status = 'OFFLINE_FIXTURE_ONLY';
                    // Only permit history lookup for an item in the current page.
                    if ($selectedId !== null && collect($signals)->contains('signal_id', $selectedId)) {
                        $detailResponse = $client->get($base . '/v1/signals/' . $selectedId . '/history');
                        if ($detailResponse->successful() && $detailResponse->json('mode') === 'OFFLINE_FIXTURE_ONLY') {
                            $selectedHistory = $detailResponse->json('versions') ?: [];
                        }
                    } else {
                        $selectedId = null;
                    }
                } else {
                    $status = 'API_ERROR';
                }
            } catch (\Throwable $exception) {
                // Avoid leaking backend exception/token details into UI.
                $status = 'API_OFFLINE';
            }
        }

        return Inertia::render('sifi/signal-inbox', [
            'apiStatus' => $status,
            'sources' => $sources,
            'signals' => $signals,
            'signalCount' => $signalCount,
            'sourceFilter' => $source,
            'page' => $page,
            'pageSize' => $limit,
            'selectedSignalId' => $selectedId,
            'selectedHistory' => $selectedHistory,
            'syndicationGroups' => $syndication,
        ]);
    }
}
