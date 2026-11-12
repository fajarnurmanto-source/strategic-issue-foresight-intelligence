<?php

namespace App\Data\Sifi;

final readonly class AiProviderCapabilities
{
    /**
     * @param  list<string>  $tasks
     * @param  list<string>  $structuredOutputFormats
     */
    public function __construct(
        public bool $enabled,
        public array $tasks,
        public array $structuredOutputFormats,
        public bool $usageMetering,
        public bool $costMetering,
    ) {}

    public static function disabled(): self
    {
        return new self(false, [], [], false, false);
    }
}
