<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\CaseBlocker;
use App\Models\Opportunity;
use App\Models\ProductionTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class DecisionQueue
{
    private const SEVERITIES = ['normal', 'high', 'critical'];

    private const IMPORTANCE = ['low', 'normal', 'high'];

    private const EFFORTS = ['small', 'medium', 'large'];

    /** @param array<string, mixed> $data */
    public function openBlocker(Opportunity $case, User $actor, array $data): CaseBlocker
    {
        $data = Validator::make($data, [
            'title' => ['required', 'string', 'max:180'],
            'reason' => ['required', 'string', 'min:3', 'max:5000'],
            'severity' => ['required', 'in:'.implode(',', self::SEVERITIES)],
            'importance' => ['required', 'in:'.implode(',', self::IMPORTANCE)],
            'effort' => ['required', 'in:'.implode(',', self::EFFORTS)],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'production_task_id' => ['nullable', 'integer', 'exists:production_tasks,id'],
        ])->validate();

        return DB::transaction(function () use ($case, $actor, $data): CaseBlocker {
            $lockedCase = Opportunity::query()->whereKey($case->id)->lockForUpdate()->firstOrFail();
            $task = null;
            if (filled($data['production_task_id'] ?? null)) {
                $task = ProductionTask::query()->whereKey($data['production_task_id'])->lockForUpdate()->first();
                $this->require($task !== null && $task->opportunity_id === $lockedCase->id, 'A tarefa precisa pertencer ao mesmo caso.', 'production_task_id');
                $this->require($task->status !== 'done', 'Uma tarefa concluída não pode receber um bloqueio novo.', 'production_task_id');
            }

            $blocker = CaseBlocker::create([
                ...$data,
                'opportunity_id' => $lockedCase->id,
                'stage' => $lockedCase->stage->value,
                'resume_status' => $task?->status,
            ]);

            if ($task) {
                $before = $task->status;
                $task->update([
                    'status' => 'blocked',
                    'blocked_reason' => $data['reason'],
                    'revision' => (int) $task->revision + 1,
                ]);
                AuditLog::create([
                    'user_id' => $actor->id,
                    'action' => 'decision_queue.task_blocked',
                    'subject_type' => ProductionTask::class,
                    'subject_id' => $task->id,
                    'metadata' => [
                        'opportunity_id' => $lockedCase->id,
                        'blocker_id' => $blocker->id,
                        'before' => $before,
                        'after' => 'blocked',
                    ],
                ]);
            }

            AuditLog::create([
                'user_id' => $actor->id,
                'action' => 'decision_queue.blocker_opened',
                'subject_type' => CaseBlocker::class,
                'subject_id' => $blocker->id,
                'metadata' => [
                    'opportunity_id' => $lockedCase->id,
                    'production_task_id' => $blocker->production_task_id,
                    'severity' => $blocker->severity,
                    'importance' => $blocker->importance,
                    'effort' => $blocker->effort,
                ],
            ]);

            return $blocker->fresh(['owner', 'task']);
        });
    }

    public function resolveBlocker(Opportunity $case, CaseBlocker $blocker, User $actor, string $resolution): CaseBlocker
    {
        $data = Validator::make(['resolution' => $resolution], [
            'resolution' => ['required', 'string', 'min:3', 'max:5000'],
        ])->validate();

        return DB::transaction(function () use ($case, $blocker, $actor, $data): CaseBlocker {
            $locked = CaseBlocker::query()->with('task')->whereKey($blocker->id)->lockForUpdate()->firstOrFail();
            $this->require($locked->opportunity_id === $case->id, 'O bloqueio precisa pertencer ao mesmo caso.', 'blocker');
            if ($locked->status === 'resolved') {
                return $locked;
            }

            $locked->update([
                'status' => 'resolved',
                'resolved_at' => now(),
                'resolved_by' => $actor->id,
                'resolution_note' => $data['resolution'],
                'revision' => (int) $locked->revision + 1,
            ]);

            $task = $locked->task;
            $otherOpenBlockers = $task
                ? CaseBlocker::query()->where('production_task_id', $task->id)->where('status', 'open')->exists()
                : false;
            if ($task && $otherOpenBlockers === false && $task->status === 'blocked') {
                $resumeStatus = in_array($locked->resume_status, ProductionOperations::STATUSES, true)
                    ? $locked->resume_status
                    : 'todo';
                $task->update([
                    'status' => $resumeStatus,
                    'blocked_reason' => null,
                    'revision' => (int) $task->revision + 1,
                ]);
                AuditLog::create([
                    'user_id' => $actor->id,
                    'action' => 'decision_queue.task_unblocked',
                    'subject_type' => ProductionTask::class,
                    'subject_id' => $task->id,
                    'metadata' => ['opportunity_id' => $case->id, 'blocker_id' => $locked->id, 'status' => $resumeStatus],
                ]);
            }

            AuditLog::create([
                'user_id' => $actor->id,
                'action' => 'decision_queue.blocker_resolved',
                'subject_type' => CaseBlocker::class,
                'subject_id' => $locked->id,
                'metadata' => ['opportunity_id' => $case->id, 'resolution' => $data['resolution']],
            ]);

            return $locked->fresh(['owner', 'task']);
        });
    }

    public function reopenBlocker(Opportunity $case, CaseBlocker $blocker, User $actor, string $reason): CaseBlocker
    {
        $data = Validator::make(['reason' => $reason], [
            'reason' => ['required', 'string', 'min:3', 'max:5000'],
        ])->validate();

        return DB::transaction(function () use ($case, $blocker, $actor, $data): CaseBlocker {
            $locked = CaseBlocker::query()->with('task')->whereKey($blocker->id)->lockForUpdate()->firstOrFail();
            $this->require($locked->opportunity_id === $case->id, 'O bloqueio precisa pertencer ao mesmo caso.', 'blocker');
            if ($locked->status === 'open') {
                return $locked;
            }

            $locked->update([
                'status' => 'open',
                'reopened_at' => now(),
                'reopened_by' => $actor->id,
                'reopen_reason' => $data['reason'],
                'revision' => (int) $locked->revision + 1,
            ]);

            if ($locked->task) {
                $task = ProductionTask::query()->whereKey($locked->task->id)->lockForUpdate()->firstOrFail();
                $before = $task->status;
                $task->update([
                    'status' => 'blocked',
                    'blocked_reason' => $data['reason'],
                    'revision' => (int) $task->revision + 1,
                ]);
                AuditLog::create([
                    'user_id' => $actor->id,
                    'action' => 'decision_queue.task_blocked',
                    'subject_type' => ProductionTask::class,
                    'subject_id' => $task->id,
                    'metadata' => ['opportunity_id' => $case->id, 'blocker_id' => $locked->id, 'before' => $before, 'after' => 'blocked', 'reopened' => true],
                ]);
            }

            AuditLog::create([
                'user_id' => $actor->id,
                'action' => 'decision_queue.blocker_reopened',
                'subject_type' => CaseBlocker::class,
                'subject_id' => $locked->id,
                'metadata' => ['opportunity_id' => $case->id, 'reason' => $data['reason']],
            ]);

            return $locked->fresh(['owner', 'task']);
        });
    }

    /** @return array<int, array<string, mixed>> */
    public function for(Opportunity $case): array
    {
        $tasks = ProductionTask::query()->where('opportunity_id', $case->id)->get(['id', 'dependency_id']);

        $items = CaseBlocker::query()
            ->with(['owner:id,name', 'task:id,title,status'])
            ->where('opportunity_id', $case->id)
            ->where('status', 'open')
            ->get()
            ->map(function (CaseBlocker $blocker) use ($tasks): array {
                $impact = $this->taskImpact($blocker->production_task_id, $tasks);

                return [
                    'id' => $blocker->id,
                    'kind' => 'blocker',
                    'source' => 'blocker',
                    'title' => $blocker->title,
                    'reason' => $blocker->reason,
                    'owner' => $blocker->owner?->name,
                    'ownerId' => $blocker->owner_id,
                    'stage' => $blocker->stage,
                    'severity' => $blocker->severity,
                    'importance' => $blocker->importance,
                    'effort' => $blocker->effort,
                    'status' => $blocker->task ? $blocker->task->status : 'blocked',
                    'taskId' => $blocker->production_task_id,
                    'taskTitle' => $blocker->task?->title,
                    'directWork' => $impact['directWork'],
                    'unlockedWork' => $impact['unlockedWork'],
                    'dependencyCycle' => $impact['dependencyCycle'],
                    'createdAt' => $blocker->created_at?->getTimestamp() ?? 0,
                ];
            })
            ->all();

        $items = [
            ...$items,
            ...Activity::query()
                ->with('assignee:id,name')
                ->where('opportunity_id', $case->id)
                ->where('is_next_action', true)
                ->whereNotIn('status', ['done', 'cancelled'])
                ->get()
                ->map(fn (Activity $activity): array => [
                    'id' => $activity->id,
                    'kind' => 'next_action',
                    'source' => 'activity',
                    'title' => $activity->title,
                    'reason' => $activity->description,
                    'owner' => $activity->assignee?->name,
                    'ownerId' => $activity->user_id,
                    'stage' => $case->stage->value,
                    'severity' => $activity->priority === 'high' ? 'high' : 'normal',
                    'importance' => $activity->priority,
                    'effort' => 'medium',
                    'status' => $activity->status,
                    'taskId' => null,
                    'taskTitle' => null,
                    'directWork' => 1,
                    'unlockedWork' => 0,
                    'dependencyCycle' => false,
                    'createdAt' => $activity->created_at?->getTimestamp() ?? 0,
                ])
                ->all(),
        ];

        usort($items, function (array $left, array $right): int {
            $leftRank = [
                $left['severity'] === 'critical' ? 0 : 1,
                -$left['unlockedWork'],
                -$this->importanceRank($left['importance']),
                $this->effortRank($left['effort']),
                $left['createdAt'],
                $left['id'],
            ];
            $rightRank = [
                $right['severity'] === 'critical' ? 0 : 1,
                -$right['unlockedWork'],
                -$this->importanceRank($right['importance']),
                $this->effortRank($right['effort']),
                $right['createdAt'],
                $right['id'],
            ];

            return $leftRank <=> $rightRank;
        });

        return array_map(function (array $item): array {
            unset($item['createdAt']);

            return $item;
        }, $items);
    }

    /** @return array<int, array<string, mixed>> */
    public function history(Opportunity $case): array
    {
        $tasks = ProductionTask::query()->where('opportunity_id', $case->id)->get(['id', 'dependency_id']);

        return CaseBlocker::query()
            ->with(['owner:id,name', 'task:id,title,status'])
            ->where('opportunity_id', $case->id)
            ->latest('updated_at')
            ->get()
            ->map(function (CaseBlocker $blocker) use ($tasks): array {
                $impact = $this->taskImpact($blocker->production_task_id, $tasks);

                return [
                    'id' => $blocker->id,
                    'title' => $blocker->title,
                    'reason' => $blocker->reason,
                    'owner' => $blocker->owner?->name,
                    'stage' => $blocker->stage,
                    'severity' => $blocker->severity,
                    'importance' => $blocker->importance,
                    'effort' => $blocker->effort,
                    'status' => $blocker->status,
                    'taskTitle' => $blocker->task?->title,
                    'directWork' => $impact['directWork'],
                    'unlockedWork' => $impact['unlockedWork'],
                    'dependencyCycle' => $impact['dependencyCycle'],
                    'resolutionNote' => $blocker->resolution_note,
                    'reopenReason' => $blocker->reopen_reason,
                    'resolvedAt' => $blocker->resolved_at?->toIso8601String(),
                    'reopenedAt' => $blocker->reopened_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }

    /** @return array{directWork: int, unlockedWork: int, dependencyCycle: bool} */
    private function taskImpact(?int $taskId, $tasks): array
    {
        if (($taskId === null || $taskId === 0) || $tasks->contains('id', $taskId) === false) {
            return ['directWork' => 0, 'unlockedWork' => 0, 'dependencyCycle' => false];
        }

        $children = [];
        foreach ($tasks as $task) {
            if ($task->dependency_id) {
                $children[$task->dependency_id][] = $task->id;
            }
        }

        $seen = [$taskId => true];
        $pending = $children[$taskId] ?? [];
        $unlocked = 0;
        $dependencyCycle = false;
        while ($pending !== []) {
            $current = array_shift($pending);
            if (isset($seen[$current])) {
                $dependencyCycle = true;

                continue;
            }
            $seen[$current] = true;
            $unlocked++;
            foreach ($children[$current] ?? [] as $child) {
                $pending[] = $child;
            }
        }

        return ['directWork' => 1, 'unlockedWork' => $unlocked, 'dependencyCycle' => $dependencyCycle];
    }

    private function importanceRank(string $importance): int
    {
        return array_search($importance, self::IMPORTANCE, true) ?: 0;
    }

    private function effortRank(string $effort): int
    {
        return array_search($effort, self::EFFORTS, true) ?: 0;
    }

    private function require(bool $condition, string $message, string $field): void
    {
        if ($condition === false) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }
}
