<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Opportunity;
use App\Models\SupplierQuote;
use App\Models\SupplierQuoteComparison;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class SupplierQuoteComparisons
{
    public function create(Opportunity $opportunity, User $actor, array $input): SupplierQuoteComparison
    {
        $data = Validator::make($input, [
            'title' => ['required', 'string', 'max:180'],
            'quote_ids' => ['required', 'array', 'min:2', 'max:8'],
            'quote_ids.*' => ['required', 'integer', 'distinct', 'exists:supplier_quotes,id'],
            'scope_difference' => ['nullable', 'string', 'max:5000'],
            'decision_quote_id' => ['nullable', 'integer'],
            'justification' => ['nullable', 'string', 'max:5000'],
        ])->validate();

        return DB::transaction(function () use ($opportunity, $actor, $data): SupplierQuoteComparison {
            $quoteIds = array_map('intval', $data['quote_ids']);
            $quotesById = SupplierQuote::query()->with('supplier')->whereIn('id', $quoteIds)->lockForUpdate()->get()->keyBy('id');
            if ($quotesById->count() !== count($quoteIds) || $quotesById->contains(fn (SupplierQuote $quote): bool => $quote->opportunity_id !== $opportunity->id)) {
                throw ValidationException::withMessages(['quote_ids' => 'Selecione somente cotações existentes do mesmo caso.']);
            }
            $quotes = collect($quoteIds)->map(fn (int $id): SupplierQuote => $quotesById->get($id));
            $revisedIds = SupplierQuote::query()->whereIn('supersedes_id', $quoteIds)->lockForUpdate()->pluck('supersedes_id')->all();
            $snapshots = $quotes->map(fn (SupplierQuote $quote): array => $this->snapshot($quote, ! in_array($quote->id, $revisedIds, true)));

            if ($this->hasScopeDifference($snapshots) && blank($data['scope_difference'] ?? null)) {
                throw ValidationException::withMessages(['scope_difference' => 'Explique a diferença de escopo antes de comparar alternativas não equivalentes.']);
            }

            $decisionQuoteId = isset($data['decision_quote_id']) ? (int) $data['decision_quote_id'] : null;
            if ($decisionQuoteId !== null) {
                $decision = $snapshots->firstWhere('quote_id', $decisionQuoteId);
                if (! $decision) {
                    throw ValidationException::withMessages(['decision_quote_id' => 'A decisão precisa apontar para uma cotação da comparação.']);
                }
                if (! $decision['can_be_decided']) {
                    throw ValidationException::withMessages(['decision_quote_id' => 'A decisão exige uma cotação vigente, completa e na revisão atual.']);
                }
                if (blank($data['justification'] ?? null)) {
                    throw ValidationException::withMessages(['justification' => 'Registre o motivo da decisão para preservar o histórico.']);
                }
            }

            $comparison = SupplierQuoteComparison::create([
                'opportunity_id' => $opportunity->id,
                'created_by' => $actor->id,
                'decision_quote_id' => $decisionQuoteId,
                'title' => $data['title'],
                'scope_difference' => $data['scope_difference'] ?? null,
                'justification' => $data['justification'] ?? null,
                'status' => $decisionQuoteId === null ? 'draft' : 'decided',
                'decided_at' => $decisionQuoteId === null ? null : now(),
            ]);

            $comparison->items()->createMany($snapshots->map(fn (array $snapshot): array => [
                'source_quote_id' => $snapshot['quote_id'],
                'normalized_total_cents' => $snapshot['normalized_total_cents'],
                'snapshot' => $snapshot,
            ])->all());
            AuditLog::create([
                'user_id' => $actor->id,
                'action' => 'supplier.quote_comparison_created',
                'subject_type' => SupplierQuoteComparison::class,
                'subject_id' => $comparison->id,
                'metadata' => [
                    'opportunity_id' => $opportunity->id,
                    'quote_ids' => $quoteIds,
                    'decision_quote_id' => $decisionQuoteId,
                    'status' => $comparison->status,
                ],
            ]);

            return $comparison;
        });
    }

    public function export(SupplierQuoteComparison $comparison): array
    {
        $comparison->loadMissing('items');
        $items = $comparison->items->sortBy('id')->map(fn ($item): array => $item->snapshot + [
            'normalized_total_cents' => $item->normalized_total_cents,
        ])->values()->all();

        return [
            'comparison' => [
                'id' => $comparison->id,
                'title' => $comparison->title,
                'status' => $comparison->status,
                'created_at' => $comparison->created_at?->toIso8601String(),
                'decided_at' => $comparison->decided_at?->toIso8601String(),
            ],
            'scope_difference' => $comparison->scope_difference,
            'decision' => $comparison->decision_quote_id === null ? null : [
                'quote_id' => $comparison->decision_quote_id,
                'justification' => $comparison->justification,
            ],
            'items' => $items,
        ];
    }

    /** @return array<string, mixed> */
    private function snapshot(SupplierQuote $quote, bool $isCurrentRevision): array
    {
        $normalizedTotal = $this->normalizedTotalCents($quote);
        $isValid = $quote->valid_until->gte(today());
        $isComplete = filled($quote->price_basis) && $quote->quantity !== null && filled($quote->unit) && $normalizedTotal !== null;

        return [
            'quote_id' => $quote->id,
            'supplier_id' => $quote->supplier_id,
            'supplier_name' => $quote->supplier->name,
            'service' => $quote->service,
            'price_basis' => $quote->price_basis,
            'quantity' => $quote->quantity,
            'unit' => $quote->unit,
            'unit_cost_cents' => $quote->unit_cost_cents,
            'normalized_total_cents' => $normalizedTotal,
            'valid_until' => $quote->valid_until->toDateString(),
            'is_valid' => $isValid,
            'is_current_revision' => $isCurrentRevision,
            'can_be_decided' => $isValid && $isCurrentRevision && $isComplete,
            'conditions' => $quote->conditions,
            'evidence' => $quote->evidence,
            'supersedes_id' => $quote->supersedes_id,
        ];
    }

    private function normalizedTotalCents(SupplierQuote $quote): ?int
    {
        if (! in_array($quote->price_basis, ['unit', 'total'], true) || $quote->quantity === null) {
            return null;
        }

        if ($quote->price_basis === 'total') {
            return $quote->unit_cost_cents;
        }

        return Money::ratio($quote->unit_cost_cents, Money::decimal((string) $quote->quantity, 'quantity'), 100);
    }

    /** @param Collection<int, array<string, mixed>> $snapshots */
    private function hasScopeDifference(Collection $snapshots): bool
    {
        return $snapshots->map(fn (array $snapshot): string => mb_strtolower((string) preg_replace('/\s+/', ' ', trim((string) $snapshot['service']))))
            ->unique()
            ->count() > 1;
    }
}
