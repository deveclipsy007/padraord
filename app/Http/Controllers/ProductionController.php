<?php

namespace App\Http\Controllers;

use App\Models\Opportunity;
use App\Models\ProductionChecklist;
use App\Models\ProductionChecklistItem;
use App\Models\ProductionServiceOrder;
use App\Models\ProductionTask;
use App\Models\ProductionTaskPreview;
use App\Models\SupplierQuote;
use App\Models\TechnicalValidation;
use App\Models\User;
use App\Services\CaseAttachments;
use App\Services\ProductionOperations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductionController extends Controller
{
    public function show(Opportunity $opportunity, ProductionOperations $operations, CaseAttachments $attachments): Response
    {
        return Inertia::render('Production', [
            'opportunity' => ['id' => $opportunity->id, 'title' => $opportunity->title, 'clientName' => $opportunity->client_name, 'eventDate' => $opportunity->event_date?->format('d/m/Y')],
            'tasks' => $opportunity->productionTasks()->with(['dependency:id,title,status', 'technicalValidation:id,reference,status', 'assignments.user:id,name'])->orderBy('status')->orderBy('scheduled_starts_at')->orderBy('due_date')->get()->map(fn (ProductionTask $task): array => [
                'id' => $task->id, 'title' => $task->title, 'description' => $task->description, 'status' => $task->status, 'priority' => $task->priority, 'phase' => $task->phase, 'dueDate' => $task->due_date?->format('Y-m-d'), 'scheduledStartsAt' => $task->scheduled_starts_at?->toIso8601String(), 'scheduledEndsAt' => $task->scheduled_ends_at?->toIso8601String(), 'assignedTo' => $task->assigned_to, 'dependencyId' => $task->dependency_id, 'dependencyTitle' => $task->dependency?->title, 'blockedReason' => $task->blocked_reason, 'technicalValidationId' => $task->technical_validation_id, 'technicalValidation' => $task->technicalValidation ? ['id' => $task->technicalValidation->id, 'reference' => $task->technicalValidation->reference, 'status' => $task->technicalValidation->status] : null, 'team' => $task->assignments->map(fn ($assignment): array => ['userId' => $assignment->user_id, 'name' => $assignment->user?->name, 'role' => $assignment->role, 'isResponsible' => $assignment->is_responsible])->values(), 'revision' => $task->revision,
            ])->values(),
            'timeline' => $operations->timeline($opportunity),
            'teamMembers' => User::query()->orderBy('name')->get(['id', 'name']),
            'checklists' => ProductionChecklist::query()->where('opportunity_id', $opportunity->id)->with(['task:id,title', 'items.photoAttachment:id,original_name,mime_type'])->latest('id')->get()->map(fn (ProductionChecklist $checklist): array => [
                'id' => $checklist->id, 'taskId' => $checklist->production_task_id, 'taskTitle' => $checklist->task?->title, 'phase' => $checklist->phase, 'title' => $checklist->title, 'status' => $checklist->status, 'revision' => $checklist->revision,
                'items' => $checklist->items->map(fn (ProductionChecklistItem $item): array => [
                    'id' => $item->id, 'title' => $item->title, 'requiresPhoto' => $item->requires_photo, 'completedAt' => $item->completed_at?->toIso8601String(), 'photo' => $item->photoAttachment ? ['id' => $item->photoAttachment->id, 'name' => $item->photoAttachment->original_name, 'mimeType' => $item->photoAttachment->mime_type, 'downloadUrl' => "/opportunities/{$opportunity->id}/attachments/{$item->photoAttachment->id}"] : null,
                ])->values(),
            ])->values(),
            'serviceOrders' => ProductionServiceOrder::query()->where('opportunity_id', $opportunity->id)->with(['supplier:id,name', 'sourceQuote:id,service', 'receiptAttachment:id,original_name,mime_type'])->latest('id')->get()->map(fn (ProductionServiceOrder $order): array => [
                'id' => $order->id, 'code' => $order->code, 'title' => $order->title, 'status' => $order->status, 'amountCents' => $order->amount_cents, 'supplierName' => $order->supplier?->name, 'sourceQuoteId' => $order->source_quote_id, 'scope' => $order->scope, 'issuedAt' => $order->issued_at?->toIso8601String(), 'receivedAt' => $order->received_at?->toIso8601String(), 'receipt' => $order->receiptAttachment ? ['id' => $order->receiptAttachment->id, 'name' => $order->receiptAttachment->original_name, 'mimeType' => $order->receiptAttachment->mime_type, 'downloadUrl' => "/opportunities/{$opportunity->id}/attachments/{$order->receiptAttachment->id}"] : null,
            ])->values(),
            'supplierQuotes' => SupplierQuote::query()->where('opportunity_id', $opportunity->id)->with('supplier:id,name,status')->orderByDesc('id')->get()->filter(fn (SupplierQuote $quote): bool => $quote->supplier?->status === 'active')->map(fn (SupplierQuote $quote): array => [
                'id' => $quote->id, 'supplierId' => $quote->supplier_id, 'supplierName' => $quote->supplier?->name, 'service' => $quote->service, 'validUntil' => $quote->valid_until?->format('Y-m-d'), 'amountCents' => (int) round((int) $quote->unit_cost_cents * (float) $quote->quantity),
            ])->values(),
            'validations' => TechnicalValidation::where('opportunity_id', $opportunity->id)->latest()->get()->map(fn (TechnicalValidation $validation): array => [
                'id' => $validation->id, 'reference' => $validation->reference, 'measurements' => $validation->measurements, 'evidence' => $validation->evidence, 'supplierName' => $validation->supplier_name, 'status' => $validation->status, 'revision' => $validation->revision, 'confirmationEvidence' => $validation->confirmation_evidence,
            ])->values(),
            'scopePreview' => ProductionTaskPreview::where('opportunity_id', $opportunity->id)->latest()->first()?->only(['id', 'source', 'items', 'status', 'result']),
            'attachments' => $attachments->list($opportunity),
            'attachmentLinks' => $attachments->links($opportunity),
            'attachmentQuota' => config('attachments.case_quota_bytes'),
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

    public function scheduleTask(Request $request, ProductionTask $task, ProductionOperations $operations): RedirectResponse
    {
        $operations->scheduleTask($task, $request->user(), $request->all());

        return back()->with('success', 'Agenda e equipe atualizadas; conflitos foram conferidos.');
    }

    public function storeChecklist(Request $request, Opportunity $opportunity, ProductionOperations $operations): RedirectResponse
    {
        $operations->createChecklist($opportunity, $request->user(), $request->all());

        return back()->with('success', 'Checklist de operação criado.');
    }

    public function completeChecklistItem(Request $request, Opportunity $opportunity, ProductionChecklistItem $item, ProductionOperations $operations): RedirectResponse
    {
        $data = $request->validate(['photo_attachment_id' => ['nullable', 'integer', 'exists:attachments,id']]);
        $operations->completeChecklistItem($opportunity, $item, $request->user(), isset($data['photo_attachment_id']) ? (int) $data['photo_attachment_id'] : null);

        return back()->with('success', 'Conferência registrada no checklist.');
    }

    public function storeServiceOrder(Request $request, Opportunity $opportunity, ProductionOperations $operations): RedirectResponse
    {
        $operations->createServiceOrder($opportunity, $request->user(), $request->all());

        return back()->with('success', 'Ordem de serviço gerada como rascunho.');
    }

    public function issueServiceOrder(Request $request, Opportunity $opportunity, ProductionServiceOrder $order, ProductionOperations $operations): RedirectResponse
    {
        $operations->issueServiceOrder($opportunity, $order, $request->user());

        return back()->with('success', 'Ordem de serviço emitida com escopo congelado.');
    }

    public function receiveServiceOrder(Request $request, Opportunity $opportunity, ProductionServiceOrder $order, ProductionOperations $operations): RedirectResponse
    {
        $data = $request->validate(['receipt_attachment_id' => ['required', 'integer', 'exists:attachments,id']]);
        $operations->receiveServiceOrder($opportunity, $order, $request->user(), (int) $data['receipt_attachment_id']);

        return back()->with('success', 'Recebimento do fornecedor registrado.');
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
