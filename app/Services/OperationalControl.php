<?php

namespace App\Services;

use App\Models\EventBrief;
use App\Models\Opportunity;
use App\Models\ProductionTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class OperationalControl
{
    public function readiness(Opportunity $case): array
    {
        $base = '/opportunities/'.$case->id;
        $brief = EventBrief::where('opportunity_id', $case->id)->first();
        $budget = $case->budgets()->latest('version')->first();
        $contract = $case->documents()->where('type', 'contract')->latest('version')->first();
        $tasks = $case->productionTasks()->with('assignments')->get();
        $blockers = $case->blockers()->where('status', 'open')->count();
        $unassigned = $tasks->filter(fn ($t) => ! $t->assigned_to && $t->assignments->isEmpty())->count();
        $orders = DB::table('production_service_orders')->where('opportunity_id', $case->id)->get();
        $tech = DB::table('technical_validations')->where('opportunity_id', $case->id)->get();
        $plan = DB::table('payment_plans')->where('opportunity_id', $case->id)->orderByDesc('id')->first();
        $late = DB::table('receivables')->where('opportunity_id', $case->id)->where('status', 'open')->whereDate('due_at', '<', today())->count();

        return [
            ['key' => 'briefing', 'title' => 'Briefing conferido', 'ready' => $brief?->status === 'approved' && $brief->revision === $case->briefing_revision, 'detail' => 'A versão atual precisa estar aprovada.', 'href' => $base.'/briefing'],
            ['key' => 'budget', 'title' => 'Orçamento aprovado', 'ready' => $budget?->status === 'approved', 'detail' => 'Confere a última versão do orçamento.', 'href' => $base.'/budget'],
            ['key' => 'contract', 'title' => 'Contrato registrado', 'ready' => $contract?->status === 'signed_external', 'detail' => 'Assinatura da versão atual registrada pela equipe.', 'href' => $base.'/documents'],
            ['key' => 'team', 'title' => 'Equipe e impedimentos', 'ready' => $tasks->isNotEmpty() && $unassigned === 0 && $blockers === 0 && ! $tasks->contains('status', 'blocked'), 'detail' => count($tasks).' tarefas · '.$unassigned.' sem responsável · '.$blockers.' impedimentos abertos.', 'href' => '/production/events/'.$case->id],
            ['key' => 'technical', 'title' => 'Validação técnica', 'ready' => $tech->isNotEmpty() && $tech->every(fn ($t) => $t->status === 'confirmed' && $t->confirmed_at && ! $t->invalidated_at), 'detail' => $tech->count().' registros técnicos. Ausência de registro exige conferência.', 'href' => '/production/events/'.$case->id],
            ['key' => 'suppliers', 'title' => 'Fornecedores alinhados', 'ready' => $orders->isNotEmpty() && $orders->every(fn ($o) => in_array($o->status, ['issued', 'received'])), 'detail' => $orders->whereIn('status', ['issued', 'received'])->count().' de '.$orders->count().' ordens emitidas. Emissão não comprova a entrega.', 'href' => '/production/events/'.$case->id],
            ['key' => 'finance', 'title' => 'Condições financeiras', 'ready' => $plan?->status === 'accepted' && $budget?->status === 'approved' && (int) $plan->total_cents === (int) data_get($budget->snapshot, 'totalCents', -1) && $late === 0, 'detail' => $late.' recebíveis vencidos. Exige plano de pagamento aceito.', 'href' => $base.'/finance'],
        ];
    }

    public function impact(Opportunity $case, string $change): array
    {
        $groups = [
            ['title' => 'Orçamentos', 'count' => $case->budgets()->count(), 'detail' => 'Rever valores, quantidades e validade das cotações.', 'href' => '/opportunities/'.$case->id.'/budget'],
            ['title' => 'Cotações de fornecedores', 'count' => DB::table('supplier_quotes')->where('opportunity_id', $case->id)->count(), 'detail' => 'Reconfirmar disponibilidade e condições com os fornecedores.', 'href' => '/opportunities/'.$case->id.'/budget'],
            ['title' => 'Tarefas de produção', 'count' => $case->productionTasks()->where('status', '!=', 'done')->count(), 'detail' => 'Conferir prazos, responsáveis e programação.', 'href' => '/production/events/'.$case->id],
            ['title' => 'Documentos', 'count' => $case->documents()->count(), 'detail' => 'Conferir o escopo; versões liberadas continuam preservadas.', 'href' => '/opportunities/'.$case->id.'/documents'],
        ];
        $state = [$case->only(['event_date', 'location', 'objective', 'briefing_revision', 'commercial_revision']),
            $case->budgets()->get(['id', 'revision'])->toArray(), $case->productionTasks()->get(['id', 'revision'])->toArray(),
            $case->documents()->get(['id', 'version', 'status'])->toArray(), DB::table('supplier_quotes')->where('opportunity_id', $case->id)->get(['id', 'updated_at'])->toArray()];

        return ['change' => $change, 'groups' => $groups, 'fingerprint' => hash('sha256', json_encode($state)), 'note' => 'Itens potencialmente afetados. Esta prévia orienta a revisão; não altera prazos nem preços automaticamente.'];
    }

    public function capacity(): array
    {
        $start = today();
        $end = today()->addDays(7);
        $tasks = ProductionTask::with(['assignments', 'opportunity'])->whereNotIn('status', ['done'])->whereHas('opportunity', fn ($q) => $q->whereNull('archived_at')->whereNotIn('stage', ['closed', 'lost', 'cancelled']))->get();

        return User::where('is_active', true)->orderBy('name')->get()->map(function ($user) use ($tasks, $start, $end) {
            $owned = $tasks->filter(fn ($t) => $t->assigned_to === $user->id || $t->assignments->contains('user_id', $user->id));
            $week = $owned->filter(fn ($t) => $t->scheduled_starts_at && $t->scheduled_ends_at && $t->scheduled_starts_at < $end && $t->scheduled_ends_at > $start)->values();
            $conflicts = [];
            foreach ($week as $i => $a) {
                foreach ($week as $j => $b) {
                    if ($j > $i && $a->scheduled_starts_at < $b->scheduled_ends_at && $b->scheduled_starts_at < $a->scheduled_ends_at) {
                        $conflicts[] = $a->title.' × '.$b->title;
                    }
                }
            }
            $intervals = $week->map(fn ($t) => [max($t->scheduled_starts_at->timestamp, $start->timestamp), min($t->scheduled_ends_at->timestamp, $end->timestamp)])->sortBy(fn ($v) => $v[0])->values();
            $seconds = 0;
            $lastEnd = 0;
            foreach ($intervals as [$a,$b]) {
                $seconds += max(0, $b - max($a, $lastEnd));
                $lastEnd = max($lastEnd, $b);
            }

            return ['id' => $user->id, 'name' => $user->name, 'jobTitle' => $user->job_title ?: ($user->isAdmin() ? 'Administração' : 'Operação'), 'open' => $owned->count(), 'unscheduled' => $owned->filter(fn ($t) => ! $t->scheduled_starts_at || ! $t->scheduled_ends_at)->count(), 'hours' => round($seconds / 3600, 1), 'capacityHours' => $user->weekly_capacity_minutes !== null ? round($user->weekly_capacity_minutes / 60, 1) : null, 'conflicts' => $conflicts, 'tasks' => $week->map(fn ($t) => ['id' => $t->id, 'title' => $t->title, 'project' => $t->opportunity->title, 'start' => $t->scheduled_starts_at->toIso8601String(), 'end' => $t->scheduled_ends_at->toIso8601String(), 'href' => '/production/events/'.$t->opportunity_id])->all()];
        })->all();
    }

    public function versions(Opportunity $case): array
    {
        $versions = [];
        $brief = EventBrief::where('opportunity_id', $case->id)->first();
        if ($brief) {
            foreach (DB::table('event_brief_revisions')->where('event_brief_id', $brief->id)->orderBy('revision')->get() as $r) {
                $snapshot = json_decode($r->snapshot, true);
                $fields = collect($snapshot)->except(['id', 'event_brief_id', 'opportunity_id', 'created_at', 'updated_at', 'legacy_snapshot', 'revision', 'approved_at', 'approved_by', 'completeness_score', 'missing_critical', 'status'])->all();
                $versions[] = ['id' => 'briefing-'.$r->id, 'kind' => 'briefing', 'label' => 'Briefing · revisão '.$r->revision, 'at' => $r->created_at, 'fields' => $fields];
            }
        }
        foreach ($case->budgets()->with('items')->orderBy('version')->get() as $b) {
            $fields = ['finalidade' => $b->purpose, 'observacoes' => $b->notes];
            foreach ($b->items as $i => $item) {
                $fields['item_'.($i + 1)] = $item->only(['description', 'category', 'quantity', 'unit_cost_cents', 'management_bps', 'administration_bps', 'tax_cents', 'contingency_cents', 'margin_percent', 'sell_total_cents']);
            }
            $versions[] = ['id' => 'budget-'.$b->id, 'kind' => 'budget', 'label' => 'Orçamento · versão '.$b->version, 'at' => $b->created_at->toIso8601String(), 'fields' => $fields];
        }
        foreach ($case->documents()->orderBy('version')->get() as $d) {
            $versions[] = ['id' => 'document-'.$d->id, 'kind' => $d->type, 'label' => ($d->type === 'contract' ? 'Contrato' : 'Proposta').' · versão '.$d->version, 'at' => $d->created_at->toIso8601String(), 'fields' => ['titulo' => $d->title, 'finalidade' => $d->purpose, ...($d->release_snapshot['sections'] ?? $d->content['sections'] ?? [])]];
        }

        return $versions;
    }
}
