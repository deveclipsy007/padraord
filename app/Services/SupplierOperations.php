<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Supplier;
use App\Models\SupplierQuote;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SupplierOperations
{
    public function save(array $input, User $user, ?Supplier $supplier = null): Supplier
    {
        $data = Validator::make($input, ['name' => 'required|string|max:160', 'service' => 'nullable|string|max:160', 'email' => 'nullable|email|max:160', 'phone' => 'nullable|string|max:60', 'notes' => 'nullable|string|max:5000', 'status' => 'sometimes|in:active,inactive', 'revision' => $supplier ? 'required|integer|min:0' : 'sometimes|integer'])->validate();

        return DB::transaction(function () use ($data, $user, $supplier) {
            if ($supplier) {
                if (! Supplier::whereKey($supplier->id)->where('revision', $data['revision'])->increment('revision')) {
                    throw ValidationException::withMessages(['revision' => 'Fornecedor alterado. Atualize antes de salvar.']);
                }unset($data['revision']);
                $supplier->refresh()->update($data);
            } else {
                $supplier = Supplier::create($data);
            }
            $this->audit($user, 'supplier.saved', $supplier);

            return $supplier;
        }, 3);
    }

    public function quote(array $input, User $user, Supplier $supplier): SupplierQuote
    {
        $data = Validator::make($input, ['opportunity_id' => 'required|exists:opportunities,id', 'service' => 'required|string|max:180', 'unit_cost' => 'required|string|max:16', 'valid_until' => 'required|date', 'conditions' => 'nullable|string|max:5000', 'evidence' => 'required|string|max:5000', 'price_basis' => 'nullable|in:unit,total', 'quantity' => 'nullable|decimal:0,2|min:0.01|max:10000', 'unit' => 'nullable|string|max:40', 'supersedes_id' => 'nullable|integer', 'inquiry_id' => 'nullable|integer'])->validate();
        $data['unit_cost_cents'] = Money::decimal($data['unit_cost'], 'unit_cost');
        unset($data['unit_cost']);
        if ($data['unit_cost_cents'] < 1 || $data['unit_cost_cents'] > 100000000) {
            throw ValidationException::withMessages(['unit_cost' => 'Informe custo entre R$ 0,01 e R$ 1.000.000,00.']);
        }
        if ($data['supersedes_id'] ?? null) {
            SupplierQuote::where('supplier_id', $supplier->id)->where('opportunity_id', $data['opportunity_id'])->findOrFail($data['supersedes_id']);
        }

        return DB::transaction(function () use ($data, $user, $supplier) {
            $inquiry = $data['inquiry_id'] ?? null;
            unset($data['inquiry_id']);
            $q = SupplierQuote::create($data + ['supplier_id' => $supplier->id]);
            if ($inquiry) {
                $found = DB::table('supplier_inquiries')->where('id', $inquiry)->where('supplier_id', $supplier->id)->where('opportunity_id', $q->opportunity_id)->update(['status' => 'received', 'updated_at' => now()]);
                if (! $found) {
                    throw ValidationException::withMessages(['inquiry_id' => 'Consulta não corresponde ao caso e fornecedor.']);
                }
            }$this->audit($user, 'supplier.quote_created', $q);

            return $q;
        });
    }

    public function inquiry(array $input, User $user, Supplier $supplier): int
    {
        $v = Validator::make($input, ['opportunity_id' => 'required|exists:opportunities,id', 'service' => 'required|string|max:180', 'notes' => 'nullable|string|max:5000'])->validate();

        return DB::transaction(function () use ($v, $user, $supplier) {
            $id = DB::table('supplier_inquiries')->insertGetId($v + ['supplier_id' => $supplier->id, 'status' => 'requested', 'created_at' => now(), 'updated_at' => now()]);
            AuditLog::create(['user_id' => $user->id, 'action' => 'supplier.inquiry_created', 'subject_type' => 'supplier_inquiries', 'subject_id' => $id]);

            return $id;
        });
    }

    private function audit(User $user, string $action, $subject): void
    {
        AuditLog::create(['user_id' => $user->id, 'action' => $action, 'subject_type' => $subject::class, 'subject_id' => $subject->id]);
    }
}
