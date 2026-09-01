<?php

namespace App\AI;

final readonly class AiCompletion
{
    public const SUCCESS = 'success';

    public const FAILED = 'failed';

    public const UNAVAILABLE = 'unavailable';

    public function __construct(
        public string $status,
        public string $provider,
        public ?string $model,
        public string $promptVersion,
        public array $payload = [],
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public ?string $error = null,
        public int $costMicros = 0,
    ) {}

    public static function success(
        array $payload,
        string $provider,
        ?string $model,
        string $promptVersion,
        int $inputTokens = 0,
        int $outputTokens = 0,
        int $costMicros = 0,
    ): self {
        return new self(self::SUCCESS, $provider, $model, $promptVersion, $payload, $inputTokens, $outputTokens, null, $costMicros);
    }

    public static function failed(
        string $provider,
        ?string $model,
        string $promptVersion,
        string $error,
    ): self {
        return new self(self::FAILED, $provider, $model, $promptVersion, error: $error);
    }

    public static function unavailable(string $error): self
    {
        return new self(self::UNAVAILABLE, 'manual_fallback', null, 'briefing-v1', error: $error);
    }

    public function succeeded(): bool
    {
        return $this->status === self::SUCCESS;
    }
}
