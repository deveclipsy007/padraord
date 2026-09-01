<?php

namespace App\Data;

final readonly class TranscriptionResult
{
    /** @param array<int, TranscriptionSegment> $segments */
    public function __construct(
        public string $text,
        public array $segments,
        public string $model,
        public ?string $requestId = null,
        public array $usage = [],
    ) {}
}
