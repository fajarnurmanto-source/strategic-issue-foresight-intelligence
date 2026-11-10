<?php

namespace App\Http\Controllers\Sifi;

use App\Http\Controllers\Controller;
use App\Models\SifiMonitoringObject;
use App\Services\Sifi\E4PeriodDiscovery;
use App\Services\Sifi\Integration\ReviewedAntaraRss;
use App\Services\Sifi\Integration\ReviewedCrossrefResearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * E4: keyword-registered monitoring object + period -> approved one-shot sources.
 * Explicit POST opt-in only. No API call on GET; no production/evidence admission.
 */
final class E4PeriodDiscoveryController extends Controller
{
    public function index(Request $request, E4PeriodDiscovery $discovery): Response
    {
        Gate::authorize('viewAny', SifiMonitoringObject::class);
        $owner = $request->user();
        $objects = SifiMonitoringObject::query()->where('user_id', $owner->id)
            ->orderByDesc('updated_at')->limit(100)->get()
            ->map(static fn ($object) => [
                'id' => $object->id, 'name' => $object->name,
                'revision' => $object->revision, 'state' => $object->state,
                'keywords' => $object->keywords_json,
            ])->all();

        $selected = $request->query('object_id');
        $start = $request->query('start_date');
        $end = $request->query('end_date');
        $plan = null;
        if ($start !== null || $end !== null) {
            $values = $request->validate([
                'object_id' => ['required', 'uuid'],
                'start_date' => ['required', 'date_format:Y-m-d'],
                'end_date' => ['required', 'date_format:Y-m-d'],
            ]);
            $object = SifiMonitoringObject::query()
                ->where('user_id', $owner->id)->findOrFail((string) $values['object_id']);
            $plan = $discovery->plan($owner, $object, $values['start_date'], $values['end_date']);
        }

        $report = $request->session()->get('e4_result');
        if (! is_array($report) || (int) ($report['owner_user_id'] ?? 0) !== (int) $owner->id
            || ($plan !== null && ($report['object_id'] ?? null) !== $plan['object_id'])) {
            $report = null;
        } elseif (is_array($report)) {
            unset($report['owner_user_id']);
        }

        return Inertia::render('sifi/e4-period-discovery', [
            'objects' => $objects,
            'plan' => $plan,
            'report' => $report,
            'live_enabled' => config('sifi_e4.live_enabled') === true,
            'production_admission' => 'DENIED',
        ]);
    }

