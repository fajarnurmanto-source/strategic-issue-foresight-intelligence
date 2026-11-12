<?php

namespace App\Services\Sifi;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

/** Own-authored synthetic wire examples. All result identifiers/URLs are fictional. */
final class SearchWireFixture
{
    public static function install(string $scenario = 'api_fixture'): void
    {
        if (! app()->environment('testing') || config('sifi.real_search_fixture') !== true) {
            throw new \RuntimeException('FIXTURE_ONLY');
        }
        Http::swap(new Factory(app('events')));
        Http::preventStrayRequests();
        Http::fake(function ($request) use ($scenario) {
            if ($scenario === 'api_timeout') {
                throw new ConnectionException('Synthetic timeout');
            }
            if ($scenario === 'api_quota') {
                return Http::response(['error' => 'synthetic quota'], 429, ['Content-Type' => 'application/json', 'Retry-After' => '60']);
            }
            if ($scenario === 'api_web_2025' && $request->url() === 'https://api.tavily.com/search') {
                return Http::response(['query' => $request['query'], 'request_id' => 'SYNTHETIC-NOT-REAL', 'results' => [['title' => 'SYNTHETIC contoh kritik layanan kota, bukan kejadian nyata', 'url' => 'https://news.sources.invalid/synthetic-city-2025', 'content' => 'SYNTHETIC contoh perbandingan pemberitaan Yogyakarta; bukan klaim kejadian atau citra nyata.', 'published_date' => '2025-06-12'], ['title' => 'SYNTHETIC contoh klarifikasi konteks kota', 'url' => 'https://official.sources.invalid/synthetic-clarification-2025', 'content' => 'SYNTHETIC contoh klarifikasi, bukan pernyataan instansi nyata.', 'published_date' => '2025-07-01']]], 200, ['Content-Type' => 'application/json']);
            }
            $wiki = str_starts_with($request->url(), 'https://id.wikipedia.org/w/rest.php/v1/search/page?');
            $cross = str_starts_with($request->url(), 'https://api.crossref.org/works?');
            if (! $wiki && ! $cross) {
                throw new \RuntimeException('UNEXPECTED_FIXTURE_ENDPOINT');
            }
            if ($scenario === 'api_partial' && $cross) {
                return Http::response([], 503, ['Content-Type' => 'application/json']);
            }
            $none = $scenario === 'api_no_result';
            $query = $request->data()['q'] ?? $request->data()['query'] ?? '';
            $topic = str_contains(mb_strtolower($query), 'yogyakarta') ? 'Yogyakarta pelayanan publik' : 'kendaraan listrik';
            $limit = (int) ($request->data()['limit'] ?? $request->data()['rows'] ?? 5);
            $body = $wiki ? ['pages' => $none ? [] : array_slice([['id' => 990001, 'key' => 'SYNTHETIC_Topic', 'title' => 'SYNTHETIC '.$topic, 'excerpt' => '<span class="searchmatch">SYNTHETIC '.$topic.'</span> metadata contoh buatan sendiri, bukan fakta.'], ['id' => 990002, 'key' => 'SYNTHETIC_Context', 'title' => 'SYNTHETIC konteks '.$topic, 'excerpt' => 'SYNTHETIC konteks '.$topic.' perbandingan metadata terbatas.']], 0, $limit)] : ['status' => 'ok', 'message-type' => 'work-list', 'message' => ['items' => $none ? [] : array_slice([['DOI' => '10.99999/synthetic-one', 'title' => ['SYNTHETIC '.$topic], 'publisher' => 'SYNTHETIC Publisher', 'type' => 'journal-article', 'published' => ['date-parts' => [[2024]]]], ['DOI' => '10.99999/synthetic-two', 'title' => ['SYNTHETIC pembanding '.$topic], 'publisher' => 'SYNTHETIC Publisher Two', 'type' => 'report', 'published' => ['date-parts' => [[2025, 6, 12]]]]], 0, $limit)]];

            return Http::response($body, 200, ['Content-Type' => 'application/json']);
        });
    }
}
