<?php

namespace App\Http\Controllers;

use App\Models\BudgetItem;
use App\Models\Opportunity;
use App\Models\SupplierQuote;
use App\Services\AssistancePreparation;
use App\Services\BudgetIntake;
use App\Services\BudgetRevisionService;
use App\Services\Money;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class BudgetController extends Controller
{
    public function show(Request $request, Opportunity $opportunity)
    {
        $budget = $opportunity->budgets()->with('items')->latest('version')->first();
        $items = $budget?->items->map(fn ($item) => [
            'id' => $item->id, 'category' => $item->category, 'description' => $item->description,
            'quantity' => $item->quantity, 'unit' => $item->unit, 'unitCostCents' => $item->unit_cost_cents,
            'supplier' => $item->supplier, 'quoteId' => $item->supplier_quote_id, 'quoteValidUntil' => $item->quote_valid_until?->format('Y-m-d'),
            'managementBps' => $item->management_bps, 'administrationBps' => $item->administration_bps,
            'contingencyCents' => $item->contingency_cents, 'marginPercent' => $item->margin_percent,
            'calculation' => Money::breakdown($item), 'sellTotalCents' => $item->sell_total_cents,
        ]) ?? collect();

        return Inertia::render('Budget', [
            'selectedQuoteId' => (int) $request->input('quote_id', 0),
            'preparation' => app(AssistancePreparation::class)->latest($opportunity),
            'opportunity' => ['id' => $opportunity->id, 'title' => $opportunity->title, 'clientName' => $opportunity->client_name, 'stage' => $opportunity->stage->value],
            'budget' => ['id' => $budget?->id, 'version' => $budget?->version ?? 1, 'revision' => $budget?->revision ?? 0, 'status' => $budget?->status ?? 'draft', 'purpose' => $budget?->purpose ?? 'preliminary', 'items' => $items, 'totalCents' => $budget?->status === 'approved' ? data_get($budget->snapshot, 'totalCents', $items->sum('sellTotalCents')) : $items->sum('sellTotalCents')],
            'versions' => $opportunity->budgets()->orderByDesc('version')->get(['id', 'version', 'status', 'snapshot']),
            'quotes' => SupplierQuote::with('supplier')->where('opportunity_id', $opportunity->id)->get()->map(fn ($q) => ['id' => $q->id, 'label' => $q->supplier->name.' · '.$q->service, 'unitCostCents' => $q->unit_cost_cents, 'validUntil' => $q->valid_until->format('Y-m-d'), 'priceBasis' => $q->price_basis, 'quantity' => $q->quantity, 'unit' => $q->unit]),
            'supplierNeeds' => $opportunity->supplierNeeds()->latest()->get()->map(fn ($need) => ['id' => $need->id, 'category' => $need->category, 'scope' => $need->scope, 'quantity' => $need->quantity, 'unit' => $need->unit, 'requiredDate' => $need->required_date?->format('Y-m-d'), 'status' => $need->status]),
            'approvalEnabled' => (bool) (auth()->user()->can_approve_commercial && config('commercial.rules_approved') && filled(config('commercial.rules_evidence'))),
        ]);
    }

    public function storeItem(Request $request, Opportunity $opportunity, BudgetRevisionService $service)
    {
        $data = $this->itemData($request, $opportunity);
        $service->mutate($opportunity, $request->user(), (int) $request->input('revision'), fn ($budget) => $budget->items()->create($data));

        return back()->with('success', 'Item salvo. A revisão anterior foi invalidada.');
    }

    public function updateItem(Request $request, Opportunity $opportunity, BudgetItem $item, BudgetRevisionService $service)
    {
        abort_unless($item->budget->opportunity_id === $opportunity->id, 404);
        $data = $this->itemData($request, $opportunity);
        $service->mutate($opportunity, $request->user(), (int) $request->input('revision'), function ($budget) use ($item, $data) {
            abort_unless($item->budget_id === $budget->id, 422, 'Edite apenas a versão atual.');
            $item->update($data);
        });

        return back()->with('success', 'Item atualizado. Revise novamente os totais.');
    }

    public function removeItem(Request $request, Opportunity $opportunity, BudgetItem $item, BudgetRevisionService $service)
    {
        abort_unless($item->budget->opportunity_id === $opportunity->id, 404);
        $request->validate(['revision' => ['required', 'integer', 'min:0']]);
        $service->mutate($opportunity, $request->user(), (int) $request->revision, function ($budget) use ($item) {
            abort_unless($item->budget_id === $budget->id, 422);
            $item->delete();
        });

        return back()->with('success', 'Item removido do rascunho.');
    }

    public function approve(Request $request, Opportunity $opportunity, BudgetRevisionService $service)
    {
        $request->validate(['revision' => ['required', 'integer', 'min:0'], 'demo' => ['sometimes', 'boolean']]);
        $demo = $request->boolean('demo');
        $service->review($opportunity, $request->user(), (int) $request->revision, ! $demo);

        return back()->with('success', $demo ? 'Revisão de demonstração salva — não representa aprovação comercial.' : 'Versão aprovada e preservada.');
    }

    public function duplicate(Request $request, Opportunity $opportunity, BudgetRevisionService $service)
    {
        $request->validate(['revision' => ['required', 'integer', 'min:0']]);
        $service->duplicate($opportunity, $request->user(), (int) $request->revision);

        return back()->with('success', 'Nova versão criada com os itens anteriores.');
    }

    public function selectQuote(Request $request, Opportunity $opportunity, SupplierQuote $quote, BudgetIntake $intake)
    {
        abort_unless($quote->opportunity_id === $opportunity->id, 404);
        $request->validate([
            'revision' => ['nullable', 'integer', 'min:0'],
            'request_key' => ['required', 'string', 'max:100'],
        ]);

        $revision = $request->filled('revision')
            ? $request->integer('revision')
            : ($opportunity->budgets()->latest('version')->value('revision') ?? 0);

        $intake->import($opportunity, $request->user(), [
            'revision' => $revision,
            'request_key' => $request->string('request_key')->toString(),
            'quote_id' => $quote->id,
        ]);

        return redirect("/opportunities/{$opportunity->id}/budget?quote_id={$quote->id}")
            ->with('success', 'Cotação selecionada e adicionada ao rascunho. Selecionar não significa contratar.');
    }

    private function itemData(Request $request, Opportunity $case): array
    {
        $data = $request->validate([
            'revision' => ['required', 'integer', 'min:0'], 'category' => ['required', 'string', 'max:80'],
            'description' => ['required', 'string', 'max:180'], 'quantity' => ['required', 'decimal:0,2', 'min:0.01', 'max:10000'],
            'unit' => ['required', 'string', 'max:30'], 'unit_cost' => ['required_without:unit_cost_cents', 'nullable', 'string', 'max:16'],
            'unit_cost_cents' => ['required_without:unit_cost', 'nullable', 'integer', 'min:0', 'max:100000000'],
            'supplier' => ['nullable', 'string', 'max:120'], 'supplier_quote_id' => ['nullable', 'integer'],
            'quote_valid_until' => ['nullable', 'date'], 'management_percent' => ['nullable', 'decimal:0,2', 'min:0', 'max:100'],
            'administration_percent' => ['nullable', 'decimal:0,2', 'min:0', 'max:100'], 'contingency' => ['nullable', 'string', 'max:16'],
        ]);
        $data['unit_cost_cents'] = filled($data['unit_cost'] ?? null) ? Money::decimal($data['unit_cost'], 'unit_cost') : (int) $data['unit_cost_cents'];
        if ($data['unit_cost_cents'] > 100000000) {
            throw ValidationException::withMessages(['unit_cost' => 'O custo unitário deve ser de até R$ 1.000.000,00.']);
        }
        $data['management_bps'] = Money::decimal((string) ($data['management_percent'] ?? '0'));
        $data['administration_bps'] = Money::decimal((string) ($data['administration_percent'] ?? '0'));
        $data['contingency_cents'] = Money::decimal((string) ($data['contingency'] ?? '0'));
        $data['margin_percent'] = 0;
        if ($data['supplier_quote_id'] ?? null) {
            $quote = SupplierQuote::with('supplier')->where('opportunity_id', $case->id)->findOrFail($data['supplier_quote_id']);
            if ($quote->price_basis === 'total' && ((float) $data['quantity'] !== 1.0 || $data['unit'] !== 'pacote')) {
                throw ValidationException::withMessages(['quantity' => 'Esta cotação é um pacote completo. Use quantidade 1 e unidade pacote ou importe pela central de cotações.']);
            }
            $data['supplier'] = $quote->supplier->name;
            $data['unit_cost_cents'] = $quote->unit_cost_cents;
            $data['quote_valid_until'] = $quote->valid_until;
        }
        unset($data['revision'], $data['unit_cost'], $data['management_percent'], $data['administration_percent'], $data['contingency']);

        return $data;
    }
}
