<?php

namespace App\Services\Sifi\Ai;

use App\Contracts\Sifi\AiProviderAdapter;
use App\Data\Sifi\AiInferenceRequest;
use App\Data\Sifi\AiInferenceResult;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

final class SecureAiRuntime
{
    public function __construct(private AiProviderAdapter $adapter, private AiTaskCapabilityRegistry $tasks) {}

    /**
     * Selection must come from server-owned session/config, never an arbitrary request owner.
     *
     * @param  array<string, mixed>  $selection
     */
    public function invoke(User $actor, int $ownerId, array $selection, AiInferenceRequest $request): AiInferenceResult
    {
        if (! $actor->exists || ! $actor->hasVerifiedEmail() || (int) $actor->id !== $ownerId) {
            throw new AuthorizationException('AI invocation denied.');
        }
        $fail = fn (string $code) => $this->record($request, AiInferenceResult::unavailable($code));
        if (($selection['enabled'] ?? false) !== true) {
            return $fail('AI_DISABLED');
        }
        if (! $this->tasks->allows($request->task)) {
            return $fail('UNSUPPORTED_TASK');
        }
        if (! $this->adapter->capabilities()->enabled) {
            return $fail('PROVIDER_DISABLED');
        }
        if (! in_array($request->task, $this->adapter->capabilities()->tasks, true)) {
            return $fail('UNSUPPORTED_CAPABILITY');
        }
        $model = null;
        foreach ($this->adapter->models() as $candidate) {
            if ($candidate->providerId === ($selection['provider'] ?? null) && $candidate->modelId === ($selection['model'] ?? null)) {
                $model = $candidate;
            }
        }
        if (! $model || ! in_array($request->task, $model->capabilities, true)) {
            return $fail('UNSUPPORTED_MODEL');
        }
        // Only the implemented schema subset is accepted; unknown constraints fail closed.
        $schema = $request->outputSchema;
        if (Validator::make($schema, [
            'type' => ['required', 'in:object'],
            'properties' => ['required', 'array', 'max:30'],
            'required' => ['present', 'array', 'max:30'],
            'required.*' => ['required', 'string', 'max:100'],
            'additionalProperties' => ['required', 'boolean'],
        ])->fails()) {
            return $fail('UNSUPPORTED_SCHEMA');
        }
        if (($schema['type'] ?? null) !== 'object' || array_diff(array_keys($schema), ['type', 'properties', 'required', 'additionalProperties']) || ($schema['additionalProperties'] ?? null) !== false || ! is_array($schema['properties'] ?? null) || ! is_array($schema['required'] ?? null)) {
            return $fail('UNSUPPORTED_SCHEMA');
        }
        foreach ($schema['properties'] as $rule) {
            if (! is_array($rule) || array_keys($rule) !== ['type'] || ! in_array($rule['type'], ['string', 'integer', 'boolean'], true)) {
                return $fail('UNSUPPORTED_SCHEMA');
            }
        }
        if (array_diff($schema['required'], array_keys($schema['properties']))) {
            return $fail('UNSUPPORTED_SCHEMA');
        }
        $key = 'sifi-ai:'.$ownerId;
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return $fail('RATE_LIMITED');
        }
        RateLimiter::hit($key, 60);
        $started = hrtime(true);
        for ($attempt = 0; ; $attempt++) {
            if ((hrtime(true) - $started) / 1_000_000 >= $request->timeoutMilliseconds) {
                return $fail('CONNECTION_TIMEOUT');
            }
            try {
                $result = $this->adapter->infer($request);
                break;
            } catch (ConnectionException) {
                if ($attempt === 1) {
                    return $fail('CONNECTION_TIMEOUT');
                }
            } catch (\Throwable) {
                return $fail('PROVIDER_FAILURE');
            }
        }
        if ((hrtime(true) - $started) / 1_000_000 >= $request->timeoutMilliseconds) {
            return $fail('CONNECTION_TIMEOUT');
        }
        if ($result->status !== 'AVAILABLE') {
            $safe = ['QUOTA_EXHAUSTED', 'BILLING_REQUIRED', 'FREE_ELIGIBILITY_UNKNOWN', 'PAID_MODEL_REJECTED', 'MODEL_NOT_ALLOWED', 'PUBLIC_SYNTHETIC_ONLY', 'GRANT_EXHAUSTED', 'MISSING_CREDENTIAL', 'PROVIDER_UNAVAILABLE', 'PROVIDER_REQUEST_FAILED', 'INVALID_PROVIDER_RESPONSE', 'MALFORMED_OUTPUT'];
            $code = $result->warnings[0] ?? '';

            return $fail(in_array($code, $safe, true) ? $code : 'PROVIDER_UNAVAILABLE');
        }
        if ($result->providerId !== $model->providerId || $result->modelId !== $model->modelId || $result->modelVersion !== $model->version) {
            return $fail('MODEL_IDENTITY_MISMATCH');
        }
        $output = $result->structuredOutput;
        if (! is_array($output) || array_diff(array_keys($output), array_keys($schema['properties'])) || array_diff($schema['required'], array_keys($output))) {
            return $fail('MALFORMED_OUTPUT');
        }
        foreach ($output as $field => $value) {
            $valid = match ($schema['properties'][$field]['type']) {
                'string' => is_string($value) && strlen($value) <= 10000,
                'integer' => is_int($value),
                'boolean' => is_bool($value),
            };
            if (! $valid) {
                return $fail('MALFORMED_OUTPUT');
            }
        }

        return $this->record($request, $result);
    }

    private function record(AiInferenceRequest $request, AiInferenceResult $result): AiInferenceResult
    {
        // Log bounded machine fields only; no prompts, arbitrary IDs, model text or secrets.
        Log::info('sifi_ai_invocation', ['status' => $result->status, 'task' => $this->tasks->allows($request->task) ? $request->task : 'UNSUPPORTED', 'result_code' => $result->status === 'AVAILABLE' ? 'STRUCTURED_OUTPUT' : ($result->warnings[0] ?? 'UNAVAILABLE')]);

        return $result;
    }
}
