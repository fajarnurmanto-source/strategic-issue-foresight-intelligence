<?php

namespace App\Services\Sifi\Ai;

final class AiTaskCapabilityRegistry
{
    /** @var array<string, string> */
    private const TASKS = [
        'text_extraction' => 'Extract bounded fields without deciding truth or evidence status.',
        'source_classification' => 'Suggest a non-binding class for an already acquired source.',
        'comparison_assist' => 'Suggest comparison structure without factual verification.',
        'summary_draft' => 'Draft a summary that preserves uncertainty and provenance.',
    ];

    /** @return list<string> */
    public function tasks(): array
    {
        return array_keys(self::TASKS);
    }

    public function allows(string $task): bool
    {
        return array_key_exists($task, self::TASKS);
    }

    public function description(string $task): ?string
    {
        return self::TASKS[$task] ?? null;
    }
}
