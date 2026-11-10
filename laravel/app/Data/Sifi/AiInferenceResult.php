<?php

namespace App\Data\Sifi;

use InvalidArgumentException;

final readonly class AiInferenceResult
{
    /**
     * @param array<string, mixed>|null $structuredOutput
     * @param list<string> $warnings
     * @param array<string, scalar|null> $provenance
     */
    public function __construct(
        public string $status,
        public ?array $structuredOutput,
        public ?string $providerId,
        public ?string $modelId,
        public ?string $modelVersion,
        public int $latencyMilliseconds,
        public int $usageUnits,
        public int $estimatedCostMicros,
        public array $warnings,
        public array $provenance,
    ) {
        if (! in_array($status, ['AVAILABLE', 'UNAVAILABLE', 'ABSTAIN'], true)) {
            throw new InvalidArgumentException('Unsupported AI result status.');
        }

        if ($latencyMilliseconds < 0 || $usageUnits < 0 || $estimatedCostMicros < 0) {
            throw new InvalidArgumentException('AI metering values cannot be negative.');
        }

        if ($status !== 'AVAILABLE' && $structuredOutput !== null) {
            throw new InvalidArgumentException('Unavailable or abstaining AI results cannot carry structured output.');
        }

        if (array_filter($warnings, fn ($value) => ! is_string($value) || preg_match('/\A[A-Z][A-Z0-9_]{0,63}\z/', $value) !== 1) !== []) {
            throw new InvalidArgumentException('AI warnings must be bounded machine-readable codes.');
        }
    }

    /** @param array<string, scalar|null> $provenance */
    public static function unavailable(string $warning, array $provenance = []): self
    {
        return new self('UNAVAILABLE', null, null, null, null, 0, 0, 0, [$warning], $provenance);
    }
}
