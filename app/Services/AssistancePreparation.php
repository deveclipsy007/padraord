<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssistancePreparation
{
    public function prepare(Opportunity $case, User $user, int $revision): void
    {
        DB::transaction(function () use ($case, $user, $revision) {
            DB::table('opportunities')->where('id', $case->id)->update(['updated_at' => now()]);
            $o = $case->fresh();
            if ($o->briefing_revision !== $revision || $o->briefing_status !== 'complete' || ($o->briefing_approval['revision'] ?? null) !== $revision) {
                throw ValidationException::withMessages(['briefing' => 'Revise e aprove o briefing atual antes de preparar a entrega.']);
            }
            if (DB::table('assistance_drafts')->where('opportunity_id', $o->id)->where('revision', $revision)->exists()) {
                return;
            }
            $fields = $o->briefing_approval['fields'];
            $scope = array_values(array_filter(array_map('trim', preg_split('/[\n;]+/u', $fields['scope'] ?? ''))));
            $payload = ['mode' => 'manual_template', 'briefing_revision' => $revision, 'briefing_snapshot' => $fields,
                'budget' => array_map(fn ($text) => ['description' => $text, 'quantity' => null, 'unit_cost_cents' => null, 'supplier_id' => null, 'status' => 'needs_quote'], array_slice($scope, 0, 30)),
                'proposal' => ['objective' => $fields['objective'] ?? '', 'scope' => $fields['scope'] ?? '', 'audience' => $fields['audience'] ?? '', 'conditions' => 'Pendente de orçamento aprovado e regras comerciais validadas.'],
                'warning' => 'Preparação por modelo, sem IA adicional. Não é orçamento, proposta aprovada ou contratação.'];
            DB::table('assistance_drafts')->insert(['opportunity_id' => $o->id, 'revision' => $revision, 'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE), 'created_at' => now(), 'updated_at' => now()]);
            $o->activities()->create(['user_id' => $o->owner_id ?? $user->id, 'title' => 'Conferir escopo e solicitar cotações · briefing r'.$revision, 'description' => 'Rascunho preparado a partir do briefing aprovado. Revise quantidades, fornecedores e custos antes de montar o orçamento.', 'type' => 'task', 'priority' => 'normal', 'status' => 'pending']);
            AuditLog::create(['user_id' => $user->id, 'action' => 'assistance.prepared', 'subject_type' => Opportunity::class, 'subject_id' => $o->id, 'metadata' => ['briefing_revision' => $revision]]);
        }, 3);
    }

    public function latest(Opportunity $o): ?array
    {
        $draft = DB::table('assistance_drafts')->where('opportunity_id', $o->id)->latest('revision')->first();

        return $draft ? ['id' => $draft->id, 'revision' => $draft->revision, 'stale' => (int) $draft->revision !== $o->briefing_revision, 'payload' => json_decode($draft->payload, true)] : null;
    }

    public function nextStep(Opportunity $o): array
    {
        $base = '/opportunities/'.$o->id;
        [$step,$title,$href,$reason] = match (true) {
            ! $o->briefingMessages()->exists() => [0, 'Adicionar contexto', $base.'/briefing', 'Cole as informações que já tem. Não é necessário preencher um formulário completo.'],
            $o->briefing_status !== 'complete' => [1, 'Conferir o briefing', $base.'/briefing', 'Confira o entendimento e resolva as lacunas antes de preparar a entrega.'],
            ! $this->latest($o) => [3, 'Preparar entrega', $base.'#preparation', 'O briefing foi revisado; prepare o roteiro de cotações e o conteúdo inicial.'],
            ! $o->budgets()->where('status', 'approved')->exists() => [4, 'Revisar orçamento', $base.'/budget', 'Quantidades, cotações e regras comerciais precisam de revisão humana.'],
            default => [5, 'Acompanhar trabalho', $base.'/production', 'Confira responsáveis, pendências e próximos compromissos.'],
        };

        return ['step' => $step, 'title' => $title, 'href' => $href, 'reason' => $reason, 'owner' => $o->owner?->name ?? 'Responsável a definir', 'due' => $o->next_action_at?->format('d/m/Y H:i')];
    }
}
