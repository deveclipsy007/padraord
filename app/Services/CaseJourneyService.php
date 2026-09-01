<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CaseJourney;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CaseJourneyService
{
    public const DELIVERABLES = ['concept' => 'Conceito do evento', 'estimate' => 'Estimativa de investimento', 'suppliers' => 'Mapa de fornecedores', 'schedule' => 'Cronograma geral', 'model_3d' => 'Maquete 3D recebida', 'floor_plan' => 'Planta baixa recebida'];

    public static function required(string $modality): array
    {
        return $modality === 'complete' ? array_keys(self::DELIVERABLES) : array_slice(array_keys(self::DELIVERABLES), 0, 4);
    }

    public function apply(Opportunity $case, User $actor, array $data): void
    {
        DB::transaction(function () use ($case, $actor, $data) {
            Opportunity::whereKey($case->id)->lockForUpdate()->firstOrFail();
            $journey = CaseJourney::where('opportunity_id', $case->id)->lockForUpdate()->first();
            if (($journey?->revision ?? 0) !== (int) $data['revision']) {
                $this->fail('revision', 'O caso foi alterado por outra pessoa. Atualize antes de decidir.');
            }
            $action = $data['action'];
            if ($action === 'configure') {
                if ($journey && $journey->viability_status !== 'not_contracted') {
                    $this->fail('action', 'Uma contratação já registrada não pode ter modalidade ou modo substituídos.');
                }
                if (($data['modality'] ?? '') === 'strategic') {
                    $this->fail('modality', 'Projeto estratégico ainda depende de definição de escopo e entregáveis.');
                }
                $journey ??= new CaseJourney(['opportunity_id' => $case->id, 'cycle' => 'commercial', 'viability_status' => 'not_contracted', 'management_status' => 'not_contracted']);
                $journey->fill(['modality' => $data['modality'], 'mode' => $data['mode']]);
            } else {
                if (! $journey || $journey->outcome) {
                    $this->fail('action', 'Configure um caso ativo antes desta ação.');
                }
                if (blank($data['evidence'] ?? null)) {
                    $this->fail('evidence', 'Registre a referência da evidência e a decisão.');
                }
                if ($journey->mode === 'real' && (! $actor->can_approve_commercial || ! config('commercial.rules_approved') || blank(config('commercial.rules_evidence')))) {
                    $this->fail('action', 'Decisão real bloqueada: regras comerciais e responsáveis ainda precisam ser validados.');
                }
                switch ($action) {
                    case 'contract_viability':
                        $this->expect($journey->viability_status === 'not_contracted', 'A Viabilidade já possui contratação registrada.');
                        $journey->fill(['cycle' => 'viability', 'viability_status' => 'in_progress']);
                        break;
                    case 'deliver_viability':
                        $this->expect($journey->viability_status === 'in_progress', 'A Viabilidade precisa estar contratada e em desenvolvimento.');
                        $missing = array_diff(self::required($journey->modality), $data['deliverables'] ?? []);
                        $this->expect(! $missing, 'Conclua todos os entregáveis da modalidade antes de registrar a entrega.');
                        $journey->fill(['deliverables' => $data['deliverables'], 'viability_status' => 'delivered']);
                        break;
                    case 'accept_delivery':
                        $this->expect($journey->viability_status === 'delivered', 'Registre a entrega antes do aceite do cliente.');
                        $journey->viability_status = 'accepted';
                        break;
                    case 'close_viability':
                        $this->expect($journey->viability_status === 'accepted' && $journey->management_status === 'not_contracted', 'A entrega deve estar aceita e sem contratação de Gestão.');
                        $journey->outcome = 'viability_completed';
                        break;
                    case 'contract_management':
                        $this->expect($journey->viability_status === 'accepted' && $journey->management_status === 'not_contracted', 'A Gestão exige aceite do projeto e contratação própria.');
                        $journey->fill(['cycle' => 'management', 'management_status' => 'planning']);
                        break;
                    default: $this->fail('action', 'Ação não disponível nesta fase.');
                }
                $journey->evidence = [...($journey->evidence ?? []), ['action' => $action, 'reference' => $data['evidence'], 'user_id' => $actor->id, 'at' => now()->toIso8601String(), 'mode' => $journey->mode]];
            }
            $journey->revision = (int) $data['revision'] + 1;
            $journey->save();
            AuditLog::create(['user_id' => $actor->id, 'subject_type' => Opportunity::class, 'subject_id' => $case->id, 'action' => 'journey.'.$action, 'metadata' => ['cycle' => $journey->cycle, 'mode' => $journey->mode, 'revision' => $journey->revision, 'evidence' => $data['evidence'] ?? null]]);
        });
    }

    private function expect(bool $condition, string $message): void
    {
        if (! $condition) {
            $this->fail('action', $message);
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
