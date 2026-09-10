<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Opportunity;
use App\Models\ProductionTask;
use App\Models\ProductionTaskPreview;
use App\Models\TechnicalValidation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ProductionOperations
{
    public const PHASES = ['preparation', 'setup', 'event', 'teardown'];

    public const STATUSES = ['todo', 'in_progress', 'done', 'blocked'];

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

            $sortOrder = ((int) $opportunity->productionTasks()->max('sort_order')) + 1;
            $task = $opportunity->productionTasks()->create([
                'assigned_to' => $data['assigned_to'] ?? $actor->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'phase' => $data['phase'] ?? 'preparation',
                'status' => 'todo',
                'priority' => $data['priority'] ?? 'normal',
                'due_date' => $data['due_date'] ?? null,
                'dependency_id' => $dependency?->id,
                'technical_validation_id' => $validation?->id,
                'sort_order' => $sortOrder,
            ]);

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
            $validation->save();
            AuditLog::create(['user_id' => $actor->id, 'action' => 'production.technical_validation_saved', 'subject_type' => TechnicalValidation::class, 'subject_id' => $validation->id, 'metadata' => ['opportunity_id' => $opportunity->id, 'revision' => $validation->revision, 'invalidated' => $changed && $validation->status === 'pending']]);

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
        $this->require($items !== [], 'O orçamento aprovado não possui itens para converter em tarefas.', 'scope');
        $source = ['budget_id' => $budget->id, 'budget_version' => $budget->version, 'budget_revision' => $budget->revision, 'updated_at' => optional($budget->updated_at)->toIso8601String()];
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
