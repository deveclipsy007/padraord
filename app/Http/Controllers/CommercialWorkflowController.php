<?php

namespace App\Http\Controllers;

use App\Enums\CommercialStage;
use App\Enums\OpportunityPriority;
use App\Models\AuditLog;
use App\Models\Opportunity;
use App\Services\CommercialStageTransitionService;
use App\Services\NextActionService;
use App\Services\QualificationService;
use App\Services\RecordArchiveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CommercialWorkflowController extends Controller
{
    public function qualification(Request $request, Opportunity $opportunity, QualificationService $service): RedirectResponse
    {
        $data = $request->validate([
            'revision' => ['nullable', 'integer', 'min:0'],
            'need_summary' => ['nullable', 'string', 'max:10000'],
            'decision_maker_status' => ['required', Rule::in(['unknown', 'identified', 'not_applicable'])],
            'decision_maker_contact_id' => ['nullable', Rule::exists('contacts', 'id')->whereNull('archived_at')],
            'event_date_status' => ['required', Rule::in(['unknown', 'estimated', 'confirmed'])],
            'budget_status' => ['required', Rule::in(['unknown', 'range', 'confirmed'])],
            'fit_status' => ['required', Rule::in(['unknown', 'low', 'medium', 'high'])],
            'status' => ['required', Rule::in(['not_started', 'in_progress', 'qualified', 'disqualified'])],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);
        $service->save($opportunity, $request->user(), $data);

        return back()->with('success', 'Checklist de qualificação salva.');
    }

    public function stage(Request $request, Opportunity $opportunity, CommercialStageTransitionService $service): RedirectResponse
    {
        $data = $request->validate([
            'to' => ['required', Rule::enum(CommercialStage::class)],
            'revision' => ['required', 'integer', 'min:0'],
            'reason_category' => ['nullable', 'string', 'max:40'],
            'reason_note' => ['nullable', 'string', 'max:1000'],
            'evidence' => ['nullable', 'string', 'max:5000'],
            'source' => ['nullable', Rule::in(['commercial', 'journey'])],
        ]);
        $service->transition($opportunity, $request->user(), $data);

        return back()->with('success', 'Etapa comercial atualizada.');
    }

    public function bulkPriority(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'opportunity_ids' => ['required', 'array', 'min:1', 'max:100'],
            'opportunity_ids.*' => ['required', 'integer', 'distinct', Rule::exists('opportunities', 'id')],
            'revisions' => ['required', 'array'],
            'revisions.*' => ['required', 'integer', 'min:0'],
            'priority' => ['required', Rule::enum(OpportunityPriority::class)],
        ]);
        $ids = collect($data['opportunity_ids'])->map(fn ($id): int => (int) $id)->values();

        DB::transaction(function () use ($data, $ids, $request): void {
            $cases = Opportunity::query()->whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');
            if ($cases->count() !== $ids->count() || $cases->contains(fn (Opportunity $case): bool => $case->archived_at !== null)) {
                throw ValidationException::withMessages(['opportunity_ids' => 'Atualize a lista antes de aplicar uma ação em lote.']);
            }
            foreach ($ids as $id) {
                $case = $cases->get($id);
                if (! $case || (int) ($data['revisions'][$id] ?? -1) !== (int) $case->commercial_revision) {
                    throw ValidationException::withMessages(['revisions' => 'Um ou mais casos foram alterados. Atualize a lista antes de aplicar a prioridade.']);
                }
            }
            foreach ($ids as $id) {
                /** @var Opportunity $case */
                $case = $cases->get($id);
                $from = $case->priority instanceof OpportunityPriority ? $case->priority->value : (string) $case->priority;
                $case->update([
                    'priority' => $data['priority'],
                    'commercial_revision' => (int) $case->commercial_revision + 1,
                ]);
                AuditLog::create([
                    'user_id' => $request->user()->id,
                    'action' => 'opportunity.priority_changed',
                    'subject_type' => Opportunity::class,
                    'subject_id' => $case->id,
                    'metadata' => [
                        'from' => $from,
                        'to' => $data['priority'],
                        'revision' => $case->commercial_revision,
                        'source' => 'pipeline',
                    ],
                ]);
            }
        });

        $count = $ids->count();

        return back()->with('success', $count === 1 ? 'Prioridade atualizada.' : "Prioridade atualizada em {$count} casos.");
    }

    public function undoBulkPriority(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'changes' => ['required', 'array', 'min:1', 'max:100'],
            'changes.*.id' => ['required', 'integer', 'distinct', Rule::exists('opportunities', 'id')],
            'changes.*.priority' => ['required', Rule::enum(OpportunityPriority::class)],
            'changes.*.revision' => ['required', 'integer', 'min:0'],
        ]);
        $changes = collect($data['changes'])->keyBy(fn (array $change): int => (int) $change['id']);
        $ids = $changes->keys()->map(fn ($id): int => (int) $id)->values();

        DB::transaction(function () use ($changes, $ids, $request): void {
            $cases = Opportunity::query()->whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');
            if ($cases->count() !== $ids->count() || $cases->contains(fn (Opportunity $case): bool => $case->archived_at !== null)) {
                throw ValidationException::withMessages(['changes' => 'Atualize a lista antes de desfazer esta alteração.']);
            }
            foreach ($ids as $id) {
                $case = $cases->get($id);
                $change = $changes->get($id);
                if (! $case || ! $change || (int) $change['revision'] !== (int) $case->commercial_revision) {
                    throw ValidationException::withMessages(['changes' => 'Um ou mais casos foram alterados. A ação não pode mais ser desfeita.']);
                }
            }
            foreach ($ids as $id) {
                /** @var Opportunity $case */
                $case = $cases->get($id);
                $change = $changes->get($id);
                $from = $case->priority instanceof OpportunityPriority ? $case->priority->value : (string) $case->priority;
                $case->update([
                    'priority' => $change['priority'],
                    'commercial_revision' => (int) $case->commercial_revision + 1,
                ]);
                AuditLog::create([
                    'user_id' => $request->user()->id,
                    'action' => 'opportunity.priority_changed',
                    'subject_type' => Opportunity::class,
                    'subject_id' => $case->id,
                    'metadata' => [
                        'from' => $from,
                        'to' => $change['priority'],
                        'revision' => $case->commercial_revision,
                        'source' => 'pipeline.undo',
                    ],
                ]);
            }
        });

        $count = $ids->count();

        return back()->with('success', $count === 1 ? 'Alteração de prioridade desfeita.' : "Alteração de prioridade desfeita em {$count} casos.");
    }

    public function nextAction(Request $request, Opportunity $opportunity, NextActionService $service): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:10000'],
            'user_id' => ['nullable', Rule::exists('users', 'id')->where('is_active', true)],
            'priority' => ['nullable', Rule::enum(OpportunityPriority::class)],
            'due_at' => ['nullable', 'date'],
        ]);
        $service->set($opportunity, $request->user(), $data);

        return back()->with('success', 'Próxima ação definida.');
    }

    public function archive(Request $request, Opportunity $opportunity, RecordArchiveService $service): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $service->opportunity($opportunity, $request->user(), $data['reason']);

        return back()->with('success', 'Oportunidade arquivada; histórico preservado.');
    }

    public function restore(Request $request, Opportunity $opportunity, RecordArchiveService $service): RedirectResponse
    {
        $service->restore($opportunity, $request->user());

        return back()->with('success', 'Oportunidade restaurada.');
    }
}
