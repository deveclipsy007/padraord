<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\AssistantPreview;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Support\Collection;

final class OperationalQueueService
{
    public function items(User $user, array $filters): Collection
    {
        $query = Activity::query()
            ->with(['opportunity', 'assignee'])
            ->whereNotIn('status', ['done', 'cancelled'])
            ->where(function ($builder): void {
                $builder->whereNull('opportunity_id')->orWhereHas('opportunity', fn ($opportunity) => $opportunity->whereNull('archived_at'));
            });

        if (($filters['scope'] ?? 'mine') === 'mine') {
            $query->where(function ($builder) use ($user): void {
                $builder->where('user_id', $user->id)->orWhereHas('opportunity', fn ($opportunity) => $opportunity->where('owner_id', $user->id));
            });
        }
        if (! empty($filters['owner'])) {
            $query->where('user_id', $filters['owner']);
        }
        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }
        $this->period($query, $filters['period'] ?? 'next_7');

        $items = $query->get()->map(fn (Activity $activity): array => [
            'id' => $activity->id,
            'kind' => $activity->type === 'follow_up' ? 'follow_up' : 'activity',
            'title' => $activity->title,
            'context' => $activity->opportunity?->title ?? 'Tarefa interna',
            'href' => $activity->opportunity ? "/opportunities/{$activity->opportunity_id}" : '/agenda',
            'owner' => $activity->assignee?->name,
            'ownerId' => $activity->user_id,
            'dueAt' => $activity->due_at?->format('d/m/Y H:i'),
            'priority' => $activity->priority,
            'status' => $activity->status,
            'overdue' => $activity->due_at?->isPast() ?? false,
            'availableActions' => ['complete', 'reschedule', 'assign'],
        ]);

        if (($filters['scope'] ?? 'mine') === 'team' && ($filters['type'] ?? 'all') !== 'activity') {
            $items = $items->concat($this->reviews($filters));
        }

        return $items->sortBy(fn (array $item) => [$item['overdue'] ? 0 : 1, $item['dueAt'] ?? '9999'])->values();
    }

    private function reviews(array $filters): Collection
    {
        $items = Opportunity::query()->whereNull('archived_at')->where('briefing_status', 'awaiting_review')->get()->map(fn (Opportunity $case): array => [
            'id' => $case->id,
            'kind' => 'review',
            'title' => 'Revisar briefing',
            'context' => $case->title,
            'href' => "/opportunities/{$case->id}/briefing",
            'owner' => $case->owner?->name,
            'ownerId' => $case->owner_id,
            'dueAt' => $case->next_action_at?->format('d/m/Y H:i'),
            'priority' => $case->priority,
            'status' => 'awaiting_review',
            'overdue' => $case->next_action_at?->isPast() ?? false,
            'availableActions' => ['open'],
        ]);

        $previews = AssistantPreview::query()->where('status', 'preview')->get()->map(fn (AssistantPreview $preview): array => [
            'id' => $preview->id,
            'kind' => 'review',
            'title' => 'Confirmar sugestão do assistente',
            'context' => 'Prévia pendente',
            'href' => "/assistant/previews/{$preview->id}",
            'owner' => null,
            'ownerId' => $preview->user_id,
            'dueAt' => null,
            'priority' => 'normal',
            'status' => 'preview',
            'overdue' => false,
            'availableActions' => ['open'],
        ]);

        return $items->concat($previews);
    }

    private function period($query, string $period): void
    {
        $today = today();
        match ($period) {
            'overdue' => $query->whereNotNull('due_at')->where('due_at', '<', now()),
            'today' => $query->whereDate('due_at', $today),
            'tomorrow' => $query->whereDate('due_at', $today->copy()->addDay()),
            'next_7' => $query->where(function ($builder) use ($today): void {
                $builder->whereNull('due_at')->orWhereBetween('due_at', [now()->startOfDay(), $today->copy()->addDays(7)->endOfDay()]);
            }),
            'all' => null,
            default => $query->where(function ($builder) use ($today): void {
                $builder->whereNull('due_at')->orWhereBetween('due_at', [now()->startOfDay(), $today->copy()->addDay()->endOfDay()]);
            }),
        };
    }
}
