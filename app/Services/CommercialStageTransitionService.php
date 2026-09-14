<?php

namespace App\Services;

use App\AI\BriefingContext;
use App\Enums\CommercialStage;
use App\Models\AuditLog;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CommercialStageTransitionService
{
    private const RETURN_REASONS = ['data_correction', 'client_request', 'process_adjustment', 'other'];

    private const LOSS_REASONS = ['price', 'no_budget', 'timing', 'no_response', 'competitor', 'not_fit', 'client_decision', 'other'];

    private const CANCELLATION_REASONS = ['duplicate', 'client_cancelled', 'internal_cancelled', 'event_cancelled', 'other'];

    public function transition(Opportunity $case, User $actor, array $data): Opportunity
    {
        return DB::transaction(function () use ($case, $actor, $data): Opportunity {
            $locked = Opportunity::query()->whereKey($case->id)->lockForUpdate()->firstOrFail();
            $current = $locked->commercial_stage instanceof CommercialStage ? $locked->commercial_stage : CommercialStage::tryFrom((string) $locked->commercial_stage);
            $target = CommercialStage::tryFrom((string) $data['to']);

            if (! $current || ! $target) {
                $this->fail('transition', 'A etapa comercial informada não existe.');
            }
            if ($current === $target) {
                $this->fail('transition', 'O caso já está nesta etapa.');
            }
            if ((int) ($data['revision'] ?? 0) !== (int) $locked->commercial_revision) {
                $this->fail('revision', 'O caso foi alterado por outra pessoa. Atualize antes de decidir.');
            }

            $isTerminal = in_array($target, [CommercialStage::LOST, CommercialStage::CANCELLED], true);
            $currentIndex = array_search($current, CommercialStage::active(), true);
            $targetIndex = array_search($target, CommercialStage::active(), true);
            $isReturn = $currentIndex !== false && $targetIndex !== false && $targetIndex < $currentIndex;

            if ($isTerminal) {
                $this->requireReason($target, $data);
            } elseif ($isReturn || $currentIndex === false) {
                $this->requireReturnReason($data);
            } elseif ($targetIndex !== false && $currentIndex !== false && $targetIndex > $currentIndex + 1) {
                $this->fail('transition', 'Avance uma etapa por vez para preservar a decisão e o histórico.');
            }

            if ($target === CommercialStage::VIABILITY_CONTRACTED && ($data['source'] ?? 'commercial') !== 'journey') {
                $this->fail('transition', 'Registre a contratação da Viabilidade na Jornada com sua evidência.');
            }

            if ($target === CommercialStage::QUALIFICATION) {
                $this->requireLeadReadiness($locked);
            }
            if ($target === CommercialStage::MEETING) {
                $qualification = $locked->qualification;
                if (! $qualification || $qualification->status !== 'qualified' || blank($locked->contact_name)) {
                    $this->fail('transition', 'Conclua a qualificação e defina o contato principal antes de agendar a reunião.');
                }
            }
            if ($target === CommercialStage::INITIAL_BRIEFING && ! $locked->activities()->where('type', 'meeting')->where('status', 'done')->exists() && blank($data['evidence'] ?? null)) {
                $this->fail('transition', 'Conclua a reunião vinculada ou registre a evidência da conversa antes de iniciar o briefing.');
            }
            if ($target === CommercialStage::VIABILITY_OFFER && ! $locked->briefingMessages()->exists() && ! collect(app(BriefingContext::class)->fields($locked))->contains(fn ($value) => filled($value))) {
                $this->fail('transition', 'Adicione contexto ao briefing antes de preparar a oferta de Viabilidade.');
            }

            $from = $current->value;
            $locked->update([
                'commercial_stage' => $target,
                'commercial_revision' => (int) $locked->commercial_revision + 1,
                'reason_category' => $data['reason_category'] ?? null,
                'reason_note' => $data['reason_note'] ?? null,
            ]);
            AuditLog::create([
                'user_id' => $actor->id,
                'action' => 'opportunity.commercial_stage_changed',
                'subject_type' => Opportunity::class,
                'subject_id' => $locked->id,
                'metadata' => [
                    'from' => $from,
                    'to' => $target->value,
                    'revision' => $locked->commercial_revision,
                    'reason_category' => $data['reason_category'] ?? null,
                    'reason_note' => $data['reason_note'] ?? null,
                    'evidence' => $data['evidence'] ?? null,
                ],
            ]);

            return $locked->fresh();
        });
    }

    private function requireLeadReadiness(Opportunity $case): void
    {
        if (! $case->client_id || ! $case->owner_id || blank($case->next_action) || blank($case->next_action_at)) {
            $this->fail('transition', 'Defina cliente, responsável, próxima ação e prazo antes de qualificar o lead.');
        }
    }

    private function requireReason(CommercialStage $target, array $data): void
    {
        $reasons = $target === CommercialStage::LOST ? self::LOSS_REASONS : self::CANCELLATION_REASONS;
        $errors = [];
        if (! in_array($data['reason_category'] ?? null, $reasons, true)) {
            $errors['reason_category'] = 'Escolha um motivo válido para esta decisão.';
        }
        if (blank($data['reason_note'] ?? null)) {
            $errors['reason_note'] = 'Explique brevemente o motivo desta decisão.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function requireReturnReason(array $data): void
    {
        $errors = [];
        if (! in_array($data['reason_category'] ?? null, self::RETURN_REASONS, true)) {
            $errors['reason_category'] = 'Escolha o motivo do retorno de etapa.';
        }
        if (blank($data['reason_note'] ?? null)) {
            $errors['reason_note'] = 'Explique por que o caso voltou de etapa.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
