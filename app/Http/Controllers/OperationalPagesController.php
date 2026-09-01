<?php

namespace App\Http\Controllers;

use App\Enums\Ability;
use App\Enums\CommercialStage;
use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Opportunity;
use App\Models\ProductionTask;
use App\Models\PrototypeFeedback;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OperationalPagesController extends Controller
{
    public function pipeline(Request $request): Response
    {
        $filters = [
            'view' => in_array($request->query('view', 'kanban'), ['kanban', 'list'], true) ? $request->query('view', 'kanban') : 'kanban',
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 120),
            'stage' => in_array($request->query('stage'), array_map(fn (CommercialStage $stage): string => $stage->value, CommercialStage::cases()), true) ? $request->query('stage') : '',
            'owner' => is_numeric($request->query('owner')) && (int) $request->query('owner') > 0 ? (string) (int) $request->query('owner') : '',
            'priority' => in_array($request->query('priority'), ['low', 'normal', 'high'], true) ? $request->query('priority') : '',
            'origin' => in_array($request->query('origin'), ['referral', 'inbound', 'outbound', 'returning_client', 'partner', 'organic', 'other'], true) ? $request->query('origin') : '',
            'overdue' => $request->boolean('overdue'),
            'unassigned' => $request->boolean('unassigned'),
            'status' => in_array($request->query('status', 'active'), ['active', 'archived', 'all'], true) ? $request->query('status', 'active') : 'active',
        ];
        $query = Opportunity::query()->with('owner');
        match ($filters['status']) {
            'archived' => $query->whereNotNull('archived_at'),
            'all' => null,
            default => $query->whereNull('archived_at'),
        };
        if ($filters['q'] !== '') {
            $needle = mb_strtolower($filters['q']);
            $query->where(fn ($builder) => $builder->whereRaw('LOWER(title) LIKE ?', ["%{$needle}%"])->orWhereRaw('LOWER(client_name) LIKE ?', ["%{$needle}%"]));
        }
        if ($filters['stage'] !== '') {
            $query->where('commercial_stage', $filters['stage']);
        } elseif ($filters['status'] === 'active') {
            $query->whereIn('commercial_stage', array_map(fn (CommercialStage $stage): string => $stage->value, CommercialStage::active()));
        }
        if ($filters['owner'] !== '') {
            $query->where('owner_id', $filters['owner']);
        }
        if ($filters['priority'] !== '') {
            $query->where('priority', $filters['priority']);
        }
        if ($filters['origin'] !== '') {
            $query->where('origin', $filters['origin']);
        }
        if ($filters['overdue']) {
            $query->whereNotNull('next_action_at')->where('next_action_at', '<', now());
        }
        if ($filters['unassigned']) {
            $query->whereNull('owner_id');
        }
        $opportunities = $query->latest('updated_at')->limit(500)->get()->map(fn ($item) => [
            'id' => $item->id,
            'title' => $item->title,
            'clientName' => $item->client_name,
            'stage' => $item->stage->value,
            'stageLabel' => $item->stage->label(),
            'commercialStage' => $item->commercial_stage->value,
            'commercialStageLabel' => $item->commercial_stage->label(),
            'ownerId' => $item->owner_id,
            'ownerName' => $item->owner?->name,
            'priority' => $item->priority,
            'origin' => $item->origin,
            'nextAction' => $item->next_action,
            'nextActionAt' => $item->next_action_at?->format('d/m/Y H:i'),
            'eventDate' => $item->event_date?->format('d/m/Y'),
            'estimatedValueCents' => $item->estimated_value_cents,
            'commercialRevision' => $item->commercial_revision,
            'archived' => (bool) $item->archived_at,
        ])->values();
        $stages = collect(CommercialStage::active())->map(fn (CommercialStage $stage): array => [
            'id' => $stage->value,
            'label' => $stage->label(),
            'count' => $opportunities->where('commercialStage', $stage->value)->count(),
            'estimatedValueCents' => $opportunities->where('commercialStage', $stage->value)->sum('estimatedValueCents'),
        ])->values();

        return Inertia::render('Pipeline', ['opportunities' => $opportunities, 'stages' => $stages, 'filters' => $filters, 'owners' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']), 'origins' => ['referral', 'inbound', 'outbound', 'returning_client', 'partner', 'organic', 'other']]);
    }

    public function briefings(): Response
    {
        return Inertia::render('Briefings', ['opportunities' => Opportunity::query()->whereIn('briefing_status', ['not_started', 'processing', 'awaiting_review'])->latest('updated_at')->get()->map(fn ($item) => ['id' => $item->id, 'title' => $item->title, 'clientName' => $item->client_name, 'status' => $item->briefing_status, 'stage' => $item->stage->label()])->values()]);
    }

    public function agenda(Request $request): Response
    {
        $filters = [
            'owner' => is_numeric($request->query('owner')) && (int) $request->query('owner') > 0 ? (string) (int) $request->query('owner') : '',
            'status' => in_array($request->query('status', 'pending'), ['pending', 'all'], true) ? $request->query('status', 'pending') : 'pending',
            'priority' => in_array($request->query('priority'), ['low', 'normal', 'high'], true) ? $request->query('priority') : '',
        ];
        $activitiesQuery = Activity::with(['opportunity', 'assignee'])->orderBy('due_at');
        if ($filters['owner'] !== '') {
            $activitiesQuery->where('user_id', $filters['owner']);
        }
        if ($filters['status'] === 'pending') {
            $activitiesQuery->whereNotIn('status', ['done', 'cancelled']);
        }
        if ($filters['priority'] !== '') {
            $activitiesQuery->where('priority', $filters['priority']);
        }
        $tasksQuery = ProductionTask::with('opportunity')->orderBy('due_date');
        if ($filters['status'] === 'pending') {
            $tasksQuery->where('status', '!=', 'done');
        }
        if ($filters['priority'] !== '') {
            $tasksQuery->where('priority', $filters['priority']);
        }

        return Inertia::render('Agenda', [
            'activities' => $activitiesQuery->get(),
            'tasks' => $tasksQuery->get(),
            'events' => Opportunity::whereNull('archived_at')->whereNotNull('event_date')->orderBy('event_date')->get(['id', 'title', 'event_date']),
            'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'opportunities' => Opportunity::whereNull('archived_at')->orderBy('title')->get(['id', 'title']),
            'filters' => $filters,
        ]);
    }

    public function clients(Request $request): Response
    {
        $filters = [
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 120),
            'status' => in_array($request->query('status', 'active'), ['active', 'archived', 'all'], true) ? $request->query('status', 'active') : 'active',
        ];
        $query = Client::query()->withCount('opportunities');
        match ($filters['status']) {
            'archived' => $query->whereNotNull('archived_at'),
            'all' => null,
            default => $query->whereNull('archived_at'),
        };
        if ($filters['q'] !== '') {
            $needle = mb_strtolower($filters['q']);
            $query->where(fn ($builder) => $builder->whereRaw('LOWER(name) LIKE ?', ["%{$needle}%"])->orWhereRaw('LOWER(industry) LIKE ?', ["%{$needle}%"]));
        }
        $clients = $query->orderBy('name')->paginate(min(50, max(1, (int) $request->query('per_page', 25))))->withQueryString()->through(fn ($client): array => [
            'id' => $client->id,
            'name' => $client->name,
            'industry' => $client->industry,
            'opportunitiesCount' => $client->opportunities_count,
            'archived' => (bool) $client->archived_at,
        ]);

        return Inertia::render('Clients', ['clients' => $clients, 'filters' => $filters]);
    }

    public function history(?Opportunity $opportunity = null): Response
    {
        $query = AuditLog::query()->latest();
        if ($opportunity?->exists) {
            $query->where('subject_type', Opportunity::class)->where('subject_id', $opportunity->id);
        }
        if (! Gate::allows(Ability::ViewPilotFeedback->value)) {
            $query->where('subject_type', '!=', User::class);
        }
        $search = mb_substr((string) request('q', ''), 0, 100);
        if ($search !== '') {
            $query->where('action', 'like', '%'.$search.'%');
        }
        $module = mb_substr((string) request('module', ''), 0, 40);
        $person = (int) request('person', 0);
        $from = request('from');
        $to = request('to');
        if ($module !== '') {
            $query->where('action', 'like', $module.'.%');
        }
        if ($person > 0) {
            $query->where('user_id', $person);
        }
        if ($from) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('created_at', '<=', $to);
        }

        $logs = $query->limit(50)->get();
        $users = User::whereIn('id', $logs->pluck('user_id')->filter()->unique())->pluck('name', 'id');
        $labels = [
            'opportunity.commercial_stage_changed' => 'Etapa comercial atualizada',
            'opportunity.next_action_set' => 'Próxima ação definida',
            'opportunity.archived' => 'Oportunidade arquivada',
            'opportunity.restored' => 'Oportunidade restaurada',
            'client.archived' => 'Cliente arquivado',
            'client.restored' => 'Cliente restaurado',
            'contact.archived' => 'Contato arquivado',
            'contact.restored' => 'Contato restaurado',
            'activity.completed' => 'Atividade concluída',
            'activity.reopened' => 'Atividade reaberta',
            'activity.rescheduled' => 'Atividade reagendada',
            'activity.assigned' => 'Atividade atribuída',
        ];

        return Inertia::render('History', [
            'records' => $logs->map(fn ($log) => ['id' => $log->id, 'action' => $log->action, 'label' => $labels[$log->action] ?? ucwords(str_replace(['.', '_'], [' · ', ' '], $log->action)), 'module' => str($log->action)->before('.')->toString(), 'user' => $users[$log->user_id] ?? 'Sistema', 'caseId' => $log->subject_type === Opportunity::class ? $log->subject_id : null, 'metadata' => $log->metadata, 'createdAt' => $log->created_at->format('d/m/Y H:i')]),
            'search' => $search, 'filters' => ['module' => $module, 'person' => $person ?: '', 'from' => $from ?: '', 'to' => $to ?: ''], 'case' => $opportunity?->only(['id', 'title']),
            'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'feedback' => Gate::allows(Ability::ViewPilotFeedback->value) ? PrototypeFeedback::latest()->limit(20)->get()->map(fn ($item) => ['rating' => $item->rating, 'category' => $item->category, 'comment' => $item->comment, 'createdAt' => $item->created_at->format('d/m/Y H:i')])->values() : [],
        ]);
    }

    public function team(): Response
    {
        Gate::authorize(Ability::ManageTeam->value);

        return Inertia::render('Team', ['users' => User::query()->orderBy('name')->get()->map(fn ($user) => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role, 'active' => $user->is_active, 'canApproveCommercial' => $user->can_approve_commercial])->values()]);
    }

    public function help(): Response
    {
        return Inertia::render('Help');
    }
}
