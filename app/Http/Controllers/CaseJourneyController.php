<?php

namespace App\Http\Controllers;

use App\Models\CaseJourney;
use App\Models\Opportunity;
use App\Services\CaseJourneyService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CaseJourneyController extends Controller
{
    public function show(Opportunity $opportunity)
    {
        return Inertia::render('CaseJourney', ['opportunity' => $opportunity->only(['id', 'title', 'client_name']), 'journey' => CaseJourney::where('opportunity_id', $opportunity->id)->first(), 'deliverables' => CaseJourneyService::DELIVERABLES]);
    }

    public function update(Request $request, Opportunity $opportunity, CaseJourneyService $service)
    {
        $data = $request->validate(['action' => ['required', Rule::in(['configure', 'contract_viability', 'deliver_viability', 'accept_delivery', 'close_viability', 'contract_management'])], 'revision' => 'required|integer|min:0', 'modality' => 'required_if:action,configure|in:express,complete,strategic', 'mode' => 'required_if:action,configure|in:demo,real', 'evidence' => 'nullable|string|max:5000', 'deliverables' => 'sometimes|array', 'deliverables.*' => ['string', Rule::in(array_keys(CaseJourneyService::DELIVERABLES))]]);
        $service->apply($opportunity, $request->user(), $data);

        return back()->with('success', 'Jornada atualizada com evidência e histórico.');
    }
}
