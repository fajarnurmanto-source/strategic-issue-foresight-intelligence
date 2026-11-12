<?php

namespace App\Http\Controllers\Sifi;

use App\Http\Controllers\Controller;
use App\Models\SifiMonitoringObject;
use App\Services\Sifi\Integration\AntaraLocalPermission;
use App\Services\Sifi\Integration\BlueskyLocalPermission;
use App\Services\Sifi\Integration\ConnectorObservationProjector;
use App\Services\Sifi\Integration\CrossrefLocalPermission;
use App\Services\Sifi\Integration\ReviewedAntaraRss;
use App\Services\Sifi\Integration\ReviewedBlueskySearch;
use App\Services\Sifi\Integration\ReviewedCrossrefResearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Browser boundary for reviewed local metadata only. Never an admission gateway. */
final class Ih03jOwnerCollectionController extends Controller
{
    public function status(Request $request, SifiMonitoringObject $object): JsonResponse
    {
        Gate::authorize('view', $object);

        return response()->json([
            'enabled' => $this->localSwitch(),
            'crossref_allowed' => $this->eligible('CROSSREF'),
            'antara_allowed' => $this->eligible('ANTARA_RSS'),
            'bluesky_allowed' => $this->eligible('BLUESKY_PUBLIC_SEARCH'),
            'social_autowatch_configured' => config('sifi_social.auto_watch_enabled') === true
                && (int) config('sifi_social.auto_watch_owner_id') === (int) $request->user()->id
                && $this->eligible('BLUESKY_PUBLIC_SEARCH')
                && app(ConnectorObservationProjector::class)->eligible(),
            'social_contact_configured' => is_string(config('sifi_social.contact_email'))
                && filter_var(config('sifi_social.contact_email'), FILTER_VALIDATE_EMAIL) !== false,
            'object_ready' => $object->state === 'CONFIGURED',
            'production_admission' => 'DENIED',
            'evidence_admission' => 'NONE',
        ]);
    }

    public function collect(Request $request, SifiMonitoringObject $object): RedirectResponse
    {
        Gate::authorize('update', $object);
        if (array_diff(array_keys($request->all()), [
            '_token', 'provider', 'contact_email', 'question', 'object_revision', 'submission_key',
        ])) {
            throw ValidationException::withMessages([
                'real_source' => 'Parameter tambahan tidak diizinkan.',
            ]);
        }
        $values = $request->validate([
            'provider' => ['required', Rule::in(['CROSSREF', 'ANTARA_RSS', 'BLUESKY_PUBLIC_SEARCH'])],
            'contact_email' => ['nullable', 'email', 'max:254'],
            'question' => ['nullable', 'string', 'max:600'],
            'object_revision' => ['required', 'integer', 'min:1'],
            'submission_key' => ['required', 'uuid'],
        ]);
        if ((int) $object->user_id !== (int) $request->user()->id
            || $object->state !== 'CONFIGURED'
            || (int) $object->revision !== (int) $values['object_revision']) {
            throw ValidationException::withMessages([
                'real_source' => 'Objek berubah, dijeda, atau bukan milik akun ini.',
            ]);
        }
        $provider = $values['provider'];
        if ($provider !== 'BLUESKY_PUBLIC_SEARCH' && empty($values['contact_email'])) {
            throw ValidationException::withMessages(['contact_email' => 'Email kontak wajib untuk sumber ini.']);
        }
        if (! $this->eligible($provider)) {
            throw ValidationException::withMessages([
                'real_source' => 'Konektor terkunci: gunakan konfigurasi lokal dan database SQLite terisolasi yang diizinkan.',
            ]);
        }

        try {
            if ($provider === 'CROSSREF') {
                if (! isset($values['question']) || mb_strlen(trim($values['question'])) < 8) {
                    throw ValidationException::withMessages([
                        'question' => 'Pertanyaan Crossref minimal 8 karakter.',
                    ]);
                }
                $id = app(ReviewedCrossrefResearch::class)->collect($request->user(), $object, [
                    'question' => $values['question'],
                    'object_revision' => (int) $values['object_revision'],
                    'submission_key' => $values['submission_key'],
                    'contact_email' => $values['contact_email'],
                ]);
            } elseif ($provider === 'ANTARA_RSS') {
                $outcome = app(ReviewedAntaraRss::class)->collect(
                    $request->user(), $object, $values['contact_email']
                );
                $id = $outcome['session_id'];
            } else {
                $configuredContact = config('sifi_social.contact_email');
                $contact = is_string($configuredContact)
                    && filter_var($configuredContact, FILTER_VALIDATE_EMAIL) !== false
                        ? $configuredContact : (string) ($values['contact_email'] ?? '');
                $outcome = app(ReviewedBlueskySearch::class)->collect(
                    $request->user(), $object, (string) ($values['question'] ?? ''), $contact
                );
                $id = $outcome['session_id'];
            }
        } catch (\RuntimeException|\InvalidArgumentException $exception) {
            // Only stable internal error codes may be returned to the browser.
            $code = $exception->getMessage();
            if (! preg_match('/\A[A-Z][A-Z0-9_]{2,100}\z/D', $code)) {
                $code = 'SOURCE_UNAVAILABLE';
            }
            throw ValidationException::withMessages([
                'real_source' => 'Permintaan metadata ditahan ('.$code.'). Tidak ada retry otomatis.',
            ]);
        }

        return redirect()->route('sifi.multi-research.index', [
            'object' => $object->id, 'research' => $id,
        ]);
    }

    private function localSwitch(): bool
    {
        return config('sifi_ih03j.owner_collection_enabled') === true
            && app()->environment(['local', 'testing']);
    }

    private function eligible(string $provider): bool
    {
        if ($provider === 'BLUESKY_PUBLIC_SEARCH') {
            try {
                app(BlueskyLocalPermission::class)->assertAllowed();

                return true;
            } catch (\RuntimeException $exception) {
                return false;
            }
        }
        if (! $this->localSwitch()) {
            return false;
        }
        if ($provider === 'CROSSREF') {
            return app(CrossrefLocalPermission::class)->eligible();
        }
        if ($provider === 'ANTARA_RSS') {
            try {
                app(AntaraLocalPermission::class)->assertAllowed();

                return true;
            } catch (\RuntimeException $exception) {
                return false;
            }
        }

        return false;
    }
}
