<?php

namespace App\Data;

final readonly class ContextIntelligenceResult
{
    public function __construct(
        public array $payload,
        public string $provider,
        public string $model,
        public string $promptVersion,
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public ?string $requestId = null,
    ) {}
}
