<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Attachment;
use App\Models\BriefRequirement;
use App\Models\EventBrief;
use App\Models\Opportunity;
use App\Models\ProductionChecklist;
use App\Models\ProductionChecklistItem;
use App\Models\ProductionServiceOrder;
use App\Models\ProductionTask;
use App\Models\ProductionTaskAssignment;
use App\Models\ProductionTaskPreview;
use App\Models\Supplier;
use App\Models\SupplierQuote;
use App\Models\TechnicalValidation;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ProductionOperations
{
    public const PHASES = ['preparation', 'setup', 'event', 'teardown'];

    public const STATUSES = ['todo', 'in_progress', 'done', 'blocked'];

    public const CHECKLIST_PHASES = ['setup', 'teardown'];

    /** @param array<string, mixed> $data */
    public function createTask(Opportunity $opportunity, User $actor, array $data): ProductionTask
    {
        $data = Validator::make($data, [
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'phase' => ['nullable', 'in:'.implode(',', self::PHASES)],
            'priority' => ['nullable', 'in:low,normal,high'],
            'due_date' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'dependency_id' => ['nullable', 'integer', 'exists:production_tasks,id'],
            'technical_validation_id' => ['nullable', 'integer', 'exists:technical_validations,id'],
            'scheduled_starts_at' => ['nullable', 'date'],
            'scheduled_ends_at' => ['nullable', 'date', 'after:scheduled_starts_at'],
            'team' => ['nullable', 'array', 'min:1'],
            'team.*.user_id' => ['required', 'integer', 'distinct', 'exists:users,id'],
            'team.*.role' => ['nullable', 'string', 'max:120'],
            'team.*.is_responsible' => ['nullable', 'boolean'],
        ])->validate();

        return DB::transaction(function () use ($opportunity, $actor, $data): ProductionTask {
            $dependency = null;
            if (! empty($data['dependency_id'])) {
                $dependency = ProductionTask::query()->whereKey($data['dependency_id'])->lockForUpdate()->first();
                $this->require($dependency !== null && $dependency->opportunity_id === $opportunity->id, 'A dependência precisa pertencer ao mesmo caso.', 'dependency_id');
                $this->require($dependency->id !== ($data['id'] ?? null), 'Uma tarefa não pode depender dela mesma.', 'dependency_id');
                $this->require(! $this->dependencyReaches($dependency, null), 'A dependência contém um ciclo existente.', 'dependency_id');
            }
            $validation = null;
            if (! empty($data['technical_validation_id'])) {
                $validation = TechnicalValidation::query()->whereKey($data['technical_validation_id'])->lockForUpdate()->first();
                $this->require($validation !== null && $validation->opportunity_id === $opportunity->id, 'A validação técnica precisa pertencer ao mesmo caso.', 'technical_validation_id');
            }

            $this->requireSchedulePair($data['scheduled_starts_at'] ?? null, $data['scheduled_ends_at'] ?? null);
            $team = $this->normaliseTeam($data['team'] ?? [], $data['assigned_to'] ?? $actor->id);
            if (! empty($data['scheduled_starts_at'])) {
                $this->assertDependencyWindow($dependency, $data['scheduled_starts_at']);
                $this->assertTeamAvailable($team, $data['scheduled_starts_at'], $data['scheduled_ends_at'], null);
            }

            $sortOrder = ((int) $opportunity->productionTasks()->max('sort_order')) + 1;
            $task = $opportunity->productionTasks()->create([
                'assigned_to' => $this->responsibleUserId($team),
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'phase' => $data['phase'] ?? 'preparation',
                'status' => 'todo',
                'priority' => $data['priority'] ?? 'normal',
                'due_date' => $data['due_date'] ?? null,
                'dependency_id' => $dependency?->id,
                'technical_validation_id' => $validation?->id,
                'scheduled_starts_at' => $data['scheduled_starts_at'] ?? null,
                'scheduled_ends_at' => $data['scheduled_ends_at'] ?? null,
                'sort_order' => $sortOrder,
            ]);
            $this->syncTeam($task, $team);

            AuditLog::create([
                'user_id' => $actor->id,
                'action' => 'production.task_created',
                'subject_type' => ProductionTask::class,
                'subject_id' => $task->id,
                'metadata' => [
                    'opportunity_id' => $opportunity->id,
                    'phase' => $task->phase,
                    'priority' => $task->priority,
                    'dependency_id' => $task->dependency_id,
                    'scheduled_starts_at' => $task->scheduled_starts_at?->toIso8601String(),
                    'scheduled_ends_at' => $task->scheduled_ends_at?->toIso8601String(),
                    'team_user_ids' => array_column($team, 'user_id'),
                ],
            ]);

            return $task;
        });
    }

    public function transition(ProductionTask $task, User $actor, string $status, ?string $reason = null): ProductionTask
    {
        $this->require(in_array($status, self::STATUSES, true), 'Status de tarefa inválido.', 'status');

        return DB::transaction(function () use ($task, $actor, $status, $reason): ProductionTask {
            $locked = ProductionTask::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();
            $this->require($status !== 'blocked' || filled(trim((string) $reason)), 'Explique o bloqueio antes de salvar.', 'blocked_reason');

            if ($status === 'done' && $locked->dependency_id) {
                $dependency = ProductionTask::query()->whereKey($locked->dependency_id)->first();
                $this->require($dependency !== null && $dependency->opportunity_id === $locked->opportunity_id, 'A dependência precisa pertencer ao mesmo caso.', 'status');
                $dependencyStatus = $dependency->status;
                $this->require($dependencyStatus === 'done', 'Conclua a tarefa anterior antes de finalizar esta tarefa.', 'status');
            }

            if ($status === 'done' && $locked->technical_validation_id) {
                $validation = TechnicalValidation::query()->whereKey($locked->technical_validation_id)->first();
                $this->require($validation !== null && $validation->opportunity_id === $locked->opportunity_id && $validation->status === 'confirmed', 'Reconfirme a validação técnica antes de liberar esta tarefa.', 'technical_validation');
            }

            $before = $locked->status;
            $reopenedDependents = $before === 'done' && $status !== 'done' ? $locked->dependents()->pluck('id')->all() : [];
            $locked->status = $status;
            $locked->blocked_reason = $status === 'blocked' ? trim((string) $reason) : null;
            $locked->started_at = in_array($status, ['in_progress', 'done'], true) ? ($locked->started_at ?? now()) : $locked->started_at;
            $locked->completed_at = $status === 'done' ? ($locked->completed_at ?? now()) : null;
            $locked->revision = (int) $locked->revision + 1;
            $locked->save();

            AuditLog::create([
                'user_id' => $actor->id,
                'action' => 'production.task_status_changed',
                'subject_type' => ProductionTask::class,
                'subject_id' => $locked->id,
                'metadata' => [
                    'opportunity_id' => $locked->opportunity_id,
                    'before' => $before,
                    'after' => $status,
                    'reason' => $locked->blocked_reason,
                ],
            ]);
            if ($reopenedDependents !== []) {
                AuditLog::create([
                    'user_id' => $actor->id,
                    'action' => 'production.task_reopened',
                    'subject_type' => ProductionTask::class,
                    'subject_id' => $locked->id,
                    'metadata' => ['opportunity_id' => $locked->opportunity_id, 'dependent_task_ids' => $reopenedDependents, 'reason' => 'Tarefa concluída reaberta; dependências podem exigir retrabalho.'],
                ]);
            }

            return $locked;
        });
    }

    /** @param array<string, mixed> $data */
    public function updateTask(ProductionTask $task, User $actor, array $data): ProductionTask
    {
        $validated = Validator::make($data, [
            'title' => ['sometimes', 'required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'phase' => ['sometimes', 'in:'.implode(',', self::PHASES)],
            'priority' => ['sometimes', 'in:low,normal,high'],
            'due_date' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'dependency_id' => ['nullable', 'integer', 'exists:production_tasks,id'],
            'technical_validation_id' => ['nullable', 'integer', 'exists:technical_validations,id'],
            'revision' => ['nullable', 'integer', 'min:0'],
        ])->validate();

        return DB::transaction(function () use ($task, $actor, $validated): ProductionTask {
            $locked = ProductionTask::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();
            if (array_key_exists('revision', $validated) && (int) $validated['revision'] !== (int) $locked->revision) {
                throw ValidationException::withMessages(['revision' => 'A tarefa mudou. Atualize antes de salvar.']);
            }
            if (array_key_exists('dependency_id', $validated) && $validated['dependency_id']) {
                $dependency = ProductionTask::query()->whereKey($validated['dependency_id'])->lockForUpdate()->first();
                $this->require($dependency !== null && $dependency->opportunity_id === $locked->opportunity_id, 'A dependência precisa pertencer ao mesmo caso.', 'dependency_id');
                $this->require($dependency->id !== $locked->id && ! $this->dependencyReaches($dependency, $locked->id), 'A dependência criaria um ciclo.', 'dependency_id');
            }
            if (array_key_exists('technical_validation_id', $validated) && $validated['technical_validation_id']) {
                $validation = TechnicalValidation::query()->whereKey($validated['technical_validation_id'])->first();
                $this->require($validation !== null && $validation->opportunity_id === $locked->opportunity_id, 'A validação técnica precisa pertencer ao mesmo caso.', 'technical_validation_id');
            }
            $before = $locked->only(['title', 'description', 'phase', 'priority', 'due_date', 'assigned_to', 'dependency_id', 'technical_validation_id']);
            $locked->fill(collect($validated)->except(['revision'])->all());
            $locked->revision = (int) $locked->revision + 1;
            $locked->save();
            AuditLog::create([
                'user_id' => $actor->id,
                'action' => 'production.task_updated',
                'subject_type' => ProductionTask::class,
                'subject_id' => $locked->id,
                'metadata' => ['opportunity_id' => $locked->opportunity_id, 'before' => $before, 'after' => $locked->only(array_keys($before))],
            ]);

            return $locked->fresh();
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function scheduleTask(ProductionTask $task, User $actor, array $data): ProductionTask
    {
        $data = Validator::make($data, [
            'scheduled_starts_at' => ['required', 'date'],
            'scheduled_ends_at' => ['required', 'date', 'after:scheduled_starts_at'],
            'team' => ['nullable', 'array', 'min:1'],
            'team.*.user_id' => ['required', 'integer', 'distinct', 'exists:users,id'],
            'team.*.role' => ['nullable', 'string', 'max:120'],
            'team.*.is_responsible' => ['nullable', 'boolean'],
            'revision' => ['nullable', 'integer', 'min:0'],
        ])->validate();

        return DB::transaction(function () use ($task, $actor, $data): ProductionTask {
            $locked = ProductionTask::query()->with('assignments')->whereKey($task->id)->lockForUpdate()->firstOrFail();
            if (array_key_exists('revision', $data) && (int) $data['revision'] !== (int) $locked->revision) {
                throw ValidationException::withMessages(['revision' => 'A tarefa mudou. Atualize antes de salvar.']);
            }

            $dependency = $locked->dependency_id
                ? ProductionTask::query()->whereKey($locked->dependency_id)->lockForUpdate()->first()
                : null;
            $this->assertDependencyWindow($dependency, $data['scheduled_starts_at']);
            $team = $this->normaliseTeam(
                $data['team'] ?? $this->teamPayloadFromTask($locked),
                $locked->assigned_to ?? $actor->id,
            );
            $this->assertTeamAvailable($team, $data['scheduled_starts_at'], $data['scheduled_ends_at'], $locked->id);

            $before = $locked->only(['scheduled_starts_at', 'scheduled_ends_at', 'assigned_to']);
            $locked->update([
                'scheduled_starts_at' => $data['scheduled_starts_at'],
                'scheduled_ends_at' => $data['scheduled_ends_at'],
                'assigned_to' => $this->responsibleUserId($team),
                'revision' => (int) $locked->revision + 1,
            ]);
            $this->syncTeam($locked, $team);

            AuditLog::create([
                'user_id' => $actor->id,
                'action' => 'production.task_scheduled',
                'subject_type' => ProductionTask::class,
                'subject_id' => $locked->id,
                'metadata' => [
                    'opportunity_id' => $locked->opportunity_id,
                    'before' => $before,
                    'scheduled_starts_at' => $locked->scheduled_starts_at?->toIso8601String(),
                    'scheduled_ends_at' => $locked->scheduled_ends_at?->toIso8601String(),
                    'team_user_ids' => array_column($team, 'user_id'),
                ],
            ]);

            return $locked->fresh(['assignments.user']);
        }, 3);
    }

    /** @return array<int, array<string, mixed>> */
    public function timeline(Opportunity $opportunity): array
    {
        return $opportunity->productionTasks()
            ->with(['dependency:id,title', 'assignments.user:id,name'])
            ->whereNotNull('scheduled_starts_at')
            ->whereNotNull('scheduled_ends_at')
            ->orderBy('scheduled_starts_at')
            ->orderBy('scheduled_ends_at')
            ->orderBy('id')
            ->get()
            ->map(fn (ProductionTask $task): array => [
                'id' => $task->id,
                'title' => $task->title,
                'phase' => $task->phase,
                'status' => $task->status,
                'dependency_id' => $task->dependency_id,
                'dependency_title' => $task->dependency?->title,
                'scheduled_starts_at' => $task->scheduled_starts_at?->toIso8601String(),
                'scheduled_ends_at' => $task->scheduled_ends_at?->toIso8601String(),
                'team' => $task->assignments->map(fn (ProductionTaskAssignment $assignment): array => [
                    'user_id' => $assignment->user_id,
                    'name' => $assignment->user?->name,
                    'role' => $assignment->role,
                    'is_responsible' => $assignment->is_responsible,
                ])->values()->all(),
            ])->all();
    }

    /** @param array<string, mixed> $data */
    public function createChecklist(Opportunity $opportunity, User $actor, array $data): ProductionChecklist
    {
        $data = Validator::make($data, [
            'task_id' => ['nullable', 'integer', 'exists:production_tasks,id'],
            'phase' => ['required', 'in:'.implode(',', self::CHECKLIST_PHASES)],
            'title' => ['required', 'string', 'max:180'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.title' => ['required', 'string', 'max:240'],
            'items.*.requires_photo' => ['nullable', 'boolean'],
        ])->validate();

        return DB::transaction(function () use ($opportunity, $actor, $data): ProductionChecklist {
            $task = null;
            if (! empty($data['task_id'])) {
                $task = ProductionTask::query()->whereKey($data['task_id'])->lockForUpdate()->first();
                $this->require($task !== null && $task->opportunity_id === $opportunity->id, 'A tarefa precisa pertencer ao mesmo caso.', 'task_id');
                $this->require($task->phase === $data['phase'], 'O checklist precisa usar a mesma fase da tarefa.', 'phase');
            }

            $checklist = ProductionChecklist::create([
                'opportunity_id' => $opportunity->id,
                'production_task_id' => $task?->id,
                'phase' => $data['phase'],
                'title' => $data['title'],
                'status' => 'open',
                'revision' => 0,
                'created_by' => $actor->id,
            ]);
            foreach ($data['items'] as $index => $item) {
                $checklist->items()->create([
                    'title' => $item['title'],
                    'requires_photo' => (bool) ($item['requires_photo'] ?? false),
                    'sort_order' => $index,
                ]);
            }
            AuditLog::create([
                'user_id' => $actor->id,
                'action' => 'production.checklist_created',
                'subject_type' => ProductionChecklist::class,
                'subject_id' => $checklist->id,
                'metadata' => ['opportunity_id' => $opportunity->id, 'phase' => $checklist->phase, 'item_count' => count($data['items'])],
            ]);

            return $checklist->fresh('items');
        }, 3);
    }

    public function completeChecklistItem(Opportunity $opportunity, ProductionChecklistItem $item, User $actor, ?int $photoAttachmentId = null): ProductionChecklistItem
    {
        return DB::transaction(function () use ($opportunity, $item, $actor, $photoAttachmentId): ProductionChecklistItem {
            $locked = ProductionChecklistItem::query()->with('checklist')->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $checklist = ProductionChecklist::query()->whereKey($locked->production_checklist_id)->lockForUpdate()->firstOrFail();
            $this->require($checklist->opportunity_id === $opportunity->id, 'O item não pertence a este caso.', 'item');

            $attachment = null;
            if ($photoAttachmentId !== null) {
                $attachment = Attachment::query()->whereKey($photoAttachmentId)->lockForUpdate()->first();
                $this->require(
                    $attachment !== null
                    && $attachment->opportunity_id === $opportunity->id
                    && $attachment->archived_at === null
                    && str_starts_with((string) $attachment->mime_type, 'image/'),
                    'Use uma foto privada, ativa e vinculada a este caso como evidência.',
                    'photo_attachment_id',
                );
            }
            $this->require(! $locked->requires_photo || $attachment !== null, 'Este item exige uma foto antes da conclusão.', 'photo_attachment_id');
            if ($locked->completed_at !== null) {
                $this->require(
                    $locked->photo_attachment_id === $attachment?->id,
                    'A conferência já foi registrada. Reabra o checklist antes de trocar a evidência.',
                    'item',
                );

                return $locked;
            }

            $locked->update([
                'completed_by' => $actor->id,
                'completed_at' => now(),
                'photo_attachment_id' => $attachment?->id,
            ]);
            $allComplete = ! ProductionChecklistItem::query()
                ->where('production_checklist_id', $checklist->id)
                ->whereNull('completed_at')
                ->exists();
            $checklist->update([
                'status' => $allComplete ? 'completed' : 'open',
                'completed_by' => $allComplete ? $actor->id : null,
                'completed_at' => $allComplete ? now() : null,
                'revision' => (int) $checklist->revision + 1,
            ]);
            AuditLog::create([
                'user_id' => $actor->id,
                'action' => 'production.checklist_item_completed',
                'subject_type' => ProductionChecklistItem::class,
                'subject_id' => $locked->id,
                'metadata' => ['opportunity_id' => $opportunity->id, 'checklist_id' => $checklist->id, 'photo_attachment_id' => $attachment?->id],
            ]);

            return $locked->fresh();
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function createServiceOrder(Opportunity $opportunity, User $actor, array $data): ProductionServiceOrder
    {
        $data = Validator::make($data, [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'source_quote_id' => ['required', 'integer', 'exists:supplier_quotes,id'],
            'task_ids' => ['required', 'array', 'min:1'],
            'task_ids.*' => ['required', 'integer', 'distinct', 'exists:production_tasks,id'],
            'title' => ['required', 'string', 'max:180'],
        ])->validate();

        return DB::transaction(function () use ($opportunity, $actor, $data): ProductionServiceOrder {
            $supplier = Supplier::query()->whereKey($data['supplier_id'])->lockForUpdate()->first();
            $this->require($supplier !== null && $supplier->status === 'active', 'Selecione um fornecedor ativo.', 'supplier_id');
            $quote = SupplierQuote::query()->whereKey($data['source_quote_id'])->lockForUpdate()->first();
            $this->require(
                $quote !== null
                && $quote->supplier_id === $supplier->id
                && $quote->opportunity_id === $opportunity->id
                && $quote->valid_until?->greaterThanOrEqualTo(today()),
                'Use uma cotação vigente deste fornecedor e deste caso.',
                'source_quote_id',
            );
            $tasks = ProductionTask::query()->whereIn('id', $data['task_ids'])->lockForUpdate()->get();
            $this->require($tasks->count() === count($data['task_ids']) && $tasks->every(fn (ProductionTask $task): bool => $task->opportunity_id === $opportunity->id), 'As tarefas precisam pertencer ao mesmo caso.', 'task_ids');

            $scope = [
                'opportunity' => [
                    'id' => $opportunity->id,
                    'title' => $opportunity->title,
                    'event_date' => $opportunity->event_date?->toDateString(),
                ],
                'supplier' => ['id' => $supplier->id, 'name' => $supplier->name, 'service' => $supplier->service],
                'quote' => [
                    'id' => $quote->id,
                    'service' => $quote->service,
                    'unit_cost_cents' => $quote->unit_cost_cents,
                    'quantity' => $quote->quantity,
                    'unit' => $quote->unit,
                    'conditions' => $quote->conditions,
                    'valid_until' => $quote->valid_until?->toDateString(),
                ],
                'tasks' => $tasks->sortBy(fn (ProductionTask $task): int => array_search($task->id, $data['task_ids'], true))
                    ->map(fn (ProductionTask $task): array => [
                        'id' => $task->id,
                        'title' => $task->title,
                        'description' => $task->description,
                        'phase' => $task->phase,
                        'scheduled_starts_at' => $task->scheduled_starts_at?->toIso8601String(),
                        'scheduled_ends_at' => $task->scheduled_ends_at?->toIso8601String(),
                    ])->values()->all(),
            ];
            $scopeHash = hash('sha256', json_encode($scope, JSON_THROW_ON_ERROR));
            $order = ProductionServiceOrder::create([
                'opportunity_id' => $opportunity->id,
                'supplier_id' => $supplier->id,
                'source_quote_id' => $quote->id,
                'code' => $this->newServiceOrderCode(),
                'title' => $data['title'],
                'amount_cents' => (int) round((int) $quote->unit_cost_cents * (float) $quote->quantity),
                'scope' => $scope,
                'scope_hash' => $scopeHash,
                'status' => 'draft',
                'revision' => 0,
                'created_by' => $actor->id,
            ]);
            AuditLog::create([
                'user_id' => $actor->id,
                'action' => 'production.service_order_created',
                'subject_type' => ProductionServiceOrder::class,
                'subject_id' => $order->id,
                'metadata' => ['opportunity_id' => $opportunity->id, 'supplier_id' => $supplier->id, 'source_quote_id' => $quote->id, 'task_ids' => $data['task_ids'], 'scope_hash' => $scopeHash],
            ]);

            return $order;
        }, 3);
    }

    public function issueServiceOrder(Opportunity $opportunity, ProductionServiceOrder $order, User $actor): ProductionServiceOrder
    {
        return DB::transaction(function () use ($opportunity, $order, $actor): ProductionServiceOrder {
            $locked = ProductionServiceOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->require($locked->opportunity_id === $opportunity->id, 'A ordem de serviço não pertence a este caso.', 'order');
            if (in_array($locked->status, ['issued', 'received'], true)) {
                return $locked;
            }
            $this->require($locked->status === 'draft', 'A ordem não pode ser emitida neste estado.', 'status');
            $locked->update([
                'status' => 'issued',
                'issued_by' => $actor->id,
                'issued_at' => now(),
                'revision' => (int) $locked->revision + 1,
            ]);
            AuditLog::create([
                'user_id' => $actor->id,
                'action' => 'production.service_order_issued',
                'subject_type' => ProductionServiceOrder::class,
                'subject_id' => $locked->id,
                'metadata' => ['opportunity_id' => $opportunity->id, 'scope_hash' => $locked->scope_hash],
            ]);

            return $locked->fresh();
        }, 3);
    }

    public function receiveServiceOrder(Opportunity $opportunity, ProductionServiceOrder $order, User $actor, int $receiptAttachmentId): ProductionServiceOrder
    {
        return DB::transaction(function () use ($opportunity, $order, $actor, $receiptAttachmentId): ProductionServiceOrder {
            $locked = ProductionServiceOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->require($locked->opportunity_id === $opportunity->id, 'A ordem de serviço não pertence a este caso.', 'order');
            $receipt = Attachment::query()->whereKey($receiptAttachmentId)->lockForUpdate()->first();
            $this->require(
                $receipt !== null
                && $receipt->opportunity_id === $opportunity->id
                && $receipt->archived_at === null
                && (str_starts_with((string) $receipt->mime_type, 'image/') || $receipt->mime_type === 'application/pdf'),
                'Use um comprovante ativo deste caso em PDF ou imagem.',
                'receipt_attachment_id',
            );
            if ($locked->status === 'received') {
                $this->require($locked->receipt_attachment_id === $receipt->id, 'O recebimento já foi registrado com outro comprovante.', 'receipt_attachment_id');

                return $locked;
            }
            $this->require($locked->status === 'issued', 'Emita a ordem antes de registrar o recebimento.', 'status');
            $locked->update([
                'status' => 'received',
                'received_by' => $actor->id,
                'receipt_attachment_id' => $receipt->id,
                'received_at' => now(),
                'revision' => (int) $locked->revision + 1,
            ]);
            AuditLog::create([
                'user_id' => $actor->id,
                'action' => 'production.service_order_received',
                'subject_type' => ProductionServiceOrder::class,
                'subject_id' => $locked->id,
                'metadata' => ['opportunity_id' => $opportunity->id, 'receipt_attachment_id' => $receipt->id],
            ]);

            return $locked->fresh();
        }, 3);
    }

    /** @param array<string, mixed> $input */
    public function saveTechnicalValidation(Opportunity $opportunity, User $actor, array $input): TechnicalValidation
    {
        $data = Validator::make($input, [
            'id' => ['nullable', 'integer', 'exists:technical_validations,id'],
            'reference' => ['required', 'string', 'max:180'],
            'measurements' => ['nullable', 'array'],
            'drawing_path' => ['nullable', 'string', 'max:255'],
            'evidence' => ['nullable', 'string', 'max:5000'],
            'supplier_name' => ['nullable', 'string', 'max:180'],
            'revision' => ['nullable', 'integer', 'min:0'],
        ])->validate();

        return DB::transaction(function () use ($opportunity, $actor, $data): TechnicalValidation {
            $validation = ! empty($data['id']) ? TechnicalValidation::query()->whereKey($data['id'])->lockForUpdate()->firstOrFail() : new TechnicalValidation(['opportunity_id' => $opportunity->id, 'revision' => 0]);
            $this->require($validation->opportunity_id === $opportunity->id, 'A validação técnica não pertence a este caso.', 'id');
            if (array_key_exists('revision', $data)) {
                $this->require((int) $data['revision'] === (int) $validation->revision, 'A validação mudou. Atualize antes de salvar.', 'revision');
            }
            $changed = $validation->exists && collect(['reference', 'measurements', 'drawing_path', 'evidence', 'supplier_name'])->contains(fn (string $key): bool => $validation->getAttribute($key) != ($data[$key] ?? null));
            $validation->fill(collect($data)->only(['reference', 'measurements', 'drawing_path', 'evidence', 'supplier_name'])->all());
            $validation->revision = (int) $validation->revision + 1;
            if ($changed && $validation->status === 'confirmed') {
                $validation->status = 'pending';
                $validation->confirmed_by = null;
                $validation->confirmed_at = null;
                $validation->invalidated_at = now();
                $validation->invalidated_reason = 'Dados técnicos alterados; reconfirmação obrigatória.';
            }
            // O local do caso é a régua das medidas. Descobrir na montagem que a
            // estrutura não passa pela porta custa o evento. Conferido antes de
            // gravar: recusar depois de salvar desfaria o registro sem motivo.
            $venue = $opportunity->venue;
            $conflicts = $venue ? $venue->accessConflicts($validation->measurements ?? []) : [];
            $this->require(
                $conflicts === [],
                'A medida não passa pelo acesso do local: '.implode(' ', $conflicts).' Ajuste a medida ou cadastre a exceção acordada com o local.',
                'measurements',
            );
            $validation->venue_id = $venue?->id;
            $validation->save();
            AuditLog::create(['user_id' => $actor->id, 'action' => 'production.technical_validation_saved', 'subject_type' => TechnicalValidation::class, 'subject_id' => $validation->id, 'metadata' => ['opportunity_id' => $opportunity->id, 'revision' => $validation->revision, 'invalidated' => $changed && $validation->status === 'pending', 'venue_id' => $venue?->id]]);

            return $validation->fresh();
        }, 3);
    }

    public function confirmTechnicalValidation(Opportunity $opportunity, TechnicalValidation $validation, User $actor, string $evidence, ?int $revision = null): TechnicalValidation
    {
        return DB::transaction(function () use ($opportunity, $validation, $actor, $evidence, $revision): TechnicalValidation {
            $locked = TechnicalValidation::query()->whereKey($validation->id)->lockForUpdate()->firstOrFail();
            $this->require($locked->opportunity_id === $opportunity->id, 'A validação técnica não pertence a este caso.', 'validation');
            $this->require($revision === null || $revision === (int) $locked->revision, 'A validação mudou. Atualize antes de confirmar.', 'revision');
            $this->require(filled(trim($evidence)), 'Registre a evidência da reconfirmação.', 'evidence');
            if ($locked->status === 'confirmed' && $locked->confirmation_evidence === trim($evidence)) {
                return $locked;
            }
            $locked->update(['status' => 'confirmed', 'confirmed_by' => $actor->id, 'confirmed_at' => now(), 'confirmation_evidence' => trim($evidence), 'invalidated_at' => null, 'invalidated_reason' => null, 'revision' => (int) $locked->revision + 1]);
            AuditLog::create(['user_id' => $actor->id, 'action' => 'production.technical_validation_confirmed', 'subject_type' => TechnicalValidation::class, 'subject_id' => $locked->id, 'metadata' => ['opportunity_id' => $opportunity->id, 'evidence' => trim($evidence)]]);

            return $locked->fresh();
        }, 3);
    }

    public function prepareFromApprovedScope(Opportunity $opportunity, User $actor): ProductionTaskPreview
    {
        $budget = $opportunity->budgets()->where('status', 'approved')->latest('version')->first();
        $this->require($budget !== null, 'Aprove um orçamento antes de preparar a produção.', 'scope');
        $items = $budget->items()->orderBy('id')->get()->map(fn ($item): array => ['description' => $item->description, 'category' => $item->category, 'quantity' => $item->quantity, 'unit' => $item->unit])->values()->all();
        $brief = EventBrief::where('opportunity_id', $opportunity->id)->where('status', 'approved')->first();
        if ($brief) {
            foreach (BriefRequirement::where('event_brief_id', $brief->id)->where('status', 'confirmed')->orderBy('id')->get() as $requirement) {
                $items[] = ['description' => $requirement->requirement, 'category' => $requirement->area, 'quantity' => $requirement->quantity, 'unit' => $requirement->unit, 'requirement_id' => $requirement->id];
            }
        }
        $this->require($items !== [], 'O orçamento aprovado não possui itens para converter em tarefas.', 'scope');
        $source = ['budget_id' => $budget->id, 'budget_version' => $budget->version, 'budget_revision' => $budget->revision, 'updated_at' => optional($budget->updated_at)->toIso8601String()];
        if ($brief) {
            $source['brief_revision'] = $brief->revision;
        }
        $hash = hash('sha256', json_encode([$source, $items], JSON_THROW_ON_ERROR));

        return ProductionTaskPreview::firstOrCreate(['opportunity_id' => $opportunity->id, 'source_hash' => $hash], ['created_by' => $actor->id, 'source' => $source, 'items' => $items, 'status' => 'pending']);
    }

    public function confirmScopePreview(Opportunity $opportunity, ProductionTaskPreview $preview, User $actor): array
    {
        $this->require($preview->opportunity_id === $opportunity->id, 'A prévia não pertence a este caso.', 'preview');

        return DB::transaction(function () use ($opportunity, $preview, $actor): array {
            $locked = ProductionTaskPreview::query()->whereKey($preview->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'confirmed') {
                return $locked->result ?? [];
            }
            $source = $locked->source ?? [];
            $budget = $opportunity->budgets()->where('status', 'approved')->latest('version')->first();
            $this->require($budget && (int) $budget->id === (int) ($source['budget_id'] ?? 0) && (int) $budget->revision === (int) ($source['budget_revision'] ?? -1), 'O orçamento aprovado mudou. Gere uma nova prévia.', 'preview');
            if (array_key_exists('brief_revision', $source)) {
                $brief = EventBrief::where('opportunity_id', $opportunity->id)->where('status', 'approved')->first();
                $this->require($brief && $brief->revision === $source['brief_revision'], 'O briefing aprovado mudou. Gere uma nova prévia de produção.', 'preview');
            }
            $taskIds = [];
            foreach (($locked->items ?? []) as $index => $item) {
                $task = ProductionTask::firstOrCreate(['source_preview_id' => $locked->id, 'source_item_index' => $index], ['opportunity_id' => $opportunity->id, 'assigned_to' => $actor->id, 'title' => $item['description'], 'description' => 'Preparado a partir do orçamento aprovado · '.($item['category'] ?? 'Operação'), 'phase' => 'preparation', 'status' => 'todo', 'priority' => 'normal', 'due_date' => null, 'sort_order' => $index]);
                $taskIds[] = $task->id;
            }
            $result = ['task_ids' => $taskIds, 'count' => count($taskIds)];
            $locked->update(['status' => 'confirmed', 'confirmed_at' => now(), 'result' => $result]);
            AuditLog::create(['user_id' => $actor->id, 'action' => 'production.scope_preview_confirmed', 'subject_type' => ProductionTaskPreview::class, 'subject_id' => $locked->id, 'metadata' => ['opportunity_id' => $opportunity->id, 'task_ids' => $taskIds]]);

            return $result;
        }, 3);
    }

    private function requireSchedulePair(?string $startsAt, ?string $endsAt): void
    {
        $this->require(
            filled($startsAt) === filled($endsAt),
            'Informe início e fim juntos para reservar a equipe.',
            'scheduled_starts_at',
        );
    }

    /** @param array<int, array<string, mixed>> $team
     *  @return array<int, array{user_id: int, role: ?string, is_responsible: bool}>
     */
    private function normaliseTeam(array $team, int $fallbackUserId): array
    {
        if ($team === []) {
            return [['user_id' => $fallbackUserId, 'role' => null, 'is_responsible' => true]];
        }
        $normalised = array_map(fn (array $member): array => [
            'user_id' => (int) $member['user_id'],
            'role' => filled($member['role'] ?? null) ? trim((string) $member['role']) : null,
            'is_responsible' => (bool) ($member['is_responsible'] ?? false),
        ], $team);
        $responsibleIndexes = array_keys(array_filter($normalised, fn (array $member): bool => $member['is_responsible']));
        if ($responsibleIndexes === []) {
            $normalised[0]['is_responsible'] = true;
            $responsibleIndexes = [0];
        }
        $this->require(count($responsibleIndexes) === 1, 'Escolha uma única pessoa responsável pela tarefa.', 'team');

        return $normalised;
    }

    /** @param array<int, array{user_id: int, role: ?string, is_responsible: bool}> $team */
    private function responsibleUserId(array $team): int
    {
        foreach ($team as $member) {
            if ($member['is_responsible']) {
                return $member['user_id'];
            }
        }

        throw new \LogicException('A equipe normalizada precisa ter uma pessoa responsável.');
    }

    private function assertDependencyWindow(?ProductionTask $dependency, string $startsAt): void
    {
        if ($dependency === null) {
            return;
        }
        $this->require(
            $dependency->scheduled_ends_at !== null,
            'Agende primeiro o término da tarefa da qual esta atividade depende.',
            'scheduled_starts_at',
        );
        $starts = \Carbon\CarbonImmutable::parse($startsAt);
        $this->require(
            $starts->greaterThanOrEqualTo($dependency->scheduled_ends_at),
            'A tarefa dependente só pode começar após o término da atividade anterior.',
            'scheduled_starts_at',
        );
    }

    /** @param array<int, array{user_id: int, role: ?string, is_responsible: bool}> $team */
    private function assertTeamAvailable(array $team, string $startsAt, string $endsAt, ?int $excludedTaskId): void
    {
        $userIds = array_column($team, 'user_id');
        $conflicts = ProductionTaskAssignment::query()
            ->with(['task:id,title,scheduled_starts_at,scheduled_ends_at', 'user:id,name'])
            ->whereIn('user_id', $userIds)
            ->whereHas('task', function ($query) use ($startsAt, $endsAt, $excludedTaskId): void {
                $query->whereNotNull('scheduled_starts_at')
                    ->whereNotNull('scheduled_ends_at')
                    ->where('scheduled_starts_at', '<', $endsAt)
                    ->where('scheduled_ends_at', '>', $startsAt);
                if ($excludedTaskId !== null) {
                    $query->whereKeyNot($excludedTaskId);
                }
            })
            ->get();
        $this->require(
            $conflicts->isEmpty(),
            'Há conflito de escala com '. $conflicts->map(fn (ProductionTaskAssignment $assignment): string => ($assignment->user?->name ?? 'Pessoa') .' em “'. ($assignment->task?->title ?? 'outra tarefa') .'”')->unique()->implode(', ') .'.',
            'scheduled_starts_at',
        );
    }

    /** @return array<int, array{user_id: int, role: ?string, is_responsible: bool}> */
    private function teamPayloadFromTask(ProductionTask $task): array
    {
        $task->loadMissing('assignments');
        $team = $task->assignments->map(fn (ProductionTaskAssignment $assignment): array => [
            'user_id' => $assignment->user_id,
            'role' => $assignment->role,
            'is_responsible' => $assignment->is_responsible,
        ])->all();

        return $team;
    }

    /** @param array<int, array{user_id: int, role: ?string, is_responsible: bool}> $team */
    private function syncTeam(ProductionTask $task, array $team): void
    {
        $task->assignments()->delete();
        foreach ($team as $member) {
            $task->assignments()->create($member);
        }
    }

    private function newServiceOrderCode(): string
    {
        do {
            $code = 'OS-'.Str::upper(Str::random(8));
        } while (ProductionServiceOrder::where('code', $code)->exists());

        return $code;
    }

    private function dependencyReaches(?ProductionTask $start, ?int $targetId): bool
    {
        $seen = [];
        $current = $start;
        while ($current) {
            if ($targetId !== null && $current->id === $targetId) {
                return true;
            }
            if (isset($seen[$current->id])) {
                return true;
            }
            $seen[$current->id] = true;
            $current = $current->dependency_id ? ProductionTask::query()->whereKey($current->dependency_id)->first() : null;
        }

        return false;
    }

    private function require(bool $condition, string $message, string $field = 'production'): void
    {
        if (! $condition) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }
}
