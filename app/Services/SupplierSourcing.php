<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Opportunity;
use App\Models\SupplierNeed;
use App\Models\SupplierQuote;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class SupplierSourcing
{
    public function createNeed(Opportunity $opportunity, User $actor, array $input): SupplierNeed
    {
        $data = Validator::make($input, [
            'category' => ['required', 'string', 'max:100'],
            'scope' => ['required', 'string', 'max:5000'],
            'quantity' => ['nullable', 'decimal:0,2', 'min:0.01', 'max:10000'],
            'unit' => ['nullable', 'string', 'max:40'],
            'required_date' => ['nullable', 'date'],
            'technical_requirements' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', Rule::in(['draft', 'ready_to_quote', 'quoting', 'quoted', 'selected', 'cancelled'])],
        ])->validate();

        return DB::transaction(function () use ($opportunity, $actor, $data) {
            $need = $opportunity->supplierNeeds()->create($data);
            AuditLog::create([
                'user_id' => $actor->id,
                'action' => 'supplier.need_created',
                'subject_type' => Opportunity::class,
                'subject_id' => $opportunity->id,
                'metadata' => ['supplier_need_id' => $need->id, 'category' => $need->category],
            ]);

            return $need;
        });
    }

    public function selectQuote(Opportunity $opportunity, SupplierNeed $need, SupplierQuote $quote, User $actor, string $note): void
    {
        if ($need->opportunity_id !== $opportunity->id || $quote->opportunity_id !== $opportunity->id) {
            abort(404);
        }
        if ($quote->valid_until->lt(today()) || ! $quote->price_basis || ! $quote->quantity || ! $quote->unit) {
            throw ValidationException::withMessages(['quote' => 'A cotação precisa estar vigente e ter preço, quantidade, unidade e base confirmados.']);
        }

        DB::transaction(function () use ($opportunity, $need, $quote, $actor, $note) {
            DB::table('supplier_quote_selections')->updateOrInsert(
                ['supplier_need_id' => $need->id, 'supplier_quote_id' => $quote->id],
                ['opportunity_id' => $opportunity->id, 'selected_by' => $actor->id, 'note' => $note, 'updated_at' => now(), 'created_at' => now()]
            );
            $need->update(['status' => 'selected', 'revision' => $need->revision + 1]);
            AuditLog::create([
                'user_id' => $actor->id,
                'action' => 'supplier.quote_selected',
                'subject_type' => Opportunity::class,
                'subject_id' => $opportunity->id,
                'metadata' => ['supplier_need_id' => $need->id, 'supplier_quote_id' => $quote->id],
            ]);
        });
    }
}
