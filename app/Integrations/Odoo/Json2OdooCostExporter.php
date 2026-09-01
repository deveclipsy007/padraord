<?php

namespace App\Integrations\Odoo;

use App\Contracts\OdooCostExporter;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class Json2OdooCostExporter implements OdooCostExporter
{
    public function export(array $payload, string $idempotencyKey): string
    {
        $baseUrl = rtrim((string) config('odoo.base_url'), '/');
        $apiKey = (string) config('odoo.api_key');
        $model = (string) config('odoo.model');
        $field = (string) config('odoo.idempotency_field');
        $accountId = (int) config('odoo.analytic_account_id');
        if ($baseUrl === '' || $apiKey === '' || $model === '' || $field === '' || $accountId <= 0) {
            throw new RuntimeException('A integração Odoo ainda não possui todos os parâmetros obrigatórios.');
        }
        if (($payload['currency'] ?? null) !== config('odoo.currency')) {
            throw new RuntimeException('A moeda do custo não corresponde à moeda configurada no Odoo.');
        }
        $headers = ['Authorization' => 'bearer '.$apiKey, 'Content-Type' => 'application/json; charset=utf-8', 'User-Agent' => 'Padrao-RD-OS/1.0'];
        if (config('odoo.database')) {
            $headers['X-Odoo-Database'] = (string) config('odoo.database');
        }
        $search = Http::withHeaders($headers)->timeout(30)->post("{$baseUrl}/json/2/{$model}/search_read", [
            'domain' => [[$field, '=', $idempotencyKey]], 'fields' => ['id'], 'limit' => 1,
        ]);
        if (! $search->successful()) {
            throw new RuntimeException('Odoo não respondeu à verificação idempotente (HTTP '.$search->status().').');
        }
        $existing = data_get($search->json(), '0.id');
        if ($existing) {
            return (string) $existing;
        }
        $amount = ((int) $payload['amount_micros']) / 1_000_000;
        $create = Http::withHeaders($headers)->timeout(30)->post("{$baseUrl}/json/2/{$model}/create", [
            'vals_list' => [[
                'name' => $payload['name'], 'account_id' => $accountId,
                'amount' => $amount * (float) config('odoo.amount_multiplier', -1),
                'date' => $payload['date'], $field => $idempotencyKey,
            ]],
        ]);
        if (! $create->successful()) {
            throw new RuntimeException('Odoo não concluiu o lançamento (HTTP '.$create->status().').');
        }
        $id = data_get($create->json(), '0') ?? data_get($create->json(), 'id');
        if (! is_int($id) && ! is_string($id)) {
            throw new RuntimeException('Odoo não retornou o identificador do lançamento.');
        }

        return (string) $id;
    }
}
