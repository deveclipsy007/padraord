<?php

namespace App\Data;

final readonly class TranscriptionSegment
{
    public function __construct(
        public string $providerId,
        public string $speaker,
        public int $startMs,
        public int $endMs,
        public string $text,
    ) {}
}
