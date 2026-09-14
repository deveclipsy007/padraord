<?php

namespace App\Services;

use App\Enums\Ability;
use App\Models\AuditLog;
use App\Models\BudgetItem;
use App\Models\EventBrief;
use App\Models\Opportunity;
use App\Models\SupplierQuote;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class EventFinance
{
    public const TRIGGERS = ['assinatura', 'dias_antes_evento', 'dias_apos_evento', 'entrega', 'data_fixa', 'marco'];

    public function savePlan(Opportunity $case, User $actor, array $input): int
    {
        $v = Validator::make($input, ['revision' => 'required|integer|min:0', 'total_cents' => 'required|integer|min:1|max:99999999999', 'installments' => 'required|array|min:1|max:100', 'installments.*.label' => 'required|string|max:180', 'installments.*.share_bps' => 'required|integer|min:1|max:10000', 'installments.*.trigger' => ['required', Rule::in(self::TRIGGERS)], 'installments.*.offset_days' => 'nullable|integer|min:0|max:3650', 'installments.*.due_at' => 'nullable|date_format:Y-m-d', 'installments.*.milestone' => 'nullable|string|max:180'])->validate();
        if (array_sum(array_column($v['installments'], 'share_bps')) !== 10000) {
            $this->fail('installments', 'As porcentagens devem somar exatamente 100%.');
        }
        $items = [];
        $cumulative = 0;
        $allocated = 0;
        foreach ($v['installments'] as $i => $item) {
            if ($item['trigger'] === 'data_fixa' && empty($item['due_at'])) {
                $this->fail('installments', 'Informe a data de cada parcela fixa.');
            }
            if (in_array($item['trigger'], ['dias_antes_evento', 'dias_apos_evento']) && ! isset($item['offset_days'])) {
                $this->fail('installments', 'Informe a quantidade de dias em relação ao evento.');
            }
            if (in_array($item['trigger'], ['marco', 'entrega']) && blank($item['milestone'] ?? null)) {
                $this->fail('installments', 'Identifique o marco ou entrega que vence a parcela.');
            }
            $cumulative += $item['share_bps'];
            $target = Money::ratio($v['total_cents'], $cumulative, 10000);
            $amount = $target - $allocated;
            $allocated = $target;
            if ($amount < 1) {
                $this->fail('installments', 'Uma parcela ficou abaixo de um centavo. Ajuste o rateio.');
            }
            $items[] = ['sequence' => $i + 1, 'label' => $item['label'], 'share_bps' => $item['share_bps'], 'amount_cents' => $amount, 'trigger' => $item['trigger'], 'offset_days' => $item['offset_days'] ?? null, 'due_at' => $item['trigger'] === 'data_fixa' ? $item['due_at'] : null, 'milestone' => $item['milestone'] ?? null, 'created_at' => now(), 'updated_at' => now()];
        }

        return DB::transaction(function () use ($case, $actor, $v, $items) {
            $this->lock($case);
            $plan = DB::table('payment_plans')->where('opportunity_id', $case->id)->first();
            if ($plan?->status === 'accepted') {
                $this->fail('plan', 'O plano aceito é imutável. Registre um aditivo comercial antes de renegociar.');
            }$this->revision($v['revision'], $plan?->revision ?? 0);
            $data = ['total_cents' => $v['total_cents'], 'revision' => $v['revision'] + 1, 'updated_at' => now()];
            if ($plan) {
                $id = $plan->id;
                DB::table('payment_plans')->where('id', $id)->update($data);
                DB::table('payment_plan_installments')->where('payment_plan_id', $id)->delete();
            } else {
                $id = DB::table('payment_plans')->insertGetId($data + ['opportunity_id' => $case->id, 'created_at' => now()]);
            }
            foreach ($items as $item) {
                DB::table('payment_plan_installments')->insert($item + ['payment_plan_id' => $id]);
            }
            $this->audit($case, $actor, 'finance.plan_saved', ['plan_id' => $id, 'revision' => $data['revision'], 'total_cents' => $v['total_cents'], 'installments' => $items]);

            return $id;
        }, 3);
    }

    public function acceptPlan(Opportunity $case, User $actor, int $id, array $input): void
    {
        Gate::forUser($actor)->authorize(Ability::ApproveCommercial->value);
        $v = Validator::make($input, ['revision' => 'required|integer|min:1', 'evidence' => 'required|string|min:3|max:5000'])->validate();
        DB::transaction(function () use ($case, $actor, $id, $v) {
            $this->lock($case);
            $p = $this->planRow($case, $id);
            if ($p->status === 'accepted') {
                if ($p->acceptance_evidence !== $v['evidence']) {
                    $this->fail('plan', 'O aceite já possui outra evidência.');
                }

                return;
            }$this->revision($v['revision'], $p->revision);
            DB::table('payment_plans')->where('id', $id)->update(['status' => 'accepted', 'accepted_by' => $actor->id, 'accepted_at' => now(), 'acceptance_evidence' => $v['evidence'], 'revision' => $p->revision + 1, 'updated_at' => now()]);
            $this->audit($case, $actor, 'finance.plan_acceptance_recorded', ['plan_id' => $id, 'evidence' => $v['evidence']]);
        }, 3);
    }

    public function plan(Opportunity $case): ?array
    {
        $p = DB::table('payment_plans')->where('opportunity_id', $case->id)->first();

        return $p ? [...(array) $p, 'installments' => DB::table('payment_plan_installments')->where('payment_plan_id', $p->id)->orderBy('sequence')->get()->map(fn ($i) => (array) $i)->all()] : null;
    }

    private function context(Opportunity $case): array
    {
        $b = EventBrief::where('opportunity_id', $case->id)->first();

        return ['starts_at' => $b?->starts_at?->toIso8601String() ?? $case->fresh()->event_date?->toIso8601String(), 'ends_at' => $b?->ends_at?->toIso8601String(), 'timezone' => $b?->timezone ?? config('app.timezone')];
    }

    public function previewReceivables(Opportunity $case, User $actor, int $id): int
    {
        return DB::transaction(function () use ($case, $actor, $id) {
            $this->lock($case);
            $p = $this->planRow($case, $id);
            if ($p->status !== 'accepted') {
                $this->fail('plan', 'Registre o aceite do plano antes de gerar recebíveis.');
            }$context = $this->context($case);
            $items = [];
            foreach (DB::table('payment_plan_installments')->where('payment_plan_id', $id)->orderBy('sequence')->get() as $i) {
                $due = $i->due_at;
                if ($i->trigger === 'assinatura') {
                    $due = Carbon::parse($p->accepted_at)->toDateString();
                }
                if ($i->trigger === 'dias_antes_evento' && $context['starts_at']) {
                    $due = Carbon::parse($context['starts_at'])->timezone($context['timezone'])->subDays($i->offset_days)->toDateString();
                }
                if ($i->trigger === 'dias_apos_evento' && $context['ends_at']) {
                    $due = Carbon::parse($context['ends_at'])->timezone($context['timezone'])->addDays($i->offset_days)->toDateString();
                }
                $items[] = ['payment_plan_installment_id' => $i->id, 'label' => $i->label, 'amount_cents' => $i->amount_cents, 'due_at' => $due, 'trigger' => $i->trigger, 'milestone' => $i->milestone];
            }

            return DB::table('receivable_previews')->insertGetId(['opportunity_id' => $case->id, 'payment_plan_id' => $id, 'created_by' => $actor->id, 'plan_revision' => $p->revision, 'context_hash' => hash('sha256', json_encode($context)), 'items' => json_encode($items), 'created_at' => now(), 'updated_at' => now()]);
        }, 3);
    }

    public function confirmReceivables(Opportunity $case, User $actor, int $id): void
    {
        DB::transaction(function () use ($case, $actor, $id) {
            $this->lock($case);
            $p = DB::table('receivable_previews')->where('id', $id)->where('opportunity_id', $case->id)->where('created_by', $actor->id)->first();
            abort_unless($p, 404);
            if ($p->confirmed_at) {
                return;
            }$plan = $this->planRow($case, $p->payment_plan_id);
            if ($plan->status !== 'accepted' || $plan->revision !== $p->plan_revision || $p->context_hash !== hash('sha256', json_encode($this->context($case)))) {
                $this->fail('preview', 'O plano ou a data do evento mudou. Gere uma nova prévia.');
            }
            foreach (json_decode($p->items, true) as $i) {
                DB::table('receivables')->insertOrIgnore($i + ['opportunity_id' => $case->id, 'created_at' => now(), 'updated_at' => now()]);
            }DB::table('receivable_previews')->where('id', $id)->update(['confirmed_at' => now(), 'updated_at' => now()]);
            $this->audit($case, $actor, 'finance.receivables_created', ['preview_id' => $id]);
        }, 3);
    }

    public function createPayable(Opportunity $case, User $actor, array $input): int
    {
        if (empty($input['origin_type']) || empty($input['origin_id'])) {
            $this->fail('origin', 'Vincule um item aprovado ou uma cotação selecionada.');
        }
        $v = Validator::make($input, ['origin_type' => 'required|in:budget_item,quote', 'origin_id' => 'required|integer|min:1', 'label' => 'required|string|max:180', 'amount_cents' => 'required|integer|min:1|max:99999999999', 'due_at' => 'nullable|date_format:Y-m-d'])->validate();

        return DB::transaction(function () use ($case, $actor, $v) {
            $this->lock($case);
            $snapshot = $this->origin($case, $v['origin_type'], $v['origin_id']);
            if ($v['amount_cents'] > $snapshot['limit_cents']) {
                $this->fail('amount_cents', 'O valor supera a origem aprovada. Revise primeiro o orçamento ou a cotação.');
            }$existing = DB::table('payables')->where('opportunity_id', $case->id)->where('origin_type', $v['origin_type'])->where('origin_id', $v['origin_id'])->first();
            if ($existing) {
                $this->fail('origin', 'Esta origem já possui um pagável. Consulte o registro existente.');
            }
            if ($snapshot['quote_id'] ?? null) {
                foreach (DB::table('payables')->where('opportunity_id', $case->id)->get() as $payable) {
                    if (data_get(json_decode($payable->origin_snapshot, true), 'quote_id') === $snapshot['quote_id']) {
                        $this->fail('origin', 'Esta cotação já está vinculada a um pagável. Preserve uma única origem para a despesa.');
                    }
                }
            }
            $id = DB::table('payables')->insertGetId($v + ['opportunity_id' => $case->id, 'category' => $snapshot['category'], 'origin_snapshot' => json_encode($snapshot), 'created_at' => now(), 'updated_at' => now()]);
            $this->audit($case, $actor, 'finance.payable_created', ['id' => $id, 'origin' => $snapshot]);

            return $id;
        }, 3);
    }

    private function origin(Opportunity $case, string $type, int $id): array
    {
        if ($type === 'budget_item') {
            $i = BudgetItem::with('budget')->findOrFail($id);
            abort_unless($i->budget->opportunity_id === $case->id, 404);
            if ($i->budget->status !== 'approved') {
                $this->fail('origin', 'O orçamento de origem precisa estar aprovado.');
            }$m = Money::breakdown($i);

            return ['category' => $i->category, 'quote_id' => $i->supplier_quote_id, 'limit_cents' => $m['cost'] + $m['tax'] + $m['contingency'], 'budget_id' => $i->budget_id, 'revision' => $i->budget->revision, 'item' => $i->toArray()];
        }
        $q = SupplierQuote::findOrFail($id);
        $selection = DB::table('supplier_quote_selections')->where('opportunity_id', $case->id)->where('supplier_quote_id', $id)->first();
        abort_unless($q->opportunity_id === $case->id && $selection, 404);

        return ['category' => DB::table('supplier_needs')->where('id', $selection->supplier_need_id)->value('category') ?? $q->service, 'quote_id' => $q->id, 'limit_cents' => $q->price_basis === 'total' ? $q->unit_cost_cents : Money::ratio($q->unit_cost_cents, Money::decimal((string) ($q->quantity ?? 1)), 100), 'quote' => $q->toArray(), 'selection_id' => $selection->id];
    }

    public function approvePayable(Opportunity $case, User $actor, int $id, int $revision): void
    {
        Gate::forUser($actor)->authorize(Ability::ApproveCommercial->value);
        DB::transaction(function () use ($case, $actor, $id, $revision) {
            $this->lock($case);
            $r = $this->entry($case, 'payables', $id);
            $this->revision($revision, $r->revision);
            if ($r->status !== 'open') {
                $this->fail('approval', 'Este pagável não está aberto.');
            }$origin = $this->origin($case, $r->origin_type, $r->origin_id);
            if (json_encode($origin) !== json_encode(json_decode($r->origin_snapshot, true))) {
                $this->fail('origin', 'A origem mudou desde o registro. Confira a divergência antes da aprovação.');
            }DB::table('payables')->where('id', $id)->update(['approved_by' => $actor->id, 'approved_at' => now(), 'revision' => $revision + 1, 'updated_at' => now()]);
            $this->audit($case, $actor, 'finance.payable_approved', ['id' => $id]);
        }, 3);
    }

    public function settle(Opportunity $case, User $actor, string $table, int $id, array $input): void
    {
        $this->table($table);
        $v = Validator::make($input, ['revision' => 'required|integer|min:0', 'amount_cents' => 'required|integer|min:1|max:99999999999', 'request_key' => 'required|string|min:6|max:100', 'paid_at' => 'required|date_format:Y-m-d|before_or_equal:today', 'evidence' => 'required|string|min:3|max:5000'])->validate();
        DB::transaction(function () use ($case, $actor, $table, $id, $v) {
            $this->lock($case);
            $r = $this->entry($case, $table, $id);
            $prior = DB::table('financial_settlements')->where('opportunity_id', $case->id)->where('request_key', $v['request_key'])->first();
            if ($prior) {
                if ($prior->ledger !== $table || $prior->entry_id !== $id || $prior->amount_cents !== $v['amount_cents'] || $prior->evidence !== $v['evidence'] || $prior->paid_at !== $v['paid_at']) {
                    $this->fail('request_key', 'Esta operação já foi registrada com outros dados.');
                }

                return;
            }$this->revision($v['revision'], $r->revision);
            if ($r->status === 'cancelled') {
                $this->fail('status', 'O lançamento está cancelado.');
            }if ($table === 'payables' && ! $r->approved_at) {
                $this->fail('approval', 'Aprove o pagável antes de registrar pagamento.');
            }$paid = $this->paid($table, $id);
            if ($v['amount_cents'] > $r->amount_cents - $paid) {
                $this->fail('amount_cents', 'A baixa não pode superar o saldo.');
            }$data = $v;
            unset($data['revision']);
            DB::table('financial_settlements')->insert($data + ['opportunity_id' => $case->id, 'ledger' => $table, 'entry_id' => $id, 'recorded_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
            DB::table($table)->where('id', $id)->update(['revision' => $r->revision + 1, 'status' => $paid + $v['amount_cents'] === $r->amount_cents ? 'paid' : 'open', 'updated_at' => now()]);
            $this->audit($case, $actor, 'finance.settlement_recorded', ['ledger' => $table, 'entry_id' => $id, 'amount_cents' => $v['amount_cents'], 'request_key' => $v['request_key']]);
        }, 3);
    }

    public function confirmDue(Opportunity $case, User $actor, int $id, array $input): void
    {
        $v = Validator::make($input, ['revision' => 'required|integer|min:0', 'due_at' => 'required|date_format:Y-m-d', 'evidence' => 'required|string|min:3|max:5000'])->validate();
        DB::transaction(function () use ($case, $actor, $id, $v) {
            $this->lock($case);
            $r = $this->entry($case, 'receivables', $id);
            $this->revision($v['revision'], $r->revision);
            if ($r->due_at || $r->status !== 'open') {
                $this->fail('due_at', 'O vencimento já está definido ou o recebível está encerrado.');
            }DB::table('receivables')->where('id', $id)->update(['due_at' => $v['due_at'], 'revision' => $r->revision + 1, 'updated_at' => now()]);
            $this->audit($case, $actor, 'finance.receivable_trigger_confirmed', ['id' => $id, 'trigger' => $r->trigger, 'due_at' => $v['due_at'], 'evidence' => $v['evidence']]);
        }, 3);
    }

    public function cancel(Opportunity $case, User $actor, string $table, int $id, array $input): void
    {
        $this->table($table);
        $v = Validator::make($input, ['revision' => 'required|integer|min:0', 'reason' => 'required|string|min:3|max:5000'])->validate();
        DB::transaction(function () use ($case, $actor, $table, $id, $v) {
            $this->lock($case);
            $r = $this->entry($case, $table, $id);
            $this->revision($v['revision'], $r->revision);
            DB::table($table)->where('id', $id)->update(['status' => 'cancelled', 'cancellation_reason' => $v['reason'], 'cancelled_at' => now(), 'revision' => $r->revision + 1, 'updated_at' => now()]);
            $this->audit($case, $actor, 'finance.entry_cancelled', ['ledger' => $table, 'id' => $id, 'reason' => $v['reason'], 'paid_cents' => $this->paid($table, $id)]);
        }, 3);
    }

    public function ledger(Opportunity $case, string $table): array
    {
        $this->table($table);
        $paid = DB::table('financial_settlements')->where('opportunity_id', $case->id)->where('ledger', $table)->selectRaw('entry_id, SUM(amount_cents) AS paid')->groupBy('entry_id')->pluck('paid', 'entry_id');

        return DB::table($table)->where('opportunity_id', $case->id)->orderBy('id')->get()->map(fn ($r) => [...(array) $r, 'paid_cents' => (int) ($paid[$r->id] ?? 0), 'balance_cents' => $r->amount_cents - (int) ($paid[$r->id] ?? 0), 'overdue' => $r->status === 'open' && $r->due_at && Carbon::parse($r->due_at)->lt(today())])->all();
    }

    public function saveCost(Opportunity $case, User $actor, array $input): void
    {
        $v = Validator::make($input, ['revision' => 'required|integer|min:0', 'category' => 'required|string|max:100', 'actual_cents' => 'required|integer|min:0|max:99999999999', 'variance_reason' => 'nullable|string|max:5000'])->validate();
        DB::transaction(function () use ($case, $actor, $v) {
            $this->lock($case);
            $r = DB::table('event_cost_results')->where('opportunity_id', $case->id)->where('category', $v['category'])->first();
            $this->revision($v['revision'], $r?->revision ?? 0);
            $paid = collect($this->ledger($case, 'payables'))->where('category', $v['category'])->sum('paid_cents');
            if ($v['actual_cents'] < $paid) {
                $this->fail('actual_cents', 'O realizado não pode ser menor que os pagamentos registrados nesta categoria.');
            }DB::table('event_cost_results')->updateOrInsert(['opportunity_id' => $case->id, 'category' => $v['category']], ['actual_cents' => $v['actual_cents'], 'variance_reason' => $v['variance_reason'] ?? null, 'revision' => $v['revision'] + 1, 'recorded_by' => $actor->id, 'created_at' => $r?->created_at ?? now(), 'updated_at' => now()]);
            $this->audit($case, $actor, 'finance.cost_reconciled', $v);
        }, 3);
    }

    private function paid(string $table, int $id): int
    {
        return (int) DB::table('financial_settlements')->where('ledger', $table)->where('entry_id', $id)->sum('amount_cents');
    }

    private function table(string $table): void
    {
        abort_unless(in_array($table, ['receivables', 'payables'], true), 404);
    }

    private function entry(Opportunity $case, string $table, int $id): object
    {
        return DB::table($table)->where('opportunity_id', $case->id)->where('id', $id)->lockForUpdate()->firstOrFail();
    }

    private function planRow(Opportunity $case, int $id): object
    {
        return DB::table('payment_plans')->where('opportunity_id', $case->id)->where('id', $id)->firstOrFail();
    }

    private function lock(Opportunity $case): void
    {
        Opportunity::whereKey($case->id)->lockForUpdate()->firstOrFail();
        if ($case->postEventReport()->where('status', 'closed')->exists()) {
            $this->fail('status', 'Reabra o pós-evento antes de alterar o financeiro.');
        }
    }

    private function revision(int $given, int $current): void
    {
        if ($given !== $current) {
            $this->fail('revision', 'O registro mudou. Atualize antes de salvar.');
        }
    }

    private function audit(Opportunity $case, User $actor, string $action, array $metadata): void
    {
        AuditLog::create(['user_id' => $actor->id, 'action' => $action, 'subject_type' => Opportunity::class, 'subject_id' => $case->id, 'metadata' => $metadata]);
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
