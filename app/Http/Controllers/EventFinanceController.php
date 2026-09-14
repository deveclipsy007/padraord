<?php

namespace App\Http\Controllers;

use App\Enums\Ability;
use App\Models\Opportunity;
use App\Services\EventFinance;
use App\Services\EventProfitability;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class EventFinanceController extends Controller
{
    public function show(Request $request, Opportunity $opportunity, EventFinance $finance)
    {
        $p = DB::table('receivable_previews')->where('opportunity_id', $opportunity->id)->where('created_by', $request->user()->id)->whereNull('confirmed_at')->latest('id')->first();
        $origins = [];
        foreach ($opportunity->budgets()->where('status', 'approved')->latest('version')->take(1)->with('items')->get() as $b) {
            foreach ($b->items as $i) {
                $origins[] = ['key' => 'budget_item:'.$i->id, 'label' => $i->description.' · orçamento v'.$b->version];
            }
        }
        foreach (DB::table('supplier_quote_selections')->join('supplier_quotes', 'supplier_quotes.id', '=', 'supplier_quote_selections.supplier_quote_id')->where('supplier_quote_selections.opportunity_id', $opportunity->id)->select('supplier_quotes.id', 'supplier_quotes.service')->distinct()->get() as $q) {
            $origins[] = ['key' => 'quote:'.$q->id, 'label' => $q->service.' · cotação selecionada'];
        }

        return Inertia::render('EventFinance', ['opportunity' => ['id' => $opportunity->id, 'title' => $opportunity->title, 'clientName' => $opportunity->client_name], 'plan' => $finance->plan($opportunity), 'receivables' => $finance->ledger($opportunity, 'receivables'), 'payables' => $finance->ledger($opportunity, 'payables'), 'profitability' => app(EventProfitability::class)->for($opportunity), 'preview' => $p ? [...(array) $p, 'items' => json_decode($p->items, true)] : null, 'origins' => $origins, 'canApprove' => Gate::allows(Ability::ApproveCommercial->value), 'settlements' => DB::table('financial_settlements')->where('opportunity_id', $opportunity->id)->latest('id')->get()]);
    }

    public function plan(Request $r, Opportunity $opportunity, EventFinance $s)
    {
        $s->savePlan($opportunity, $r->user(), $r->all());

        return back()->with('success', 'Plano salvo; parcelas conferidas ao centavo.');
    }

    public function accept(Request $r, Opportunity $opportunity, int $plan, EventFinance $s)
    {
        $s->acceptPlan($opportunity, $r->user(), $plan, $r->all());

        return back()->with('success', 'Aceite registrado e condições preservadas.');
    }

    public function preview(Request $r, Opportunity $opportunity, int $plan, EventFinance $s)
    {
        $s->previewReceivables($opportunity, $r->user(), $plan);

        return back();
    }

    public function confirm(Request $r, Opportunity $opportunity, int $preview, EventFinance $s)
    {
        $s->confirmReceivables($opportunity, $r->user(), $preview);

        return back()->with('success', 'Recebíveis gerados.');
    }

    public function payable(Request $r, Opportunity $opportunity, EventFinance $s)
    {
        $s->createPayable($opportunity, $r->user(), $r->all());

        return back()->with('success', 'Pagável registrado para aprovação.');
    }

    public function approve(Request $r, Opportunity $opportunity, int $entry, EventFinance $s)
    {
        $v = $r->validate(['revision' => 'required|integer|min:0']);
        $s->approvePayable($opportunity, $r->user(), $entry, $v['revision']);

        return back()->with('success', 'Pagamento aprovado.');
    }

    public function settle(Request $r, Opportunity $opportunity, string $ledger, int $entry, EventFinance $s)
    {
        $s->settle($opportunity, $r->user(), $ledger, $entry, $r->all());

        return back()->with('success', 'Baixa registrada; saldo atualizado.');
    }

    public function cancel(Request $r, Opportunity $opportunity, string $ledger, int $entry, EventFinance $s)
    {
        $s->cancel($opportunity, $r->user(), $ledger, $entry, $r->all());

        return back()->with('success', 'Lançamento cancelado; histórico preservado.');
    }

    public function due(Request $r, Opportunity $opportunity, int $entry, EventFinance $s)
    {
        $s->confirmDue($opportunity, $r->user(), $entry, $r->all());

        return back()->with('success', 'Vencimento confirmado com evidência.');
    }

    public function cost(Request $r, Opportunity $opportunity, EventFinance $s)
    {
        $s->saveCost($opportunity, $r->user(), $r->all());

        return back()->with('success', 'Conferência por categoria salva.');
    }
}
