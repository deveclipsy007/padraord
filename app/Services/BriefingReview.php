<?php

namespace App\Services;

use App\AI\BriefingContext;
use App\Models\AuditLog;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BriefingReview
{
    public function apply(Opportunity $case, User $user, array $input): void
    {
        DB::transaction(function () use ($case, $user, $input) {
            // Compare-and-swap acquires the write lock before reading shared state.
            $changed = Opportunity::whereKey($case->id)->where('briefing_revision', $input['revision'])->increment('briefing_revision');
            if (! $changed) {
                throw ValidationException::withMessages(['revision' => 'O briefing mudou em outra ação. Atualize a página e compare antes de salvar; seu texto permanece no formulário.']);
            }
            $o = $case->fresh();
            $fields = app(BriefingContext::class)->fields($o);
            $before = $fields;
            if ($input['action'] === 'save') {
                $fields = array_replace($fields, $input['fields'] ?? []);
            }
            if (in_array($input['action'], ['accept', 'reject'])) {
                $run = $o->aiRuns()->where('status', 'success')->findOrFail($input['run_id']);
                $decisions = $run->decisions ?? [];
                foreach ($input['indices'] ?? [] as $index) {
                    $change = $run->output_payload['suggested_changes'][$index] ?? null;
                    if (! $change || isset($decisions[$index])) {
                        throw ValidationException::withMessages(['review' => 'Sugestão inexistente ou já revisada.']);
                    }
                    if ($input['action'] === 'accept') {
                        if (($fields[$change['field']] ?? '') !== ($change['current'] ?? '')) {
                            throw ValidationException::withMessages(['review' => 'O conteúdo atual difere da origem da sugestão. Compare e faça a edição manual.']);
                        }
                        $fields[$change['field']] = $change['suggested'];
                    }
                    $decisions[$index] = ['decision' => $input['action'], 'user_id' => $user->id, 'at' => now()->toISOString()];
                }
                $run->update(['decisions' => $decisions]);
            }
            $o->briefing_data = $fields;
            if ($before === $fields && $input['action'] !== 'approve') {
                $o->briefing_revision = (int) $input['revision'];
            }
            if ($before !== $fields) {
                $o->briefing_status = 'awaiting_review';
            }
            if ($input['action'] === 'approve') {
                if (app(BriefingContext::class)->gaps($o)) {
                    throw ValidationException::withMessages(['review' => 'Preencha objetivo, público, data, local, investimento e escopo antes de aprovar.']);
                }
                $o->briefing_status = 'complete';
                $o->briefing_approval = ['revision' => $o->briefing_revision, 'fields' => $fields, 'user_id' => $user->id, 'at' => now()->toISOString()];
            }
            $o->save();
            AuditLog::create(['user_id' => $user->id, 'action' => 'briefing.'.$input['action'], 'subject_type' => Opportunity::class, 'subject_id' => $o->id, 'metadata' => ['revision' => $o->briefing_revision, 'before' => $before, 'after' => $fields, 'previous_approval_preserved' => (bool) $o->briefing_approval]]);
        }, 3);
    }
}
