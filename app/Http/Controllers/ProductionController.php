<?php

namespace App\Http\Controllers;

use App\Models\Opportunity;
use App\Models\ProductionTask;
use App\Models\ProductionTaskPreview;
use App\Models\TechnicalValidation;
use App\Services\ProductionOperations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductionController extends Controller
{
    public function show(Opportunity $opportunity): Response
    {
        return Inertia::render('Production', [
            'opportunity' => ['id' => $opportunity->id, 'title' => $opportunity->title, 'clientName' => $opportunity->client_name, 'eventDate' => $opportunity->event_date?->format('d/m/Y')],
            'tasks' => $opportunity->productionTasks()->with('dependency:id,title,status', 'technicalValidation:id,reference,status')->orderBy('status')->orderBy('due_date')->get()->map(fn (ProductionTask $task): array => [
                'id' => $task->id, 'title' => $task->title, 'description' => $task->description, 'status' => $task->status, 'priority' => $task->priority, 'phase' => $task->phase, 'dueDate' => $task->due_date?->format('Y-m-d'), 'assignedTo' => $task->assigned_to, 'dependencyId' => $task->dependency_id, 'dependencyTitle' => $task->dependency?->title, 'blockedReason' => $task->blocked_reason, 'technicalValidationId' => $task->technical_validation_id, 'technicalValidation' => $task->technicalValidation ? ['id' => $task->technicalValidation->id, 'reference' => $task->technicalValidation->reference, 'status' => $task->technicalValidation->status] : null, 'revision' => $task->revision,
            ])->values(),
            'validations' => TechnicalValidation::where('opportunity_id', $opportunity->id)->latest()->get()->map(fn (TechnicalValidation $validation): array => [
                'id' => $validation->id, 'reference' => $validation->reference, 'measurements' => $validation->measurements, 'evidence' => $validation->evidence, 'supplierName' => $validation->supplier_name, 'status' => $validation->status, 'revision' => $validation->revision, 'confirmationEvidence' => $validation->confirmation_evidence,
            ])->values(),
            'scopePreview' => ProductionTaskPreview::where('opportunity_id', $opportunity->id)->latest()->first()?->only(['id', 'source', 'items', 'status', 'result']),
        ]);
    }

    public function storeTask(Request $request, Opportunity $opportunity, ProductionOperations $operations): RedirectResponse
    {
        $operations->createTask($opportunity, $request->user(), $request->all());

        return back()->with('success', 'Tarefa criada na produção.');
    }

    public function updateTask(Request $request, ProductionTask $task, ProductionOperations $operations): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', 'in:todo,in_progress,done,blocked'],
            'blocked_reason' => ['nullable', 'string', 'max:5000'],
            'title' => ['sometimes', 'required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'phase' => ['sometimes', 'in:preparation,setup,event,teardown'],
            'priority' => ['sometimes', 'in:low,normal,high'],
            'due_date' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'dependency_id' => ['nullable', 'integer', 'exists:production_tasks,id'],
            'technical_validation_id' => ['nullable', 'integer', 'exists:technical_validations,id'],
            'revision' => ['nullable', 'integer', 'min:0'],
        ]);
        if (array_key_exists('status', $data)) {
            $operations->transition($task, $request->user(), $data['status'], $data['blocked_reason'] ?? null);
        } else {
            $operations->updateTask($task, $request->user(), $data);
        }

        return back()->with('success', 'Status da tarefa atualizado.');
    }

    public function saveTechnicalValidation(Request $request, Opportunity $opportunity, ProductionOperations $operations): RedirectResponse
    {
        $operations->saveTechnicalValidation($opportunity, $request->user(), $request->all());

        return back()->with('success', 'Validação técnica salva. Reconfirme após qualquer alteração.');
    }

    public function confirmTechnicalValidation(Request $request, Opportunity $opportunity, TechnicalValidation $validation, ProductionOperations $operations): RedirectResponse
    {
        abort_unless($validation->opportunity_id === $opportunity->id, 404);
        $data = $request->validate(['evidence' => ['required', 'string', 'min:3', 'max:5000'], 'revision' => ['nullable', 'integer', 'min:0']]);
        $operations->confirmTechnicalValidation($opportunity, $validation, $request->user(), $data['evidence'], isset($data['revision']) ? (int) $data['revision'] : null);

        return back()->with('success', 'Reconfirmação técnica registrada.');
    }

    public function prepareScope(Request $request, Opportunity $opportunity, ProductionOperations $operations): RedirectResponse
    {
        $operations->prepareFromApprovedScope($opportunity, $request->user());

        return back()->with('success', 'Prévia de tarefas preparada a partir do escopo aprovado.');
    }

    public function confirmScope(Request $request, Opportunity $opportunity, ProductionTaskPreview $preview, ProductionOperations $operations): RedirectResponse
    {
        abort_unless($preview->opportunity_id === $opportunity->id, 404);
        $operations->confirmScopePreview($opportunity, $preview, $request->user());

        return back()->with('success', 'Tarefas de produção confirmadas sem duplicação.');
    }
}
