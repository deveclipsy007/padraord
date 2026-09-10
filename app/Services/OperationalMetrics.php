<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\AssistantPreview;
use App\Models\Budget;
use App\Models\Document;
use App\Models\Opportunity;
use App\Models\ProductionTask;
use App\Models\User;

final class OperationalMetrics
{
    /**
     * Build metrics only from persisted operational records.
     *
     * A case scope is useful for the case history while the unscoped view
     * powers the global operational history and administration screens.
     */
    public function for(?Opportunity $case = null): array
    {
        $caseId = $case?->id;
        $caseScope = static function ($query) use ($caseId): void {
            if ($caseId !== null) {
                $query->where('opportunity_id', $caseId);
            }
        };

        $activeCases = Opportunity::query()
            ->whereNull('archived_at')
            ->whereNotIn('commercial_stage', ['lost', 'cancelled'])
            ->when($caseId !== null, fn ($query) => $query->whereKey($caseId))
            ->count();

        $openProductionTasksQuery = ProductionTask::query()->whereNotIn('status', ['done', 'cancelled']);
        $caseScope($openProductionTasksQuery);
        $openProductionTasks = $openProductionTasksQuery->count();

        $overdueActivitiesQuery = Activity::query()
            ->whereNotIn('status', ['done', 'cancelled'])
            ->whereNotNull('due_at')
            ->where('due_at', '<', now());
        $caseScope($overdueActivitiesQuery);
        $overdueProductionQuery = ProductionTask::query()
            ->whereNotIn('status', ['done', 'cancelled'])
            ->whereNotNull('due_date')
            ->where('due_date', '<', today());
        $caseScope($overdueProductionQuery);
        $overdueWork = $overdueActivitiesQuery->count() + $overdueProductionQuery->count();

        $pendingReviews = Opportunity::query()
            ->where('briefing_status', 'awaiting_review')
            ->whereNull('archived_at')
            ->when($caseId !== null, fn ($query) => $query->whereKey($caseId))
            ->count();
        $pendingReviews += $this->countScoped(Budget::query()->where('status', 'review'), $caseId);
        $pendingReviews += $this->countScoped(Document::query()->where('status', 'review'), $caseId);
        $pendingReviews += AssistantPreview::query()
            ->where('status', 'preview')
            ->when($caseId !== null, fn ($query) => $query->whereJsonContains('context->opportunity_id', $caseId))
            ->count();

        $workload = User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function (User $user) use ($caseId): array {
                $activities = Activity::query()
                    ->where('user_id', $user->id)
                    ->whereNotIn('status', ['done', 'cancelled'])
                    ->when($caseId !== null, fn ($query) => $query->where('opportunity_id', $caseId))
                    ->count();
                $tasks = ProductionTask::query()
                    ->where('assigned_to', $user->id)
                    ->whereNotIn('status', ['done', 'cancelled'])
                    ->when($caseId !== null, fn ($query) => $query->where('opportunity_id', $caseId))
                    ->count();

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'openItems' => $activities + $tasks,
                ];
            })
            ->values()
            ->all();

        return [
            'activeCases' => $activeCases,
            'openProductionTasks' => $openProductionTasks,
            'overdueWork' => $overdueWork,
            'pendingReviews' => $pendingReviews,
            'workload' => $workload,
            'measured' => true,
        ];
    }

    private function countScoped($query, ?int $caseId): int
    {
        return $query->when($caseId !== null, fn ($builder) => $builder->where('opportunity_id', $caseId))->count();
    }
}
