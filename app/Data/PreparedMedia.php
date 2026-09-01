<?php

namespace App\Data;

final readonly class PreparedMedia
{
    public function __construct(
        public string $path,
        public string $mime,
        public int $bytes,
        public array $metadata = [],
    ) {}
}
