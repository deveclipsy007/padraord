<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Opportunity;
use App\Models\ProductionTask;
use App\Models\PrototypeFeedback;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class OperationalPagesController extends Controller
{
    public function pipeline(): Response
    {
        return Inertia::render('Pipeline', ['opportunities' => Opportunity::query()->latest('updated_at')->get()->map(fn ($item) => ['id' => $item->id, 'title' => $item->title, 'clientName' => $item->client_name, 'stage' => $item->stage->value, 'stageLabel' => $item->stage->label(), 'eventDate' => $item->event_date?->format('d/m/Y'), 'estimatedValueCents' => $item->estimated_value_cents])->values()]);
    }

    public function briefings(): Response
    {
        return Inertia::render('Briefings', ['opportunities' => Opportunity::query()->whereIn('briefing_status', ['not_started', 'processing', 'awaiting_review'])->latest('updated_at')->get()->map(fn ($item) => ['id' => $item->id, 'title' => $item->title, 'clientName' => $item->client_name, 'status' => $item->briefing_status, 'stage' => $item->stage->label()])->values()]);
    }

    public function agenda(): Response
    {
        return Inertia::render('Agenda', [
            'activities' => Activity::with(['opportunity', 'assignee'])->orderBy('due_at')->get(),
            'tasks' => ProductionTask::with('opportunity')->orderBy('due_date')->get(),
            'events' => Opportunity::whereNotNull('event_date')->orderBy('event_date')->get(['id', 'title', 'event_date']),
            'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'opportunities' => Opportunity::orderBy('title')->get(['id', 'title']),
        ]);
    }

    public function clients(): Response
    {
        return Inertia::render('Clients', ['clients' => Client::withCount('opportunities')->orderBy('name')->get()->map(fn ($client) => ['id' => $client->id, 'name' => $client->name, 'industry' => $client->industry, 'opportunitiesCount' => $client->opportunities_count])->values()]);
    }

    public function history(?Opportunity $opportunity = null): Response
    {
        $query = AuditLog::query()->latest();
        if ($opportunity?->exists) {
            $query->where('subject_type', Opportunity::class)->where('subject_id', $opportunity->id);
        }
        if (! request()->user()->isAdmin()) {
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

        return Inertia::render('History', [
            'records' => $query->limit(100)->get()->map(fn ($log) => ['id' => $log->id, 'action' => $log->action, 'user' => User::find($log->user_id)?->name ?? 'Sistema', 'caseId' => $log->subject_type === Opportunity::class ? $log->subject_id : null, 'metadata' => $log->metadata, 'createdAt' => $log->created_at->format('d/m/Y H:i')]),
            'search' => $search, 'filters' => ['module' => $module, 'person' => $person ?: '', 'from' => $from ?: '', 'to' => $to ?: ''], 'case' => $opportunity?->only(['id', 'title']),
            'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'feedback' => request()->user()->isAdmin() ? PrototypeFeedback::latest()->limit(20)->get()->map(fn ($item) => ['rating' => $item->rating, 'category' => $item->category, 'comment' => $item->comment, 'createdAt' => $item->created_at->format('d/m/Y H:i')])->values() : [],
        ]);
    }

    public function team(): Response
    {
        abort_unless(request()->user()?->isAdmin(), 403);

        return Inertia::render('Team', ['users' => User::query()->orderBy('name')->get()->map(fn ($user) => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role, 'active' => $user->is_active, 'canApproveCommercial' => $user->can_approve_commercial])->values()]);
    }

    public function help(): Response
    {
        return Inertia::render('Help');
    }
}
