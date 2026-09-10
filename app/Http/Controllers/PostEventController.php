<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Opportunity;
use App\Models\PostEventReport;
use App\Services\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PostEventController extends Controller
{
    public function show(Opportunity $opportunity): Response
    {
        $report = $opportunity->postEventReport;

        return Inertia::render('PostEvent', ['opportunity' => ['id' => $opportunity->id, 'title' => $opportunity->title, 'clientName' => $opportunity->client_name], 'report' => ['summary' => $report?->summary, 'learnings' => $report?->learnings, 'occurrences' => $report?->occurrences ?? [], 'actualTotalCents' => $report?->actual_total_cents, 'plannedTotalCents' => $report?->planned_total_cents, 'supplierEvaluations' => $report?->supplier_evaluations ?? [], 'closureItems' => $report?->closure_items ?? [], 'status' => $report?->status ?? 'draft', 'closedAt' => $report?->closed_at?->toIso8601String()]]);
    }

    public function store(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $data = $request->validate([
            'summary' => ['nullable', 'string', 'max:5000'],
            'learnings' => ['nullable', 'string', 'max:5000'],
            'planned_total' => ['nullable', 'string', 'max:20'],
            'planned_total_cents' => ['nullable', 'integer', 'min:0'],
            'actual_total' => ['nullable', 'string', 'max:20'],
            'actual_total_cents' => ['nullable', 'integer', 'min:0'],
            'occurrences' => ['nullable', 'array'],
            'supplier_evaluations' => ['nullable', 'array'],
            'supplier_evaluations.*.supplier' => ['required_with:supplier_evaluations', 'string', 'max:180'],
            'supplier_evaluations.*.rating' => ['required_with:supplier_evaluations', 'integer', 'between:1,5'],
            'supplier_evaluations.*.notes' => ['nullable', 'string', 'max:2000'],
            'closure_items' => ['nullable', 'array'],
            'closure_items.*.title' => ['required', 'string', 'max:180'],
            'closure_items.*.status' => ['required', 'in:pending,assigned,resolved'],
            'closure_items.*.assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'closure_items.*.due_at' => ['nullable', 'date'],
            'closure_items.*.justification' => ['nullable', 'string', 'max:2000'],
            'occurrence_description' => ['nullable', 'string', 'max:1000'],
            'occurrence_solution' => ['nullable', 'string', 'max:2000'],
            'occurrence_extra_total' => ['nullable', 'string', 'max:20'],
            'status' => ['required', 'in:draft,review,closed'],
        ]);
        $requestedStatus = $data['status'];
        $closureError = null;
        if ($requestedStatus === 'closed') {
            if (blank($data['summary'] ?? null) || blank($data['learnings'] ?? null)) {
                $closureError = 'Registre o resumo e os aprendizados antes de encerrar o evento.';
            } elseif ($opportunity->productionTasks()->where('status', '!=', 'done')->exists()) {
                $closureError = 'Conclua ou atribua todas as tarefas de produção antes de encerrar.';
            } else {
                foreach ($data['closure_items'] ?? [] as $item) {
                    $assigned = $item['status'] === 'assigned' && filled($item['assigned_to'] ?? null) && filled($item['due_at'] ?? null) && filled($item['justification'] ?? null);
                    if ($item['status'] === 'pending' || ($item['status'] === 'assigned' && ! $assigned)) {
                        $closureError = 'Resolva cada pendência final ou atribua responsável, prazo e justificativa antes de encerrar.';
                        break;
                    }
                }
            }
        }
        if (filled($data['planned_total'] ?? null)) {
            $data['planned_total_cents'] = Money::decimal($data['planned_total'], 'planned_total');
        }
        if (filled($data['actual_total'] ?? null)) {
            $data['actual_total_cents'] = Money::decimal($data['actual_total'], 'actual_total');
        }
        if (filled($data['occurrence_description'] ?? null)) {
            $occurrences = $opportunity->postEventReport?->occurrences ?? [];
            $occurrences[] = [
                'description' => trim($data['occurrence_description']),
                'solution' => trim((string) ($data['occurrence_solution'] ?? '')),
                'extraCents' => filled($data['occurrence_extra_total'] ?? null) ? Money::decimal($data['occurrence_extra_total'], 'occurrence_extra_total') : 0,
                'at' => now()->toIso8601String(),
            ];
            $data['occurrences'] = $occurrences;
        }
        unset($data['actual_total']);
        unset($data['planned_total']);
        unset($data['occurrence_description'], $data['occurrence_solution'], $data['occurrence_extra_total']);
        $existing = $opportunity->postEventReport;
        if ($existing?->status === 'closed') {
            if ($data['status'] !== 'closed') {
                return back()->withErrors(['status' => 'A memória está encerrada. Reabra o registro explicitamente para editar.']);
            }
            $same = trim((string) ($data['summary'] ?? '')) === (string) $existing->summary
                && trim((string) ($data['learnings'] ?? '')) === (string) $existing->learnings
                && ($data['planned_total_cents'] ?? null) === $existing->planned_total_cents
                && ($data['actual_total_cents'] ?? null) === $existing->actual_total_cents
                && ($data['occurrences'] ?? $existing->occurrences) === $existing->occurrences
                && ($data['supplier_evaluations'] ?? []) === ($existing->supplier_evaluations ?? [])
                && ($data['closure_items'] ?? []) === ($existing->closure_items ?? []);
            if ($same) {
                return back()->with('success', 'Memória já encerrada; nenhum novo registro foi criado.');
            }

            return back()->withErrors(['status' => 'A memória encerrada é imutável. Reabra antes de alterar seu conteúdo.']);
        }
        if ($closureError !== null) {
            $data['status'] = 'draft';
            $report = $opportunity->postEventReport()->updateOrCreate([], $data);
            AuditLog::create(['user_id' => $request->user()->id, 'action' => 'post_event.saved', 'subject_type' => PostEventReport::class, 'subject_id' => $report->id, 'metadata' => ['opportunity_id' => $opportunity->id, 'status' => 'draft', 'closure_blocked' => true]]);

            return back()->withErrors(['status' => $closureError]);
        }
        $report = $opportunity->postEventReport()->updateOrCreate([], $data + ($data['status'] === 'closed' ? ['closed_by' => $request->user()->id, 'closed_at' => now()] : ['closed_by' => null, 'closed_at' => null]));
        if ($data['status'] === 'closed') {
            $opportunity->update(['stage' => 'closed']);
            AuditLog::create(['user_id' => $request->user()->id, 'action' => 'post_event.closed', 'subject_type' => PostEventReport::class, 'subject_id' => $report->id, 'metadata' => ['opportunity_id' => $opportunity->id]]);
        } else {
            AuditLog::create(['user_id' => $request->user()->id, 'action' => 'post_event.saved', 'subject_type' => PostEventReport::class, 'subject_id' => $report->id, 'metadata' => ['opportunity_id' => $opportunity->id, 'status' => $data['status']]]);
        }

        return back()->with('success', 'Memória do evento salva.');
    }

    public function reopen(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:2000']]);
        $report = $opportunity->postEventReport;
        if (! $report || $report->status !== 'closed') {
            return back()->withErrors(['status' => 'Somente uma memória encerrada pode ser reaberta.']);
        }
        $report->update(['status' => 'review', 'closed_by' => null, 'closed_at' => null]);
        if ($opportunity->stage?->value === 'closed') {
            $opportunity->update(['stage' => 'post_event']);
        }
        AuditLog::create(['user_id' => $request->user()->id, 'action' => 'post_event.reopened', 'subject_type' => PostEventReport::class, 'subject_id' => $report->id, 'metadata' => ['opportunity_id' => $opportunity->id, 'reason' => $data['reason']]]);

        return back()->with('success', 'Memória reaberta para revisão.');
    }
}
