<?php

namespace App\Http\Controllers;

use App\Enums\OpportunityOrigin;
use App\Enums\OpportunityPriority;
use App\Enums\OpportunityStage;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\NextActionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class OpportunityController extends Controller
{
    public function edit(Opportunity $opportunity)
    {
        return Inertia::render('OpportunityEdit', ['opportunity' => $opportunity, 'clients' => Client::active()->with(['contacts' => fn ($query) => $query->active()])->orderBy('name')->get(), 'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name'])]);
    }

    public function store(Request $request, NextActionService $nextActions): RedirectResponse
    {
        $opportunity = DB::transaction(function () use ($request): Opportunity {
            $opportunity = Opportunity::create([
                ...$this->details($request),
                'stage' => OpportunityStage::LEAD,
                'commercial_stage' => 'lead',
                'origin' => $request->input('origin', 'other'),
                'priority' => $request->input('priority', 'normal'),
                'briefing_status' => 'not_started',
            ]);
            AuditLog::create(['user_id' => $request->user()->id, 'action' => 'opportunity.created', 'subject_type' => Opportunity::class, 'subject_id' => $opportunity->id]);

            return $opportunity;
        });

        if (filled($opportunity->next_action)) {
            $nextActions->set($opportunity, $request->user(), [
                'title' => $opportunity->next_action,
                'user_id' => $opportunity->owner_id,
                'priority' => $opportunity->priority,
                'due_at' => $opportunity->next_action_at,
            ]);
        }

        return to_route('dashboard');
    }

    public function update(Request $request, Opportunity $opportunity, NextActionService $nextActions): RedirectResponse
    {
        DB::transaction(function () use ($request, $opportunity): void {
            $locked = Opportunity::query()->whereKey($opportunity->id)->lockForUpdate()->firstOrFail();
            if ($request->filled('revision') && (int) $request->input('revision') !== (int) $locked->commercial_revision) {
                abort(409, 'O caso foi alterado por outra pessoa. Atualize antes de salvar.');
            }
            $locked->update([...$this->details($request), 'origin' => $request->input('origin', $locked->origin), 'priority' => $request->input('priority', $locked->priority), 'commercial_revision' => (int) $locked->commercial_revision + 1]);
            AuditLog::create(['user_id' => $request->user()->id, 'action' => 'opportunity.updated', 'subject_type' => Opportunity::class, 'subject_id' => $opportunity->id]);
        });

        if ($request->has('next_action')) {
            $fresh = $opportunity->fresh();
            if (filled($fresh?->next_action)) {
                $nextActions->set($fresh, $request->user(), [
                    'title' => $fresh->next_action,
                    'user_id' => $fresh->owner_id,
                    'priority' => $fresh->priority,
                    'due_at' => $fresh->next_action_at,
                ]);
            } else {
                $nextActions->clear($opportunity, $request->user());
            }
        }

        return back()->with('success', 'Dados do caso atualizados.');
    }

    private function details(Request $request): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:160', 'client_id' => ['nullable', Rule::exists('clients', 'id')->whereNull('archived_at')],
            'client_name' => 'required_without:client_id|nullable|string|max:160',
            'contact_id' => ['nullable', Rule::exists('contacts', 'id')->where('client_id', $request->input('client_id'))->whereNull('archived_at')],
            'contact_name' => 'nullable|string|max:160', 'contact_email' => 'nullable|email|max:160',
            'owner_id' => ['nullable', Rule::exists('users', 'id')->where('is_active', true)],
            'event_date' => 'nullable|date', 'location' => 'nullable|string|max:255', 'objective' => 'nullable|string|max:10000',
            'next_action' => 'nullable|string|max:255', 'next_action_at' => 'nullable|date',
            'origin' => ['nullable', Rule::enum(OpportunityOrigin::class)],
            'priority' => ['nullable', Rule::enum(OpportunityPriority::class)],
            'revision' => ['nullable', 'integer', 'min:0'],
        ]);
        $client = ! empty($data['client_id']) ? Client::active()->findOrFail($data['client_id']) : Client::active()->firstOrCreate(['name' => $data['client_name']]);
        $contact = ! empty($data['contact_id']) ? Contact::findOrFail($data['contact_id']) : null;
        if (! $contact && ! empty($data['contact_name'])) {
            $contact = $client->contacts()->firstOrCreate(['name' => $data['contact_name'], 'email' => $data['contact_email'] ?? null]);
        }

        return [...$data, 'client_id' => $client->id, 'client_name' => $client->name, 'contact_id' => $contact?->id, 'contact_name' => $contact?->name, 'contact_email' => $contact?->email, 'owner_id' => $data['owner_id'] ?? $request->user()->id];
    }

    public function updateStage(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $data = $request->validate([
            'stage' => ['required', 'string'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $target = OpportunityStage::tryFrom($data['stage']);
        abort_unless($target, 422, 'Etapa inválida.');

        $stages = collect(OpportunityStage::cases())->reject(fn (OpportunityStage $stage) => in_array($stage, [OpportunityStage::LOST, OpportunityStage::CANCELLED], true))->values();
        $currentIndex = $stages->search(fn (OpportunityStage $stage) => $stage === $opportunity->stage);
        $targetIndex = $stages->search(fn (OpportunityStage $stage) => $stage === $target);
        $blocker = null;
        if ($targetIndex !== false && $currentIndex !== false && $targetIndex > $currentIndex) {
            $blocker = match ($target) {
                OpportunityStage::QUALIFICATION => blank($opportunity->contact_name) ? 'Defina um contato principal antes de qualificar.' : null,
                OpportunityStage::BRIEFING => blank($opportunity->event_date) ? 'Defina a data do evento antes de iniciar o briefing.' : null,
                OpportunityStage::BUDGET => ! $opportunity->briefingMessages()->exists() ? 'Adicione pelo menos uma mensagem ao briefing antes de montar o orçamento.' : null,
                OpportunityStage::PROPOSAL => ! $opportunity->budgets()->where('status', 'approved')->exists() ? 'Aprove uma versão do orçamento antes de gerar a proposta.' : null,
                default => null,
            };
        }
        if ($blocker) {
            return back()->with('error', $blocker);
        }

        $from = $opportunity->stage->value;
        $opportunity->update(['stage' => $target, 'stage_note' => $data['note'] ?? null]);
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'opportunity.stage_changed',
            'subject_type' => Opportunity::class,
            'subject_id' => $opportunity->id,
            'metadata' => ['from' => $from, 'to' => $target->value, 'note' => $data['note'] ?? null],
        ]);

        return back()->with('success', 'Etapa atualizada.');
    }
}
