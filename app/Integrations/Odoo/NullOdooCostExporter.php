<?php

namespace App\Integrations\Odoo;

use App\Contracts\OdooCostExporter;
use RuntimeException;

class NullOdooCostExporter implements OdooCostExporter
{
    public function export(array $payload, string $idempotencyKey): string
    {
        throw new RuntimeException('A exportação ao Odoo está desabilitada. O custo continua preservado localmente.');
    }
}
