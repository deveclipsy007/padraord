<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Opportunity;
use App\Models\OpportunityQualification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class QualificationService
{
    public function save(Opportunity $case, User $actor, array $data): OpportunityQualification
    {
        return DB::transaction(function () use ($case, $actor, $data): OpportunityQualification {
            if ($case->client_id) {
                Client::whereKey($case->client_id)->lockForUpdate()->firstOrFail();
            }
            $locked = Opportunity::query()->whereKey($case->id)->lockForUpdate()->firstOrFail();
            $qualification = OpportunityQualification::query()->firstOrNew(['opportunity_id' => $locked->id]);

            if ($qualification->exists && (int) ($data['revision'] ?? 0) !== (int) $qualification->revision) {
                throw ValidationException::withMessages(['revision' => 'A qualificação foi alterada por outra pessoa. Atualize antes de salvar.']);
            }

            if (! empty($data['decision_maker_contact_id'])) {
                $belongsToCase = $locked->client_id && $locked->client?->contacts()->active()->where('is_decision_maker', true)->whereKey($data['decision_maker_contact_id'])->exists();
                if (! $belongsToCase) {
                    throw ValidationException::withMessages(['decision_maker_contact_id' => 'Selecione um contato ativo deste cliente com papel de decisor confirmado.']);
                }
            }
            if ($data['decision_maker_status'] === 'identified' && empty($data['decision_maker_contact_id'])) {
                throw ValidationException::withMessages(['decision_maker_contact_id' => 'Indique o contato que toma a decisão.']);
            }

            $qualification->fill([
                'need_summary' => $data['need_summary'] ?? null,
                'decision_maker_status' => $data['decision_maker_status'],
                'decision_maker_contact_id' => $data['decision_maker_contact_id'] ?? null,
                'event_date_status' => $data['event_date_status'],
                'budget_status' => $data['budget_status'],
                'fit_status' => $data['fit_status'],
                'notes' => $data['notes'] ?? null,
                'status' => $data['status'],
                'revision' => (int) $qualification->revision + 1,
                'qualified_at' => $data['status'] === 'qualified' ? now() : null,
                'qualified_by' => $data['status'] === 'qualified' ? $actor->id : null,
            ]);
            $qualification->save();

            return $qualification;
        });
    }
}
