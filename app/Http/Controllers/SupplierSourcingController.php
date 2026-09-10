<?php

namespace App\Http\Controllers;

use App\Models\Opportunity;
use App\Models\SupplierNeed;
use App\Models\SupplierQuote;
use App\Services\SupplierSourcing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SupplierSourcingController extends Controller
{
    public function storeNeed(Request $request, Opportunity $opportunity, SupplierSourcing $sourcing): RedirectResponse
    {
        $sourcing->createNeed($opportunity, $request->user(), $request->all());

        return back()->with('success', 'Necessidade registrada. Nenhuma cotação foi inventada.');
    }

    public function selectQuote(Request $request, Opportunity $opportunity, SupplierNeed $need, SupplierQuote $quote, SupplierSourcing $sourcing): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:3', 'max:5000']]);
        $sourcing->selectQuote($opportunity, $need, $quote, $request->user(), $data['note']);

        return back()->with('success', 'Cotação selecionada para esta necessidade. A contratação continua pendente.');
    }
}
