<?php

namespace App\Http\Controllers;

use App\Models\Opportunity;
use App\Models\Supplier;
use App\Models\SupplierQuote;
use App\Models\SupplierQuoteComparison;
use App\Services\SupplierOperations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SupplierController extends Controller
{
    public function duplicates(Request $request)
    {
        $v = $request->validate(['name' => 'nullable|string|max:160', 'email' => 'nullable|string|max:160', 'phone' => 'nullable|string|max:60', 'exclude' => 'nullable|integer']);
        if (! filled($v['name'] ?? null) && ! filled($v['email'] ?? null) && ! filled($v['phone'] ?? null)) {
            return response()->json([]);
        }

        return response()->json(Supplier::when($v['exclude'] ?? null, fn ($q, $id) => $q->where('id', '!=', $id))->where(function ($q) use ($v) {
            $q->whereRaw('1 = 0');
            foreach (['name', 'email', 'phone'] as $field) {
                if (filled($v[$field] ?? null)) {
                    $q->orWhereRaw('LOWER('.$field.') = ?', [mb_strtolower(trim($v[$field]))]);
                }
            }
        })->limit(5)->get(['id', 'name']));
    }

    public function index(Request $request)
    {
        $v = $request->validate(['q' => 'nullable|string|max:160', 'service' => 'nullable|string|max:160', 'status' => 'nullable|in:active,inactive', 'tab' => 'nullable|in:suppliers,quotes', 'opportunity_id' => 'nullable|integer|exists:opportunities,id', 'supplier_id' => 'nullable|integer|exists:suppliers,id', 'quote_status' => 'nullable|in:current,expired', 'page' => 'nullable|integer|min:1', 'quotes_page' => 'nullable|integer|min:1']);
        $suppliers = Supplier::query()->when($v['q'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($s).'%'])->orWhereRaw('LOWER(service) LIKE ?', ['%'.mb_strtolower($s).'%'])))->when($v['service'] ?? null, fn ($q, $s) => $q->where('service', $s))->when($v['status'] ?? null, fn ($q, $s) => $q->where('status', $s))->orderBy('name')->paginate(25)->withQueryString();
        $quotes = SupplierQuote::with('supplier')->when($v['opportunity_id'] ?? null, fn ($q, $id) => $q->where('opportunity_id', $id))->when($v['supplier_id'] ?? null, fn ($q, $id) => $q->where('supplier_id', $id))->when($v['quote_status'] ?? null, fn ($q, $s) => $q->whereDate('valid_until', $s === 'expired' ? '<' : '>=', today()))->latest()->paginate(25, ['*'], 'quotes_page')->withQueryString();
        $revisedQuoteIds = SupplierQuote::whereIn('supersedes_id', $quotes->getCollection()->pluck('id'))->pluck('supersedes_id')->all();
        $quotes->getCollection()->transform(function (SupplierQuote $quote) use ($revisedQuoteIds): SupplierQuote {
            $quote->setAttribute('is_current_revision', ! in_array($quote->id, $revisedQuoteIds, true));
            $quote->setAttribute('is_valid', $quote->valid_until->gte(today()));

            return $quote;
        });
        $inquiries = DB::table('supplier_inquiries')->join('suppliers', 'suppliers.id', '=', 'supplier_inquiries.supplier_id')->where('supplier_inquiries.status', 'requested')->when($v['opportunity_id'] ?? null, fn ($q, $id) => $q->where('opportunity_id', $id))->when($v['supplier_id'] ?? null, fn ($q, $id) => $q->where('supplier_id', $id))->select('supplier_inquiries.*', 'suppliers.name as supplier_name')->limit(30)->get();
        $comparisons = SupplierQuoteComparison::query()->with('items')->when($v['opportunity_id'] ?? null, fn ($q, $id) => $q->where('opportunity_id', $id))->latest()->limit(12)->get()->map(fn (SupplierQuoteComparison $comparison): array => [
            'id' => $comparison->id,
            'opportunity_id' => $comparison->opportunity_id,
            'title' => $comparison->title,
            'status' => $comparison->status,
            'scope_difference' => $comparison->scope_difference,
            'justification' => $comparison->justification,
            'decision_quote_id' => $comparison->decision_quote_id,
            'created_at' => $comparison->created_at?->toIso8601String(),
            'decided_at' => $comparison->decided_at?->toIso8601String(),
            'items' => $comparison->items->sortBy('id')->map(fn ($item): array => $item->snapshot + ['normalized_total_cents' => $item->normalized_total_cents])->values(),
        ]);

        return Inertia::render('Suppliers', ['suppliers' => $suppliers, 'quotes' => $quotes, 'inquiries' => $inquiries, 'comparisons' => $comparisons, 'filters' => $v, 'services' => Supplier::whereNotNull('service')->distinct()->pluck('service'), 'supplierOptions' => Supplier::orderBy('name')->get(['id', 'name']), 'opportunities' => Opportunity::orderBy('title')->get(['id', 'title'])]);
    }

    public function show(Supplier $supplier)
    {
        $quotes = SupplierQuote::where('supplier_id', $supplier->id)->latest()->get();
        $duplicates = Supplier::where('id', '!=', $supplier->id)->where(function ($q) use ($supplier) {
            $q->whereRaw('LOWER(name) = ?', [mb_strtolower($supplier->name)]);
            if ($supplier->email) {
                $q->orWhere('email', $supplier->email);
            }if ($supplier->phone) {
                $q->orWhere('phone', $supplier->phone);
            }
        })->get(['id', 'name']);

        return Inertia::render('SupplierProfile', ['supplier' => $supplier, 'quotes' => $quotes, 'duplicates' => $duplicates, 'cases' => Opportunity::whereIn('id', $quotes->pluck('opportunity_id'))->get(['id', 'title'])]);
    }

    public function store(Request $r, SupplierOperations $s)
    {
        $s->save($r->all(), $r->user());

        return back()->with('success', 'Fornecedor cadastrado.');
    }

    public function update(Request $r, Supplier $supplier, SupplierOperations $s)
    {
        $s->save($r->all(), $r->user(), $supplier);

        return back()->with('success', 'Cadastro atualizado; cotações preservadas.');
    }

    public function quote(Request $r, Supplier $supplier, SupplierOperations $s)
    {
        $s->quote($r->all(), $r->user(), $supplier);

        return back()->with('success', 'Nova cotação preservada. Selecionar não significa contratar.');
    }

    public function inquiry(Request $r, Supplier $supplier, SupplierOperations $s)
    {
        $s->inquiry($r->all(), $r->user(), $supplier);

        return back()->with('success', 'Consulta registrada. Nenhum contato externo foi enviado.');
    }
}
