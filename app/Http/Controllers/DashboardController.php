<?php

namespace App\Http\Controllers;

use App\Enums\OpportunityStage;
use App\Models\Activity;
use App\Models\Opportunity;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $opportunities = Opportunity::query()
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Opportunity $opportunity): array => [
                'id' => $opportunity->id,
                'title' => $opportunity->title,
                'clientName' => $opportunity->client_name,
                'stage' => $opportunity->stage->value,
                'stageLabel' => $opportunity->stage->label(),
                'nextAction' => $opportunity->next_action,
                'eventDate' => $opportunity->event_date?->format('d/m/Y'),
                'estimatedValueCents' => $opportunity->estimated_value_cents,
                'briefingStatus' => $opportunity->briefing_status,
            ]);

        $columns = collect(OpportunityStage::cases())
            ->reject(fn (OpportunityStage $stage) => in_array($stage, [OpportunityStage::LOST, OpportunityStage::CANCELLED], true))
            ->map(fn (OpportunityStage $stage): array => [
                'id' => $stage->value,
                'label' => $stage->label(),
                'count' => $opportunities->where('stage', $stage->value)->count(),
            ])
            ->values();

        return Inertia::render('Dashboard', [
            'todayQueue' => Activity::with('assignee')->whereNotIn('status', ['done', 'cancelled'])->orderBy('due_at')->get()->map(fn ($activity) => ['id' => $activity->id, 'title' => $activity->title, 'owner' => $activity->assignee?->name ?? 'Sem responsável', 'ownerId' => $activity->user_id, 'due' => $activity->due_at?->format('d/m/Y H:i'), 'overdue' => $activity->due_at?->isPast() ?? false, 'priority' => $activity->priority, 'status' => $activity->status]),
            'currentUserId' => request()->user()->id,
            'todayLabel' => now()->locale('pt_BR')->translatedFormat('l · d M Y'),
            'aiMode' => config('ai.mode', 'manual'),
            'opportunities' => $opportunities->values(),
            'columns' => $columns,
            'metrics' => [
                'activeOpportunities' => $opportunities->whereNotIn('stage', ['closed', 'lost', 'cancelled'])->count(),
                'pendingBriefings' => $opportunities->where('briefingStatus', '!=', 'complete')->count(),
                'nextActions' => $opportunities->filter(fn (array $item): bool => filled($item['nextAction']))->count(),
            ],
        ]);
    }
}
