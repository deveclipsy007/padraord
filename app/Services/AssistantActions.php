<?php

namespace App\Services;

use App\AI\AiConfiguration;
use App\Models\Activity;
use App\Models\AssistantPreview;
use App\Models\AuditLog;
use App\Models\Opportunity;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AssistantActions
{
    public const KINDS = ['supplier.create', 'inquiry.create', 'quote.create', 'task.create', 'budget.import_quote', 'budget.import_preparation'];

    public function preview(User $user, array $input): AssistantPreview
    {
        $v = Validator::make($input, ['message' => 'required|string|max:12000', 'opportunity_id' => 'nullable|exists:opportunities,id', 'supplier_id' => 'nullable|exists:suppliers,id', 'actions' => 'required|array|min:1|max:10', 'actions.*.kind' => 'required|in:'.implode(',', self::KINDS), 'actions.*.data' => 'required|array'])->validate();
        $o = isset($v['opportunity_id']) ? Opportunity::findOrFail($v['opportunity_id']) : null;
        $s = isset($v['supplier_id']) ? Supplier::findOrFail($v['supplier_id']) : null;

        return AssistantPreview::create(['user_id' => $user->id, 'mode' => app(AiConfiguration::class)->setting()->mode, 'message' => $v['message'], 'context' => ['opportunity_id' => $o?->id, 'supplier_id' => $s?->id, 'case_hash' => $o ? $this->caseHash($o) : null, 'budget_id' => $o?->budgets()->latest('version')->first()?->id, 'briefing_revision' => $o?->briefing_revision, 'budget_revision' => $o?->budgets()->latest('version')->first()?->revision ?? 0, 'supplier_revision' => $s?->revision, 'case_title' => $o?->title, 'supplier_name' => $s?->name], 'actions' => $v['actions'], 'status' => 'preview']);
    }

    public function confirm(AssistantPreview $preview, User $user): array
    {
        abort_unless($preview->user_id === $user->id, 403);

        return DB::transaction(function () use ($preview, $user) {
            DB::table('assistant_previews')->where('id', $preview->id)->update(['updated_at' => now()]);
            $p = $preview->fresh();
            if ($p->status === 'confirmed') {
                return $p->result;
            }
            if ($p->created_at->lt(now()->subDay())) {
                throw ValidationException::withMessages(['preview' => 'Prévia expirada. Gere novamente para conferir dados atuais.']);
            }
            $c = $p->context;
            $o = $c['opportunity_id'] ? Opportunity::whereKey($c['opportunity_id'])->lockForUpdate()->firstOrFail() : null;
            $s = $c['supplier_id'] ? Supplier::whereKey($c['supplier_id'])->lockForUpdate()->firstOrFail() : null;
            if ($o && ($this->caseHash($o) !== ($c['case_hash'] ?? null) || $o->budgets()->latest('version')->first()?->id !== ($c['budget_id'] ?? null))) {
                throw ValidationException::withMessages(['preview' => 'O caso ou a versão do orçamento mudou. Gere uma nova prévia.']);
            }
            if (($o && ($o->briefing_revision !== $c['briefing_revision'] || ($o->budgets()->latest('version')->first()?->revision ?? 0) !== $c['budget_revision'])) || ($s && (int) $s->revision !== (int) $c['supplier_revision'])) {
                throw ValidationException::withMessages(['preview' => 'O contexto mudou depois da prévia. Gere uma nova comparação.']);
            }
            if ($p->mode === 'demo') {
                $result = ['demo' => true, 'message' => 'Simulação confirmada. Nenhum registro operacional foi criado.', 'links' => []];
                $p->update(['status' => 'confirmed', 'result' => $result]);

                return $result;
            }
            $links = [];
            $supplierOps = app(SupplierOperations::class);
            $budgetRevision = $c['budget_revision'];
            foreach ($p->actions as $index => $action) {
                $d = $action['data'];
                switch ($action['kind']) {
                    case 'supplier.create':$created = $supplierOps->save($d, $user);
                        $links[] = ['label' => $created->name, 'href' => '/suppliers/'.$created->id];
                        break;
                    case 'inquiry.create':case 'quote.create':if (! $o || ! $s) {
                        throw ValidationException::withMessages(['context' => 'Selecione caso e fornecedor.']);
                    }$d['opportunity_id'] = $o->id;
                        if ($action['kind'] === 'quote.create') {
                            $d['evidence'] = $p->message;
                            $supplierOps->quote($d, $user, $s);
                        } else {
                            $supplierOps->inquiry($d, $user, $s);
                        }$links[] = ['label' => 'Cotações do caso', 'href' => '/suppliers?tab=quotes&opportunity_id='.$o->id];
                        break;
                    case 'task.create':$data = Validator::make($d, ['title' => 'required|string|max:180', 'description' => 'nullable|string|max:5000', 'due_at' => 'nullable|date', 'priority' => 'nullable|in:low,normal,high'])->validate();
                        Activity::create($data + ['opportunity_id' => $o?->id, 'user_id' => $user->id, 'type' => 'task', 'status' => 'todo']);
                        $links[] = ['label' => 'Tarefas e agenda', 'href' => '/agenda'];
                        break;
                    case 'budget.import_quote':case 'budget.import_preparation':if (! $o) {
                        throw ValidationException::withMessages(['context' => 'Selecione o caso.']);
                    }$data = $d;
                        $data['revision'] = $budgetRevision;
                        $data['request_key'] = 'assistant-'.$p->id.'-'.$index;
                        $result = app(BudgetIntake::class)->import($o, $user, $data);
                        $budgetRevision = $result['revision'];
                        $links[] = ['label' => 'Orçamento atualizado', 'href' => $result['href']];
                        break;
                    default:throw ValidationException::withMessages(['action' => 'Ação não permitida.']);
                }
            }
            $result = ['demo' => false, 'message' => 'Alterações confirmadas e registradas.', 'links' => $links];
            $p->update(['status' => 'confirmed', 'result' => $result]);
            AuditLog::create(['user_id' => $user->id, 'action' => 'assistant.confirmed', 'subject_type' => AssistantPreview::class, 'subject_id' => $p->id, 'metadata' => ['actions' => array_column($p->actions, 'kind'), 'context' => $c]]);

            return $result;
        }, 3);
    }

    private function caseHash(Opportunity $o): string
    {
        return hash('sha256', json_encode(collect($o->getAttributes())->except(['updated_at', 'last_viewed_at'])->all()));
    }
}
