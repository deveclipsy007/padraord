<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\OperationalAutomations;
use App\Services\OperationalControl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class OperationalControlController extends Controller
{
    public function index(Request $request, OperationalControl $control, OperationalAutomations $automations)
    {
        $pending = DB::table('client_pending_items as p')->join('opportunities as o', 'o.id', '=', 'p.opportunity_id')->join('users as u', 'u.id', '=', 'p.owner_id')->whereNull('o.archived_at')->where('p.status', 'open')->orderBy('p.due_date')->get(['p.*', 'o.title as project', 'u.name as owner']);

        return Inertia::render('Operations', ['pending' => $pending, 'capacity' => $control->capacity(), 'rules' => collect(OperationalAutomations::RULES)->map(fn ($title, $key) => ['key' => $key, 'title' => $title, 'record' => DB::table('operational_rules')->where('rule_key', $key)->first()])->values(), 'runs' => DB::table('operational_rule_runs as r')->join('operational_rules as a', 'a.id', '=', 'r.operational_rule_id')->leftJoin('activities as t', 't.id', '=', 'r.activity_id')->orderByDesc('r.id')->limit(30)->get(['r.id', 'r.created_at', 'r.undone_at', 't.title', 'a.rule_key']), 'projects' => Opportunity::whereNull('archived_at')->whereNotIn('stage', ['closed', 'lost', 'cancelled'])->get(['id', 'title'])]);
    }

    public function show(Request $request, Opportunity $opportunity, OperationalControl $control)
    {
        $links = DB::table('client_portal_links')->where('opportunity_id', $opportunity->id)->orderByDesc('id')->get();

        return Inertia::render('ProjectControl', [
            'opportunity' => $opportunity->only(['id', 'title', 'client_name']),
            'pending' => DB::table('client_pending_items as p')->join('users as u', 'u.id', '=', 'p.owner_id')->where('p.opportunity_id', $opportunity->id)->orderBy('p.status')->orderBy('p.due_date')->get(['p.*', 'u.name as owner']),
            'users' => User::where('is_active', true)->get(['id', 'name']),
            'readiness' => $control->readiness($opportunity), 'versions' => $control->versions($opportunity),
            'portalLinks' => $links->map(fn ($p) => ['id' => $p->id, 'created_at' => $p->created_at, 'expires_at' => $p->expires_at, 'revoked_at' => $p->revoked_at]),
            'portalResponses' => DB::table('client_portal_responses')->whereIn('client_portal_link_id', $links->pluck('id'))->orderByDesc('id')->get(),
            'portalUploads' => DB::table('client_portal_uploads')->whereIn('client_portal_link_id', $links->pluck('id'))->get(['id', 'name', 'original_name', 'created_at']),
            'documents' => $opportunity->documents()->where('type', 'proposal')->whereIn('status', ['sent', 'accepted', 'changes_requested'])->whereNotNull('release_hash')->get(['id', 'title', 'version']),
            'portalUrl' => session('portal_url'),
            'canPublish' => $request->user()->isAdmin() || $opportunity->owner_id === $request->user()->id,
        ]);
    }

    public function pending(Request $request, Opportunity $opportunity)
    {
        $data = $request->validate(['title' => 'required|string|max:180', 'kind' => 'required|in:approval,document,answer,payment', 'awaiting' => 'required|in:client,team', 'owner_id' => ['required', Rule::exists('users', 'id')->where('is_active', true)], 'due_date' => 'nullable|date', 'next_contact_at' => 'nullable|date']);
        DB::transaction(function () use ($data, $request, $opportunity) {
            $id = DB::table('client_pending_items')->insertGetId([...$data, 'opportunity_id' => $opportunity->id, 'created_at' => now(), 'updated_at' => now()]);
            $this->audit($request, $opportunity, 'pending.created', ['id' => $id]);
        });

        return back()->with('success', 'Pendência registrada com responsável.');
    }

    public function resolve(Request $request, Opportunity $opportunity, int $pending)
    {
        $data = $request->validate(['revision' => 'required|integer|min:0', 'status' => 'required|in:open,resolved', 'resolution' => 'nullable|string|max:2000']);
        DB::transaction(function () use ($data, $request, $opportunity, $pending) {
            $record = DB::table('client_pending_items')->where('id', $pending)->where('opportunity_id', $opportunity->id)->lockForUpdate()->first();
            abort_unless($record, 404);
            if ($record->revision != $data['revision']) {
                throw ValidationException::withMessages(['revision' => 'A pendência mudou. Atualize a página.']);
            }
            DB::table('client_pending_items')->where('id', $pending)->update(['status' => $data['status'], 'resolution' => $data['resolution'] ?? null, 'revision' => $record->revision + 1, 'updated_at' => now()]);
            $this->audit($request, $opportunity, 'pending.'.$data['status'], ['id' => $pending]);
        });

        return back()->with('success', 'Pendência atualizada.');
    }

    public function impact(Request $request, Opportunity $opportunity, OperationalControl $control)
    {
        $data = $request->validate(['change' => 'required|in:date,scope,audience,location']);

        return response()->json($control->impact($opportunity, $data['change']));
    }

    public function rulePreview(string $key, OperationalAutomations $service)
    {
        return response()->json($service->preview($key));
    }

    public function ruleEnable(Request $request, string $key, OperationalAutomations $service)
    {
        $data = $request->validate(['fingerprint' => 'required|string', 'enabled' => 'required|boolean']);
        DB::transaction(function () use ($data, $key, $service, $request) {
            $preview = $service->preview($key);
            if (! hash_equals($preview['fingerprint'], $data['fingerprint'])) {
                throw ValidationException::withMessages(['automation' => 'A prévia mudou. Confira novamente antes de ativar.']);
            }
            DB::table('operational_rules')->updateOrInsert(['rule_key' => $key], ['enabled' => $data['enabled'], 'enabled_by' => $request->user()->id, 'updated_at' => now(), 'created_at' => now()]);
            AuditLog::create(['user_id' => $request->user()->id, 'action' => 'automation.configured', 'subject_type' => 'OperationalRule', 'subject_id' => DB::table('operational_rules')->where('rule_key', $key)->value('id'), 'metadata' => ['key' => $key, 'enabled' => $data['enabled']]]);
        });

        return back()->with('success', $data['enabled'] ? 'Regra ativada. Use Executar agora ou aguarde o agendador.' : 'Regra pausada.');
    }

    public function ruleRun(string $key, OperationalAutomations $service)
    {
        abort_unless(isset(OperationalAutomations::RULES[$key]), 404);
        $count = $service->run($key);

        return back()->with('success', $count.' tarefas criadas. Nenhuma mensagem externa foi enviada.');
    }

    public function ruleUndo(Request $request, int $run, OperationalAutomations $service)
    {
        $service->undo($run, $request->user());

        return back()->with('success', 'Tarefa automática cancelada. O histórico foi preservado.');
    }

    public function capacityLimit(Request $request, User $user)
    {
        $data = $request->validate(['hours' => 'required|numeric|min:0.5|max:168']);
        $user->forceFill(['weekly_capacity_minutes' => (int) round($data['hours'] * 60)])->save();
        AuditLog::create(['user_id' => $request->user()->id, 'action' => 'team.capacity_changed', 'subject_type' => User::class, 'subject_id' => $user->id, 'metadata' => ['hours' => $data['hours']]]);

        return back()->with('success', 'Capacidade semanal atualizada.');
    }

    public function focus(Request $request)
    {
        $data = $request->validate(['focus' => 'required|in:management,production,finance,commercial']);
        if ($data['focus'] === 'management') {
            abort_unless($request->user()->isAdmin(), 403);
        }
        $request->user()->forceFill(['workspace_focus' => $data['focus']])->save();

        return back()->with('success', 'Seu foco de trabalho foi atualizado.');
    }

    private function audit(Request $request, Opportunity $case, string $action, array $metadata): void
    {
        AuditLog::create(['user_id' => $request->user()->id, 'action' => $action, 'subject_type' => Opportunity::class, 'subject_id' => $case->id, 'metadata' => $metadata]);
    }
}
