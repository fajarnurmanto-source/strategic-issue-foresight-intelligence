<?php

namespace App\Services\Sifi\Integration;

use App\Models\SifiConnectorProfile;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Offline, disabled-by-construction connector configuration drafts.
 * This service NEVER grants endpoint rights or invokes HTTP/AI runtimes.
 */
final class ConnectorProfileDrafts
{
    public function create(User $actor, array $input): SifiConnectorProfile
    {
        $this->authorize($actor);

        $data = Validator::make($input, [
            'provider_key' => ['required', 'string', 'max:64', 'regex:/\A[a-z][a-z0-9_-]{1,63}\z/D'],
            'label' => ['required', 'string', 'max:100'],
            'kind' => ['required', Rule::in(['AI', 'SOCIAL', 'SEARCH', 'NEWS', 'OPEN_DATA', 'LOCAL'])],
            'endpoint' => ['required', 'string', 'url', 'max:255'],
            'auth_scheme' => ['required', Rule::in(['NONE', 'BEARER', 'API_KEY', 'OAUTH2'])],
            'credential' => ['nullable', 'string', 'max:4096'],
            'rate_limit_per_minute' => ['sometimes', 'integer', 'min:0', 'max:1000'],
        ])->validate();

        $endpoint = rtrim($data['endpoint'], '/');
        $url = parse_url($endpoint);

        if (! is_array($url) || isset($url['user'], $url['pass'])
            || isset($url['user']) || isset($url['pass'])
            || isset($url['query']) || isset($url['fragment'])) {
            throw new \InvalidArgumentException('CREDENTIAL_OR_QUERY_IN_ENDPOINT');
        }

        $host = strtolower((string) ($url['host'] ?? ''));
        if ($host === '' || ($data['kind'] !== 'LOCAL'
            && (filter_var($host, FILTER_VALIDATE_IP) !== false
                || $host === 'localhost' || str_ends_with($host, '.local')))) {
            throw new \InvalidArgumentException('REMOTE_ENDPOINT_HOST_NOT_ALLOWED');
        }

        $isLoopback = ($data['kind'] === 'LOCAL')
            && ($url['scheme'] ?? '') === 'http'
            && $host === '127.0.0.1';

        if (($url['scheme'] ?? '') !== 'https' && ! $isLoopback) {
            throw new \InvalidArgumentException('ENDPOINT_SCHEME_NOT_ALLOWED');
        }

        if ($data['kind'] === 'LOCAL' && ! $isLoopback) {
            throw new \InvalidArgumentException('LOCAL_ENDPOINT_NOT_LOOPBACK');
        }

        $credential = $data['credential'] ?? null;
        if ($credential === '') { $credential = null; }

        if ($data['auth_scheme'] === 'NONE' && $credential !== null && $credential !== '') {
            throw new \InvalidArgumentException('UNEXPECTED_CREDENTIAL');
        }

        return DB::transaction(function () use ($actor, $data, $credential, $endpoint): SifiConnectorProfile {
            $profile = new SifiConnectorProfile;
            $profile->id = (string) Str::uuid();
            $profile->provider_key = $data['provider_key'];
            $profile->label = $data['label'];
            $profile->kind = $data['kind'];
            $profile->endpoint = $endpoint;
            $profile->auth_scheme = $data['auth_scheme'];
            $profile->credential_ciphertext = $credential;
            $profile->rate_limit_per_minute = (int) ($data['rate_limit_per_minute'] ?? 0);
            $profile->max_cost_micros = 0;
            $profile->enabled = false;
            $profile->billing_authorized = false;
            $profile->rights_status = 'UNASSESSED';
            $profile->connection_status = 'NOT_TESTED';
            $profile->created_by = $actor->id;
            $profile->save();

            $timestamp = now();
            DB::table('sifi_connector_audits')->insert([
                'profile_id' => $profile->id,
                'actor_id' => $actor->id,
                'event_code' => 'PROFILE_DRAFT_CREATED',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            return $profile;
        });
    }

    /** Only safe display fields. Never returns the encrypted blob or plaintext. */
    public function publicList(User $actor): array
    {
        $this->authorize($actor);

        return SifiConnectorProfile::query()->orderBy('label')->get()
            ->map(fn (SifiConnectorProfile $p): array => [
                'id' => $p->id,
                'provider_key' => $p->provider_key,
                'label' => $p->label,
                'kind' => $p->kind,
                'endpoint_host' => parse_url($p->endpoint, PHP_URL_HOST),
                'credential_saved' => $p->credentialIsStored(),
                'rate_limit_per_minute' => $p->rate_limit_per_minute,
                'max_cost_micros' => $p->max_cost_micros,
                'enabled' => $p->enabled,
                'billing_authorized' => $p->billing_authorized,
                'rights_status' => $p->rights_status,
                'connection_status' => $p->connection_status,
            ])->all();
    }

    private function authorize(User $actor): void
    {
        if (config('sifi_superadmin.enabled', false) !== true
            || ! $actor->exists || ! $actor->hasVerifiedEmail()
            || ! $actor->hasRole('superadmin', 'web')) {
            throw new AuthorizationException('Superadmin connector configuration denied.');
        }
    }
}