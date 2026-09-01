<?php

namespace App\Http\Controllers;

use App\Models\Opportunity;
use App\Models\PrototypeCase;
use App\Services\CaseJourneyService;
use App\Services\PrototypeFlow;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PrototypeController extends Controller
{
    public function index()
    {
        return Inertia::render('Projects', ['cases' => PrototypeCase::latest()->get()->map(fn ($case) => ['id' => $case->id, 'title' => $case->title, 'revision' => $case->revision, 'owner' => $case->state['owner'], 'viability' => $case->state['viability'], 'management' => $case->state['management'], 'outcome' => $case->state['outcome'], 'next' => $case->state['next']]), 'opportunities' => Opportunity::with('owner')->latest()->get()->map(fn ($case) => ['id' => $case->id, 'title' => $case->title, 'client' => $case->client_name, 'owner' => $case->owner?->name, 'stage' => $case->stage->label(), 'next' => $case->next_action])]);
    }

    public function store(Request $request)
    {
        $case = PrototypeCase::create(['created_by' => $request->user()->id, 'title' => 'Conferência Horizonte 2026', 'mode' => 'demo', 'revision' => 0, 'state' => PrototypeFlow::initial($request->user())]);

        return redirect('/prototype/'.$case->id.'/overview')->with('success', 'Novo cenário fictício criado. Nenhum caso real foi alterado.');
    }

    public function show(PrototypeCase $prototype, string $section)
    {
        abort_unless(in_array($section, PrototypeFlow::SECTIONS, true), 404);

        return Inertia::render('PrototypeWorkspace', ['record' => $prototype, 'section' => $section]);
    }

    public function action(Request $request, PrototypeCase $prototype, PrototypeFlow $flow)
    {
        $request->validate(['action' => 'required|string|max:40']);
        $action = (string) $request->input('action');
        $required = match ($action) {
            'case_save' => ['client', 'owner', 'origin', 'priority', 'due', 'next'],
            'stage' => ['stage'], 'message' => ['text'], 'briefing_save' => ['briefing'],
            'suggestion' => ['id', 'decision'], 'quote' => ['supplier', 'description', 'cost', 'valid_until', 'evidence'],
            'item' => ['description', 'cost', 'quantity'], 'item_remove', 'document_review', 'document_send', 'document_accept', 'task_done' => ['id'],
            'document' => ['document_type', 'purpose', 'text'], 'technical_save' => ['description', 'quantity'],
            'task' => ['text', 'owner', 'due', 'phase'], 'occurrence' => ['text', 'solution'],
            'post_save' => ['text', 'learning', 'cost', 'rating'], default => [],
        };
        $rules = ['action' => 'required|string|max:40', 'revision' => 'required|integer|min:0', 'id' => 'sometimes|integer|min:1', 'quantity' => 'sometimes|numeric|min:0.01|max:10000', 'management_percent' => 'sometimes|decimal:0,2|min:0|max:100', 'administration_percent' => 'sometimes|decimal:0,2|min:0|max:100', 'dependency' => 'nullable|integer|min:0', 'rating' => 'sometimes|integer|min:1|max:5', 'modality' => 'sometimes|in:express,complete', 'priority' => 'sometimes|in:low,normal,high', 'decision' => 'sometimes|in:accepted,rejected', 'document_type' => 'sometimes|in:proposal,contract', 'purpose' => 'sometimes|in:viability,management', 'phase' => 'sometimes|in:preparation,setup,event,teardown', 'due' => 'sometimes|date_format:Y-m-d', 'valid_until' => 'sometimes|date_format:Y-m-d', 'deliverables' => 'sometimes|array', 'deliverables.*' => ['string', Rule::in(array_keys(CaseJourneyService::DELIVERABLES))], 'briefing' => ['sometimes', 'array:'.implode(',', PrototypeFlow::FIELDS)], 'briefing.*' => 'nullable|string|max:5000', 'quote_id' => 'nullable|integer|min:1'];
        foreach (['client', 'owner', 'origin', 'next', 'stage', 'supplier', 'description', 'text', 'conditions', 'evidence', 'cost', 'solution', 'learning'] as $field) {
            $rules[$field] = 'sometimes|nullable|string|max:5000';
        }
        foreach ($required as $field) {
            $existing = is_array($rules[$field]) ? $rules[$field] : explode('|', $rules[$field]);
            $rules[$field] = [...array_filter($existing, fn ($rule) => ! in_array($rule, ['sometimes', 'nullable'], true)), 'required'];
        }
        $flow->apply($prototype, $request->user(), $request->validate($rules));

        return back()->with('success', 'Demonstração salva. Nenhuma mensagem ou contratação externa foi realizada.');
    }

    public function print(PrototypeCase $prototype, int $document)
    {
        $snapshot = collect($prototype->state['documents'])->firstWhere('id', $document);
        abort_unless($snapshot, 404);

        return response()->view('prototype-document', ['case' => $prototype, 'document' => $snapshot])->header('Cache-Control', 'private, no-store');
    }
}
