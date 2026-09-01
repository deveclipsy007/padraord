<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Opportunity;
use App\Models\User;
use App\Models\ViabilityProject;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ViabilityWorkspaceService
{
    public const DELIVERABLES = [
        'concept' => 'Conceito do evento', 'estimate' => 'Estimativa preliminar',
        'suppliers' => 'Mapa de fornecedores', 'schedule' => 'Cronograma macro',
        'model_3d' => 'Maquete 3D', 'floor_plan' => 'Planta baixa',
    ];

    public function save(Opportunity $case, User $actor, array $data): ViabilityProject
    {
        return DB::transaction(function () use ($case, $actor, $data): ViabilityProject {
            $project = ViabilityProject::where('opportunity_id', $case->id)->lockForUpdate()->first();
            if (($project?->revision ?? 0) !== (int) $data['revision']) {
                throw ValidationException::withMessages(['revision' => 'A Viabilidade mudou em outra ação. Atualize e compare antes de salvar.']);
            }
            $project ??= new ViabilityProject(['opportunity_id' => $case->id]);
            $project->fill(collect($data)->only(['modality', 'concept', 'experience', 'technical_assumptions', 'estimate_notes', 'supplier_needs', 'schedule_notes', 'references'])->all());
            $project->status = 'draft';
            $project->revision = (int) $data['revision'] + 1;
            $project->save();

            $selected = $data['deliverables'] ?? [];
            foreach (self::DELIVERABLES as $key => $title) {
                $required = in_array($key, $project->modality === 'complete' ? array_keys(self::DELIVERABLES) : array_slice(array_keys(self::DELIVERABLES), 0, 4), true);
                $project->deliverables()->updateOrCreate(['key' => $key], [
                    'opportunity_id' => $case->id, 'title' => $title, 'required' => $required,
                    'status' => in_array($key, $selected, true) ? 'draft' : 'pending',
                ]);
            }
            AuditLog::create(['user_id' => $actor->id, 'subject_type' => Opportunity::class, 'subject_id' => $case->id, 'action' => 'viability.draft_saved', 'metadata' => ['revision' => $project->revision, 'modality' => $project->modality]]);

            return $project->fresh('deliverables');
        }, 3);
    }
}
