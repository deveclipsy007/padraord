<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\ViabilityProject;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ViabilityLifecycleService
{
    public const STATUSES = [
        'draft',
        'in_development',
        'ready_for_delivery',
        'delivered',
        'accepted',
        'closed_without_management',
        'cancelled',
    ];

    public function transition(ViabilityProject $project, User $actor, string $status, array $data = []): ViabilityProject
    {
        if (! in_array($status, self::STATUSES, true)) {
            throw ValidationException::withMessages(['status' => 'Estado de Viabilidade inválido.']);
        }

        return DB::transaction(function () use ($project, $actor, $status, $data): ViabilityProject {
            $locked = ViabilityProject::with('deliverables')->lockForUpdate()->findOrFail($project->id);
            $this->assertTransitionAllowed($locked, $status, $data);

            $before = $locked->status;
            $snapshot = $locked->snapshot ?? [];
            if (in_array($status, ['delivered', 'accepted', 'closed_without_management'], true)) {
                $snapshot['lifecycle'][$status] = [
                    'at' => now()->toIso8601String(),
                    'by' => $actor->id,
                    'note' => trim((string) ($data['note'] ?? '')),
                    'deliverables' => $locked->deliverables->map(fn ($deliverable) => [
                        'key' => $deliverable->key,
                        'status' => $deliverable->status,
                        'content' => $deliverable->content,
                        'evidence' => $deliverable->evidence,
                    ])->values()->all(),
                ];
            }

            $locked->update([
                'status' => $status,
                'snapshot' => $snapshot,
                'revision' => $locked->revision + 1,
            ]);

            AuditLog::create([
                'user_id' => $actor->id,
                'subject_type' => ViabilityProject::class,
                'subject_id' => $locked->id,
                'action' => 'viability.lifecycle_changed',
                'metadata' => [
                    'from' => $before,
                    'to' => $status,
                    'note' => trim((string) ($data['note'] ?? '')),
                ],
            ]);

            return $locked->fresh('deliverables');
        }, 3);
    }

    private function assertTransitionAllowed(ViabilityProject $project, string $status, array $data): void
    {
        $allowed = [
            'draft' => ['in_development', 'cancelled'],
            'in_development' => ['ready_for_delivery', 'cancelled'],
            'ready_for_delivery' => ['in_development', 'delivered', 'cancelled'],
            'delivered' => ['in_development', 'accepted', 'cancelled'],
            'accepted' => ['closed_without_management'],
            'closed_without_management' => [],
            'cancelled' => [],
        ];
        if ($status !== $project->status && ! in_array($status, $allowed[$project->status] ?? [], true)) {
            throw ValidationException::withMessages(['status' => 'Esta decisão não pode pular etapas da Viabilidade.']);
        }

        if ($status === 'ready_for_delivery') {
            $missing = $project->deliverables
                ->filter(fn ($deliverable) => $deliverable->required && (! in_array($deliverable->status, ['ready', 'delivered'], true) || empty($deliverable->evidence)))
                ->pluck('title')
                ->values()
                ->all();

            if ($missing !== []) {
                throw ValidationException::withMessages([
                    'deliverables' => 'Conclua e vincule evidências aos entregáveis obrigatórios: '.implode(', ', $missing).'.',
                ]);
            }
        }

        if (in_array($status, ['accepted', 'closed_without_management'], true) && trim((string) ($data['note'] ?? '')) === '') {
            throw ValidationException::withMessages(['note' => 'Registre a evidência ou a observação da decisão.']);
        }
    }
}