    public function collect(Request $request, E4PeriodDiscovery $discovery): RedirectResponse
    {
        if (array_diff(array_keys($request->all()), [
            '_token', 'object_id', 'object_revision', 'start_date',
            'end_date', 'submission_key', 'consent',
        ])) {
            throw ValidationException::withMessages(['source' => 'Parameter provider atau izin tambahan tidak boleh dikirim pengguna.']);
        }
        $values = $request->validate([
            'object_id' => ['required', 'uuid'],
            'object_revision' => ['required', 'integer', 'min:1'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d'],
            'submission_key' => ['required', 'uuid'],
            'consent' => ['accepted'],
        ]);
        $owner = $request->user();
        $object = SifiMonitoringObject::query()
            ->where('user_id', $owner->id)->findOrFail((string) $values['object_id']);
        Gate::authorize('update', $object);
        $plan = $discovery->plan($owner, $object, $values['start_date'], $values['end_date']);
        if ((int) $object->revision !== (int) $values['object_revision']) {
            throw ValidationException::withMessages(['object_revision' => 'Objek berubah. Muat ulang periode sebelum akuisisi.']);
        }
        if (! $plan['live_enabled'] || $plan['eligible_dispatch_count'] === 0) {
            throw ValidationException::withMessages([
                'source' => 'Tidak ada sumber yang diizinkan untuk akuisisi live pada SQLite validasi ini. Periksa konfigurasi Superadmin dan hak penyedia.',
            ]);
        }
        $contact = config('sifi_social.contact_email');
        if (! is_string($contact) || filter_var($contact, FILTER_VALIDATE_EMAIL) === false) {
            $contact = (string) $owner->email;
        }

        $sources = [];
        $items = [];
        foreach ($plan['sources'] as $source) {
            if (! $source['dispatchable']) {
                $sources[] = $source + ['result_state' => 'NOT_DISPATCHED', 'session_id' => null, 'result_count' => 0];
                continue;
            }
            $sessionId = null;
            $state = 'UNAVAILABLE';
            $errorCode = null;
            try {
                if ($source['id'] === 'CROSSREF') {
                    $sessionId = app(ReviewedCrossrefResearch::class)->collect($owner, $object, [
                        'question' => $plan['query'],
                        'object_revision' => (int) $object->revision,
                        'submission_key' => $values['submission_key'],
                        'contact_email' => $contact,
                        'date_window' => [
                            'start' => $plan['start_date'],
                            'end' => $plan['end_exclusive'],
                        ],
                    ]);
                } elseif ($source['id'] === 'ANTARA_RSS') {
                    $outcome = app(ReviewedAntaraRss::class)->collect(
                        $owner, $object, $contact,
                        ['start' => $plan['start_date'], 'end' => $plan['end_exclusive']],
                        $object->keywords_json
                    );
                    $sessionId = $outcome['session_id'];
                }
                if ($sessionId !== null) {
                    $state = 'RECEIPT_SAVED';
                    $items = [...$items, ...$this->entries($owner->id, $object->id,
                        $sessionId, $source, $plan['start_date'], $plan['end_date'])];
                }
            } catch (\Throwable $exception) {
                // Never expose upstream content, credentials, or exception traces.
                $reason = $exception->getMessage();
                $errorCode = preg_match('/\A[A-Z][A-Z0-9_]{2,100}\z/D', $reason)
                    ? $reason : 'PROVIDER_UNAVAILABLE';
            }
            $sources[] = $source + [
                'result_state' => $state, 'session_id' => $sessionId,
                'result_count' => count(array_filter($items,
                    static fn ($item) => $item['provider'] === $source['id'])),
                'error_code' => $errorCode,
            ];
        }

        $report = [
            'schema' => 'sifi.e4.one-shot-report.v1',
            'owner_user_id' => (int) $owner->id,
            'object_id' => $object->id,
            'object_revision' => (int) $object->revision,
            'keyword_scope_sha256' => $plan['keyword_scope_sha256'],
            'start_date' => $plan['start_date'],
            'end_date' => $plan['end_date'],
            'sources' => $sources,
            'items' => array_slice($items, 0, 10),
            'result_state' => $items ? 'PARTIAL_REAL_METADATA' : 'INSUFFICIENT_COVERAGE',
            'coverage' => 'Approved bounded source receipts only. RSS latest-only; Crossref bibliographic metadata only. Results are not exhaustive and not verified events.',
            'evidence_admission' => 'NONE',
            'production_admission' => 'DENIED',
        ];

        return redirect()->route('sifi.e4.period-discovery.index', [
            'object_id' => $object->id,
            'start_date' => $plan['start_date'],
            'end_date' => $plan['end_date'],
        ])->with('e4_result', $report);
    }

    /**
     * @param array<string,mixed> $source
     * @return list<array<string, string|null>>
     */
    private function entries(int $ownerId, string $objectId, string $sessionId,
        array $source, string $start, string $end): array
    {
        $row = DB::table('sifi_research_sessions')->where('id', $sessionId)
            ->where('user_id', $ownerId)->where('object_id', $objectId)->first();
        if ($row === null) {
            return [];
        }
        $results = json_decode($row->results_json, true, 32, JSON_THROW_ON_ERROR);
        $envelope = $results['multi_source']['envelope'] ?? null;
        if (! is_array($envelope) || ($envelope['mode'] ?? null) !== 'APPROVED_LIVE_CORPUS_METADATA'
            || ($envelope['provider_ids'] ?? null) !== [$source['id']]) {
            return [];
        }

        $items = [];
        foreach (array_slice($envelope['entries'] ?? [], 0, 10) as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $date = substr((string) ($entry['published_at'] ?? ''), 0, 10);
            if ($date < $start || $date > $end || preg_match('/\A\d{4}-\d{2}-\d{2}\z/D', $date) !== 1) {
                continue;
            }
            $url = $entry['canonical_url'] ?? null;
            if (! is_string($url) || ! str_starts_with($url, 'https://')) {
                continue;
            }
            $items[] = [
                'provider' => $source['id'],
                'source_class' => $source['class'],
                'platform' => $source['platform'],
                'title' => mb_substr((string) ($entry['title'] ?? ''), 0, 300),
                'url' => $url,
                'published_at' => $date,
                'rights_state' => (string) ($entry['rights_state'] ?? 'UNASSESSED'),
                'session_id' => $sessionId,
            ];
        }
        return $items;
    }
}
