<?php

namespace App\Http\Controllers;

use App\Models\CaseJourney;
use App\Models\Opportunity;
use App\Models\ViabilityDeliverable;
use App\Models\ViabilityProject;
use App\Services\ViabilityLifecycleService;
use App\Services\ViabilityWorkspaceService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FeasibilityController extends Controller
{
    public function show(Opportunity $opportunity): Response
    {
        $project = ViabilityProject::with('deliverables')->where('opportunity_id', $opportunity->id)->first();
        $journey = CaseJourney::where('opportunity_id', $opportunity->id)->first();

        return Inertia::render('Feasibility', [
            'opportunity' => ['id' => $opportunity->id, 'title' => $opportunity->title, 'clientName' => $opportunity->client_name],
            'project' => $project ? [
                ...$project->only(['revision', 'modality', 'status', 'concept', 'experience', 'technical_assumptions', 'estimate_notes', 'supplier_needs', 'schedule_notes', 'references']),
                'deliverables' => $project->deliverables->map(fn ($item) => $item->only(['id', 'key', 'title', 'status', 'required', 'content', 'evidence']))->values(),
            ] : null,
            'journey' => $journey?->only(['cycle', 'viability_status', 'management_status', 'outcome']),
            'deliverableOptions' => ViabilityWorkspaceService::DELIVERABLES,
        ]);
    }

    public function store(Request $request, Opportunity $opportunity, ViabilityWorkspaceService $service)
    {
        $data = $request->validate([
            'revision' => 'required|integer|min:0', 'modality' => ['required', Rule::in(['express', 'complete'])],
            'concept' => 'nullable|string|max:10000', 'experience' => 'nullable|string|max:10000',
            'technical_assumptions' => 'nullable|string|max:10000', 'estimate_notes' => 'nullable|string|max:10000',
            'supplier_needs' => 'nullable|string|max:10000', 'schedule_notes' => 'nullable|string|max:10000',
            'references' => 'nullable|string|max:10000', 'deliverables' => 'array',
            'deliverables.*' => ['string', Rule::in(array_keys(ViabilityWorkspaceService::DELIVERABLES))],
        ]);
        $service->save($opportunity, $request->user(), $data);

        return back()->with('success', 'Rascunho da Viabilidade salvo e versionado.');
    }

    public function lifecycle(Request $request, Opportunity $opportunity, ViabilityLifecycleService $service)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(ViabilityLifecycleService::STATUSES)],
            'note' => 'nullable|string|max:5000',
        ]);
        $project = ViabilityProject::where('opportunity_id', $opportunity->id)->firstOrFail();
        $service->transition($project, $request->user(), $data['status'], $data);

        return back()->with('success', 'Estado da Viabilidade atualizado.');
    }

    public function updateDeliverable(Request $request, Opportunity $opportunity, ViabilityDeliverable $deliverable, ViabilityWorkspaceService $service)
    {
        abort_unless($deliverable->opportunity_id === $opportunity->id, 404);
        $data = $request->validate([
            'status' => ['required', Rule::in(['pending', 'draft', 'ready', 'delivered'])],
            'content' => 'nullable|string|max:10000',
            'evidence' => 'nullable|string|max:5000',
        ]);
        $service->updateDeliverable($deliverable, $request->user(), $data);

        return back()->with('success', 'Entregável atualizado.');
    }
}
