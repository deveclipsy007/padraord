<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class NextActionService
{
    public function clear(Opportunity $case, User $actor): void
    {
        DB::transaction(function () use ($case, $actor): void {
            $locked = Opportunity::query()->whereKey($case->id)->lockForUpdate()->firstOrFail();
            Activity::query()->where('opportunity_id', $locked->id)->where('is_next_action', true)->update(['is_next_action' => false]);
            $locked->update(['next_action' => null, 'next_action_at' => null]);
            AuditLog::create(['user_id' => $actor->id, 'action' => 'opportunity.next_action_cleared', 'subject_type' => Opportunity::class, 'subject_id' => $locked->id]);
        });
    }

    public function set(Opportunity $case, User $actor, array $data): Activity
    {
        return DB::transaction(function () use ($case, $actor, $data): Activity {
            $locked = Opportunity::query()->whereKey($case->id)->lockForUpdate()->firstOrFail();
            $active = Activity::query()
                ->where('opportunity_id', $locked->id)
                ->where('is_next_action', true)
                ->whereNotIn('status', ['done', 'cancelled'])
                ->lockForUpdate()
                ->get();
            $existing = $active->first(fn (Activity $activity): bool => $activity->title === $data['title'] && (int) $activity->user_id === (int) ($data['user_id'] ?? 0) && (string) $activity->due_at === (string) ($data['due_at'] ?? ''));

            Activity::query()->whereIn('id', $active->pluck('id'))->update(['is_next_action' => false]);
            $activity = $existing?->fresh() ?? Activity::create([
                'opportunity_id' => $locked->id,
                'user_id' => $data['user_id'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'type' => 'follow_up',
                'priority' => $data['priority'] ?? $locked->priority ?? 'normal',
                'status' => 'todo',
                'due_at' => $data['due_at'] ?? null,
                'is_next_action' => true,
            ]);
            $activity->update(['is_next_action' => true]);
            $locked->update(['next_action' => $activity->title, 'next_action_at' => $activity->due_at]);
            AuditLog::create([
                'user_id' => $actor->id,
                'action' => 'opportunity.next_action_set',
                'subject_type' => Opportunity::class,
                'subject_id' => $locked->id,
                'metadata' => ['activity_id' => $activity->id, 'title' => $activity->title, 'due_at' => optional($activity->due_at)->toIso8601String(), 'user_id' => $activity->user_id],
            ]);

            return $activity;
        });
    }
}
