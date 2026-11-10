<?php

namespace App\Data\Sifi;

use InvalidArgumentException;

final readonly class AiInferenceRequest
{
    /**
     * @param list<string> $inputReferences
     * @param array<string, mixed> $outputSchema
     */
    public function __construct(
        public string $task,
        public array $inputReferences,
        public array $outputSchema,
        public int $timeoutMilliseconds,
        public string $privacyClass,
        public string $correlationId,
    ) {
        if ($task === '' || $correlationId === '') {
            throw new InvalidArgumentException('AI task and correlation ID are required.');
        }

        if ($timeoutMilliseconds < 100 || $timeoutMilliseconds > 120_000) {
            throw new InvalidArgumentException('AI timeout must be between 100 and 120000 milliseconds.');
        }

        if (! in_array($privacyClass, ['PUBLIC', 'INTERNAL', 'RESTRICTED'], true)) {
            throw new InvalidArgumentException('Unsupported AI privacy class.');
        }

        if (count($inputReferences) > 50 || array_filter($inputReferences, fn ($value) => ! is_string($value) || $value === '') !== []) {
            throw new InvalidArgumentException('AI input references must contain at most 50 non-empty strings.');
        }
    }

    /** @return array<string, int|string> */
    public function auditContext(): array
    {
        return [
            'task' => $this->task,
            'privacy_class' => $this->privacyClass,
            'correlation_id' => $this->correlationId,
            'input_reference_count' => count($this->inputReferences),
            'schema_field_count' => count($this->outputSchema),
            'timeout_milliseconds' => $this->timeoutMilliseconds,
        ];
    }
}
