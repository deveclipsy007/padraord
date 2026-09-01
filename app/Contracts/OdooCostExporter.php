<?php

namespace App\Contracts;

interface OdooCostExporter
{
    public function export(array $payload, string $idempotencyKey): string;
}
