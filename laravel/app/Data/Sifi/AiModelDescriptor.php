<?php

namespace App\Data\Sifi;

final readonly class AiModelDescriptor
{
    /** @param list<string> $capabilities */
    public function __construct(
        public string $providerId,
        public string $modelId,
        public ?string $version,
        public array $capabilities,
    ) {}
}
