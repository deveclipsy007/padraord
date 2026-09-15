<?php

namespace App\Contracts;

interface CommercialChannel
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{channel: string, event: string, payload: array<string, mixed>}
     */
    public function prepare(string $event, array $payload): array;
}
