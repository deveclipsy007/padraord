<?php

namespace App\Http\Controllers;

use App\Enums\CommercialStage;
use App\Models\Opportunity;
use App\Models\ProductionTask;
use App\Services\OperationalQueueService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, OperationalQueueService $queueService): Response
    {
        $filters = [
            'scope' => in_array($request->query('scope', 'mine'), ['mine', 'team'], true) ? $request->query('scope', 'mine') : 'mine',
            'period' => in_array($request->query('period', 'next_7'), ['overdue', 'today', 'tomorrow', 'next_7', 'all'], true) ? $request->query('period', 'next_7') : 'next_7',
            'priority' => in_array($request->query('priority'), ['low', 'normal', 'high'], true) ? $request->query('priority') : '',
            'owner' => (int) $request->query('owner', 0) ?: '',
            'type' => in_array($request->query('type', 'all'), ['activity', 'follow_up', 'review', 'case', 'all'], true) ? $request->query('type', 'all') : 'all',
        ];
        $opportunities = Opportunity::query()
            ->with('owner')
            ->whereNull('archived_at')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Opportunity $opportunity): array => [
                'id' => $opportunity->id,
                'title' => $opportunity->title,
                'clientName' => $opportunity->client_name,
                'stage' => $opportunity->stage->value,
                'stageLabel' => $opportunity->stage->label(),
                'commercialStage' => $opportunity->commercial_stage->value,
                'commercialStageLabel' => $opportunity->commercial_stage->label(),
                'ownerId' => $opportunity->owner_id,
                'ownerName' => $opportunity->owner?->name,
                'priority' => $opportunity->priority,
                'origin' => $opportunity->origin,
                'nextAction' => $opportunity->next_action,
                'nextActionAt' => $opportunity->next_action_at?->format('d/m/Y H:i'),
                'nextActionOverdue' => $opportunity->next_action_at?->isPast() ?? false,
                'eventDate' => $opportunity->event_date?->format('d/m/Y'),
                'estimatedValueCents' => $opportunity->estimated_value_cents,
                'briefingStatus' => $opportunity->briefing_status,
                'commercialRevision' => $opportunity->commercial_revision,
                'archived' => (bool) $opportunity->archived_at,
            ]);

        $columns = collect(CommercialStage::active())
            ->map(fn (CommercialStage $stage): array => [
                'id' => $stage->value,
                'label' => $stage->label(),
                'count' => $opportunities->where('commercialStage', $stage->value)->count(),
                'estimatedValueCents' => $opportunities->where('commercialStage', $stage->value)->sum('estimatedValueCents'),
            ])
            ->values();

        $focus = $request->user()->workspace_focus ?? ($request->user()->isAdmin() ? 'management' : 'production');
        $focusItems = [];
        if ($focus === 'production') {
            $focusItems = ProductionTask::with('opportunity')->where('status', '!=', 'done')->whereHas('opportunity', fn ($q) => $q->whereNull('archived_at')->whereNotIn('stage', ['closed', 'lost', 'cancelled']))->where(fn ($q) => $q->where('assigned_to', $request->user()->id)->orWhereHas('assignments', fn ($a) => $a->where('user_id', $request->user()->id)))->orderBy('due_date')->limit(8)->get()->map(fn ($t) => ['id' => 'task-'.$t->id, 'title' => $t->title, 'context' => $t->opportunity->title, 'href' => '/production/events/'.$t->opportunity_id, 'due' => $t->due_date?->format('Y-m-d'), 'detail' => $t->status === 'blocked' ? 'Bloqueada' : 'Acompanhar execução'])->all();
        } elseif ($focus === 'finance') {
            foreach (['receivables' => 'A receber', 'payables' => 'A pagar'] as $table => $label) {
                foreach (DB::table($table.' as f')->join('opportunities as o', 'o.id', '=', 'f.opportunity_id')->where('f.status', 'open')->whereNull('o.archived_at')->orderBy('f.due_at')->limit(6)->get(['f.id', 'f.label', 'f.due_at', 'f.amount_cents', 'f.opportunity_id', 'o.title']) as $entry) {
                    $focusItems[] = ['id' => $table.'-'.$entry->id, 'title' => $entry->label, 'context' => $entry->title, 'href' => '/opportunities/'.$entry->opportunity_id.'/finance', 'due' => $entry->due_at, 'detail' => $label.' · R$ '.number_format($entry->amount_cents / 100, 2, ',', '.')];
                }
            }
        }

        return Inertia::render('Dashboard', [
            'focusItems' => $focusItems,
            'todayQueue' => $queueService->items($request->user(), $filters),
            'queueFilters' => $filters,
            'currentUserId' => request()->user()->id,
            'todayLabel' => now()->locale('pt_BR')->translatedFormat('l · d M Y'),
            'aiMode' => config('ai.mode', 'manual'),
            'opportunities' => $opportunities->values(),
            'columns' => $columns,
            'metrics' => [
                'activeOpportunities' => $opportunities->whereNotIn('commercialStage', ['lost', 'cancelled'])->count(),
                'pendingBriefings' => $opportunities->where('briefingStatus', '!=', 'complete')->count(),
                'nextActions' => $opportunities->filter(fn (array $item): bool => filled($item['nextAction']))->count(),
            ],
        ]);
    }
}
