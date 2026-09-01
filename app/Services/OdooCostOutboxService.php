<?php

namespace App\Services;

use App\Models\AiCostEntry;
use App\Models\CaseContextEntry;
use App\Models\OdooCostOutbox;
use Illuminate\Validation\ValidationException;

class OdooCostOutboxService
{
    public function enqueue(CaseContextEntry $entry): OdooCostOutbox
    {
        $costs = AiCostEntry::query()->where('case_context_entry_id', $entry->id)->whereIn('status', ['reported', 'reconciled'])->orderBy('id')->get();
        if ($costs->isEmpty()) {
            throw ValidationException::withMessages(['odoo' => 'Ainda não há custos concluídos para exportar.']);
        }
        $currencies = $costs->pluck('currency')->unique()->values();
        if ($currencies->count() !== 1) {
            throw ValidationException::withMessages(['odoo' => 'Custos com moedas diferentes precisam ser reconciliados antes da exportação.']);
        }
        $idempotency = hash('sha256', $entry->digest.'|'.$costs->pluck('id')->implode(','));
        $amount = $costs->sum(fn ($cost) => $cost->reconciled_amount_micros ?? $cost->reported_amount_micros);
        $opportunity = $entry->opportunity()->first();
        $payload = [
            'name' => 'IA operacional — '.($opportunity?->title ?? "Caso {$entry->opportunity_id}"),
            'opportunity_id' => $entry->opportunity_id,
            'context_entry_id' => $entry->id,
            'date' => now()->toDateString(),
            'currency' => $currencies->first(),
            'amount_micros' => (int) $amount,
            'items' => $costs->map(fn ($cost) => ['id' => $cost->id, 'operation' => $cost->operation, 'amount_micros' => $cost->reconciled_amount_micros ?? $cost->reported_amount_micros])->all(),
        ];

        return OdooCostOutbox::firstOrCreate(
            ['idempotency_key' => $idempotency],
            ['opportunity_id' => $entry->opportunity_id, 'case_context_entry_id' => $entry->id, 'payload' => $payload, 'status' => 'pending', 'next_attempt_at' => now()],
        );
    }
}
