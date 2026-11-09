<?php

namespace App\Contracts\Sifi;

use App\Data\Sifi\AiInferenceRequest;
use App\Data\Sifi\AiInferenceResult;
use App\Data\Sifi\AiProviderCapabilities;
use App\Data\Sifi\AiModelDescriptor;

interface AiProviderAdapter
{
    public function capabilities(): AiProviderCapabilities;

    /** @return list<AiModelDescriptor> */
    public function models(): array;

    public function infer(AiInferenceRequest $request): AiInferenceResult;
}
