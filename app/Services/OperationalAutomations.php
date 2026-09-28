<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class OperationalAutomations
{
    public const RULES = ['overdue' => 'Acompanhar pendência vencida', 'contract' => 'Preparar produção após contrato assinado'];

    public function preview(string $key): array
    {
        abort_unless(isset(self::RULES[$key]), 404);
        if ($key === 'overdue') {
            $rows = DB::table('client_pending_items as p')->join('opportunities as o', 'o.id', '=', 'p.opportunity_id')->whereNull('o.archived_at')->whereNotIn('o.stage', ['closed', 'lost', 'cancelled'])->where('p.status', 'open')->whereDate('p.due_date', '<', today())->orderBy('p.id')->get(['p.*', 'o.title as project'])->map(fn ($p) => ['source' => 'pending-'.$p->id, 'opportunity_id' => $p->opportunity_id, 'owner_id' => $p->owner_id, 'project' => $p->project, 'title' => 'Acompanhar: '.$p->title, 'reason' => 'Pendência vencida em '.$p->due_date])->all();
        } else {
            $rows = Opportunity::whereNull('archived_at')->whereNotIn('stage', ['closed', 'lost', 'cancelled'])->with(['documents' => fn ($q) => $q->where('type', 'contract')->orderByDesc('version')])->orderBy('id')->get()->filter(fn ($c) => $c->documents->first()?->status === 'signed_external')->map(fn ($c) => ['source' => 'contract-'.$c->documents->first()->id, 'opportunity_id' => $c->id, 'owner_id' => $c->owner_id, 'project' => $c->title, 'title' => 'Preparar checklist de produção · '.$c->title, 'reason' => 'Contrato com assinatura registrada. Revisar escopo, equipe e fornecedores.'])->values()->all();
        }
        $rule = DB::table('operational_rules')->where('rule_key', $key)->first();
        $done = $rule ? DB::table('operational_rule_runs')->where('operational_rule_id', $rule->id)->pluck('source_key')->all() : [];
        $rows = array_values(array_filter($rows, fn ($r) => ! in_array($r['source'], $done, true)));

        return ['key' => $key, 'title' => self::RULES[$key], 'rows' => $rows, 'fingerprint' => hash('sha256', json_encode($rows))];
    }

    public function run(string $key): int
    {
        return DB::transaction(function () use ($key) {
            $rule = DB::table('operational_rules')->where('rule_key', $key)->lockForUpdate()->first();
            if (! $rule || ! $rule->enabled) {
                return 0;
            }
            $actor = User::find($rule->enabled_by);
            if (! $actor?->is_active || ! $actor->isAdmin()) {
                return 0;
            }
            $preview = $this->preview($key);
            $count = 0;
            foreach ($preview['rows'] as $row) {
                $owner = User::where('id', $row['owner_id'])->where('is_active', true)->value('id') ?: $actor->id;
                $activity = Activity::create(['opportunity_id' => $row['opportunity_id'], 'user_id' => $owner, 'title' => $row['title'], 'description' => $row['reason'], 'type' => 'task', 'priority' => 'high', 'status' => 'todo', 'due_at' => now()->endOfDay()]);
                DB::table('operational_rule_runs')->insert(['operational_rule_id' => $rule->id, 'source_key' => $row['source'], 'activity_id' => $activity->id, 'original' => json_encode($activity->fresh()->getAttributes()), 'created_at' => now(), 'updated_at' => now()]);
                AuditLog::create(['user_id' => $actor->id, 'action' => 'automation.activity_created', 'subject_type' => Opportunity::class, 'subject_id' => $row['opportunity_id'], 'metadata' => ['rule' => $key, 'activity_id' => $activity->id]]);
                $count++;
            }
            DB::table('operational_rules')->where('id', $rule->id)->update(['last_run_at' => now(), 'updated_at' => now()]);

            return $count;
        }, 3);
    }

    public function undo(int $id, User $actor): void
    {
        DB::transaction(function () use ($id, $actor) {
            $run = DB::table('operational_rule_runs')->where('id', $id)->lockForUpdate()->first();
            abort_unless($run, 404);
            if ($run->undone_at) {
                return;
            }
            $activity = Activity::whereKey($run->activity_id)->lockForUpdate()->first();
            $original = json_decode($run->original, true);
            if (! $activity || $activity->completed_at || $activity->getAttributes() != $original) {
                throw ValidationException::withMessages(['automation' => 'A tarefa já foi alterada. Revise-a na agenda; o desfazer foi bloqueado para preservar o trabalho.']);
            }
            $activity->update(['status' => 'cancelled']);
            DB::table('operational_rule_runs')->where('id', $id)->update(['undone_at' => now(), 'updated_at' => now()]);
            AuditLog::create(['user_id' => $actor->id, 'action' => 'automation.undone', 'subject_type' => Opportunity::class, 'subject_id' => $activity->opportunity_id, 'metadata' => ['run_id' => $id]]);
        });
    }
}
