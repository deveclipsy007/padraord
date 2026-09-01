<?php

namespace App\Services;

use App\Models\Opportunity;
use App\Models\SupplierQuote;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BudgetIntake
{
    public function import(Opportunity $o, User $user, array $input): array
    {
        $v = Validator::make($input, ['revision' => 'required|integer|min:0', 'request_key' => 'required|string|max:100', 'quote_id' => 'nullable|integer', 'preparation_id' => 'required_without:quote_id|nullable|integer', 'index' => 'required_without:quote_id|nullable|integer|min:0', 'quantity' => 'required_without:quote_id|nullable|decimal:0,2|min:0.01|max:10000', 'unit' => 'required_without:quote_id|nullable|string|max:40'])->validate();

        return DB::transaction(function () use ($o, $user, $v) {
            DB::table('opportunities')->where('id', $o->id)->update(['updated_at' => now()]);
            $old = DB::table('budget_intakes')->where('opportunity_id', $o->id)->where('request_key', $v['request_key'])->first();
            if ($old) {
                return json_decode($old->result, true);
            }
            if ($v['quote_id'] ?? null) {
                $q = SupplierQuote::with('supplier')->where('opportunity_id', $o->id)->findOrFail($v['quote_id']);
                if ($q->valid_until->lt(today()) || ! $q->price_basis || ! $q->quantity || ! $q->unit) {
                    throw ValidationException::withMessages(['quote' => 'Confirme base, quantidade, unidade e validade da cotação antes de importar.']);
                }
                $data = ['category' => 'Produção', 'description' => $q->service, 'supplier_quote_id' => $q->id, 'supplier' => $q->supplier->name, 'quote_valid_until' => $q->valid_until, 'unit_cost_cents' => $q->unit_cost_cents, 'quantity' => $q->price_basis === 'total' ? 1 : $q->quantity, 'unit' => $q->price_basis === 'total' ? 'pacote' : $q->unit, 'notes' => $q->price_basis === 'total' ? 'Pacote total cobrindo '.$q->quantity.' '.$q->unit.'. Não multiplicar pelo número de peças.' : 'Preço unitário da cotação #'.$q->id];
            } else {
                $p = DB::table('assistance_drafts')->where('opportunity_id', $o->id)->where('id', $v['preparation_id'])->first();
                if (! $p || (int) $p->revision !== $o->fresh()->briefing_revision) {
                    throw ValidationException::withMessages(['preparation' => 'A preparação mudou. Gere um novo roteiro após revisar o briefing.']);
                }$item = json_decode($p->payload, true)['budget'][$v['index']] ?? null;
                if (! $item) {
                    throw ValidationException::withMessages(['preparation' => 'Item não encontrado.']);
                }$data = ['category' => 'A cotar', 'description' => mb_substr($item['description'], 0, 180), 'quantity' => $v['quantity'], 'unit' => $v['unit'], 'unit_cost_cents' => 0, 'notes' => 'Rascunho sem preço. Origem: preparação #'.$p->id];
            }
            $budget = app(BudgetRevisionService::class)->mutate($o, $user, (int) $v['revision'], fn ($b) => $b->items()->create($data));
            $result = ['href' => '/opportunities/'.$o->id.'/budget', 'budget_id' => $budget->id, 'revision' => $budget->revision];
            DB::table('budget_intakes')->insert(['opportunity_id' => $o->id, 'request_key' => $v['request_key'], 'result' => json_encode($result), 'created_at' => now(), 'updated_at' => now()]);

            return $result;
        }, 3);
    }
}
