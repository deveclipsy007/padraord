<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Budget;
use App\Models\Opportunity;
use App\Models\SupplierQuote;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class BudgetRevisionService
{
    public function mutate(Opportunity $case, User $actor, int $revision, callable $callback): Budget
    {
        return DB::transaction(function () use ($case, $actor, $revision, $callback) {
            Opportunity::whereKey($case->id)->lockForUpdate()->firstOrFail();
            $budget = $case->budgets()->latest('version')->lockForUpdate()->first();
            if ($budget && $budget->status === 'approved') {
                throw ValidationException::withMessages(['budget' => 'Esta versão é imutável. Crie uma nova versão para editar.']);
            }
            if (($budget?->revision ?? 0) !== $revision) {
                throw ValidationException::withMessages(['revision' => 'Outra pessoa alterou este orçamento. Atualize a página antes de reaplicar sua edição.']);
            }
            $budget ??= $case->budgets()->create(['version' => 1, 'status' => 'draft', 'calculation_mode' => 'explicit_demo']);
            $callback($budget);
            $budget->update(['revision' => $revision + 1, 'status' => 'draft', 'reviewed_at' => null, 'snapshot' => null]);
            $this->audit($actor, $budget, 'budget.changed');

            return $budget;
        });
    }

    public function review(Opportunity $case, User $actor, int $revision, bool $final): Budget
    {
        return DB::transaction(function () use ($case, $actor, $revision, $final) {
            $budget = $case->budgets()->latest('version')->lockForUpdate()->first();
            if (! $budget || $budget->revision !== $revision || $budget->status === 'approved') {
                throw ValidationException::withMessages(['revision' => 'Versão inexistente, já aprovada ou alterada. Atualize antes de revisar.']);
            }
            if ($final && (! $actor->can_approve_commercial || ! config('commercial.rules_approved') || blank(config('commercial.rules_evidence')))) {
                throw ValidationException::withMessages(['approval' => 'Aprovação real bloqueada: confirme as regras comerciais e a autoridade do aprovador. Use a revisão de demonstração.']);
            }
            $items = $budget->items()->get();
            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['budget' => 'Adicione itens antes de revisar.']);
            }
            foreach ($items as $item) {
                if ($item->unit_cost_cents <= 0 || ($item->quote_valid_until && $item->quote_valid_until->lt(today()))) {
                    throw ValidationException::withMessages(['budget' => 'Corrija os custos ausentes ou as cotações vencidas antes de revisar.']);
                }
                if ($final && ! $item->supplier_quote_id) {
                    throw ValidationException::withMessages(['budget' => 'A revisão final exige cotação com fornecedor, validade e evidência em cada item.']);
                }
                if ($item->supplier_quote_id) {
                    $quote = SupplierQuote::findOrFail($item->supplier_quote_id);
                    if ($quote->opportunity_id !== $case->id || $quote->valid_until->lt(today()) || $quote->unit_cost_cents !== $item->unit_cost_cents) {
                        throw ValidationException::withMessages(['budget' => 'Cotação vencida ou incompatível. Selecione uma nova cotação.']);
                    }
                }
            }
            $snapshot = ['version' => $budget->version, 'purpose' => $budget->purpose, 'calculationMode' => $budget->calculation_mode, 'rulesEvidence' => $final ? config('commercial.rules_evidence') : null, 'demo' => ! $final, 'items' => $items->map(fn ($item) => $item->toArray() + ['calculation' => Money::breakdown($item)])->all(), 'totalCents' => $items->sum(fn ($item) => $item->sell_total_cents)];
            $budget->update(['status' => $final ? 'approved' : 'reviewed_demo', 'revision' => $revision + 1, 'snapshot' => $snapshot, 'reviewed_at' => now(), 'approved_at' => $final ? now() : null, 'approved_by' => $final ? $actor->id : null]);
            $this->audit($actor, $budget, $final ? 'budget.approved' : 'budget.demo_reviewed');

            return $budget;
        });
    }

    public function duplicate(Opportunity $case, User $actor, int $revision): Budget
    {
        return DB::transaction(function () use ($case, $actor, $revision) {
            Opportunity::whereKey($case->id)->lockForUpdate()->firstOrFail();
            $source = $case->budgets()->latest('version')->lockForUpdate()->firstOrFail();
            if ($source->revision !== $revision) {
                throw ValidationException::withMessages(['revision' => 'A versão mudou. Atualize antes de duplicar.']);
            }
            $copy = $case->budgets()->create(['version' => $source->version + 1, 'revision' => 0, 'status' => 'draft', 'purpose' => $source->purpose, 'calculation_mode' => $source->calculation_mode, 'notes' => $source->notes]);
            foreach ($source->items as $item) {
                $copy->items()->create($item->only(array_diff($item->getFillable(), ['budget_id'])));
            }
            $this->audit($actor, $copy, 'budget.version_created');

            return $copy;
        });
    }

    private function audit(User $actor, Budget $budget, string $action): void
    {
        AuditLog::create(['user_id' => $actor->id, 'action' => $action, 'subject_type' => Opportunity::class, 'subject_id' => $budget->opportunity_id, 'metadata' => ['budget_id' => $budget->id, 'version' => $budget->version, 'revision' => $budget->revision]]);
    }
}
