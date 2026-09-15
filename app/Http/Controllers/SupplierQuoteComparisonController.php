<?php

namespace App\Http\Controllers;

use App\Models\Opportunity;
use App\Models\SupplierQuoteComparison;
use App\Services\SupplierQuoteComparisons;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SupplierQuoteComparisonController extends Controller
{
    public function store(Request $request, Opportunity $opportunity, SupplierQuoteComparisons $comparisons): RedirectResponse
    {
        $comparison = $comparisons->create($opportunity, $request->user(), $request->all());

        return back()->with('success', $comparison->status === 'decided' ? 'Comparação e decisão registradas; cotações originais foram preservadas.' : 'Comparação registrada para revisão.');
    }

    public function export(Opportunity $opportunity, SupplierQuoteComparison $comparison, SupplierQuoteComparisons $comparisons): JsonResponse
    {
        abort_unless($comparison->opportunity_id === $opportunity->id, 404);

        return response()->json($comparisons->export($comparison), 200, [
            'Content-Disposition' => 'attachment; filename="comparacao-de-cotacoes-'.$comparison->id.'.json"',
        ]);
    }
}
