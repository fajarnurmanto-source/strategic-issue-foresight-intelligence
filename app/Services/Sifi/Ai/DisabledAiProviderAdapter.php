<?php

namespace App\Services\Sifi\Ai;

use App\Contracts\Sifi\AiProviderAdapter;
use App\Data\Sifi\AiInferenceRequest;
use App\Data\Sifi\AiInferenceResult;
use App\Data\Sifi\AiProviderCapabilities;

final class DisabledAiProviderAdapter implements AiProviderAdapter
{
    public function capabilities(): AiProviderCapabilities
    {
        return AiProviderCapabilities::disabled();
    }

    public function models(): array
    {
        return [];
    }

    public function infer(AiInferenceRequest $request): AiInferenceResult
    {
        return AiInferenceResult::unavailable('AI_DISABLED', [
            'mode' => 'DETERMINISTIC_ONLY',
            'external_call' => false,
            'correlation_id' => $request->correlationId,
        ]);
    }
}
