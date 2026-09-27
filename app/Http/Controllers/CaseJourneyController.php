<?php

namespace App\Http\Controllers;

use App\Models\CaseJourney;
use App\Models\Opportunity;
use App\Services\CaseJourneyService;
use App\Services\CaseWorkspaceSummary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CaseJourneyController extends Controller
{
    public function show(Opportunity $opportunity, CaseWorkspaceSummary $summary)
    {
        $modules = $summary->for($opportunity)['moduleStatuses'];
        $contract = $opportunity->documents()->where('type', 'contract')->latest('version')->first();
        $plan = DB::table('payment_plans')->where('opportunity_id', $opportunity->id)->first();
        array_splice($modules, 5, 0, [
            ['key' => 'contract', 'label' => 'Contrato', 'status' => $contract?->status === 'signed_external' ? 'complete' : ($contract ? 'needs_review' : 'empty'), 'pending' => $contract?->status === 'signed_external' ? 0 : 1],
            ['key' => 'finance', 'label' => 'Financeiro', 'status' => $plan?->status === 'accepted' ? 'approved' : ($plan ? 'draft' : 'empty'), 'pending' => $plan ? DB::table('receivables')->where('opportunity_id', $opportunity->id)->whereNotIn('status', ['paid', 'cancelled'])->count() : 1],
        ]);

        return Inertia::render('CaseJourney', ['opportunity' => $opportunity->only(['id', 'title', 'client_name', 'next_action']), 'journey' => CaseJourney::where('opportunity_id', $opportunity->id)->first(), 'deliverables' => CaseJourneyService::DELIVERABLES, 'moduleStatuses' => $modules]);
    }

    public function update(Request $request, Opportunity $opportunity, CaseJourneyService $service)
    {
        $data = $request->validate(['action' => ['required', Rule::in(['configure', 'contract_viability', 'deliver_viability', 'accept_delivery', 'close_viability', 'contract_management'])], 'revision' => 'required|integer|min:0', 'modality' => 'required_if:action,configure|in:express,complete,strategic', 'mode' => 'required_if:action,configure|in:demo,real', 'evidence' => 'nullable|string|max:5000', 'deliverables' => 'sometimes|array', 'deliverables.*' => ['string', Rule::in(array_keys(CaseJourneyService::DELIVERABLES))]]);
        $service->apply($opportunity, $request->user(), $data);

        return back()->with('success', 'Jornada atualizada com evidência e histórico.');
    }
}
