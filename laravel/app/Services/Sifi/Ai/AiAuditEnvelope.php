<?php

namespace App\Services\Sifi\Ai;

use App\Data\Sifi\AiInferenceRequest;
use App\Data\Sifi\AiInferenceResult;

final class AiAuditEnvelope
{
    /** @return array<string, mixed> */
    public function build(AiInferenceRequest $request, AiInferenceResult $result): array
    {
        // Free-form adapter metadata is never copied into an audit record.
        $safeProvenance = [
            'external_call' => ($result->provenance['external_call'] ?? null) === true,
        ];
        if (($result->provenance['mode'] ?? null) === 'DETERMINISTIC_ONLY') {
            $safeProvenance['mode'] = 'DETERMINISTIC_ONLY';
        }

        return [
            ...$request->auditContext(),
            'status' => $result->status,
            'provider_id' => $result->providerId,
            'model_id' => $result->modelId,
            'model_version' => $result->modelVersion,
            'latency_milliseconds' => $result->latencyMilliseconds,
            'usage_units' => $result->usageUnits,
            'estimated_cost_micros' => $result->estimatedCostMicros,
            'warnings' => $result->warnings,
            'provenance' => $safeProvenance,
        ];
    }
}
