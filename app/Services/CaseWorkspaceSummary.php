<?php

namespace App\Services;

use App\AI\BriefingContext;
use App\Models\CaseJourney;
use App\Models\Opportunity;
use App\Models\ViabilityProject;

class CaseWorkspaceSummary
{
    public function __construct(private readonly BriefingContext $briefingContext) {}

    public function for(Opportunity $case): array
    {
        $journey = CaseJourney::where('opportunity_id', $case->id)->first();
        $viability = ViabilityProject::where('opportunity_id', $case->id)->first();
        $budget = $case->budgets()->latest('version')->first();
        $modules = [
            ['key' => 'journey', 'label' => 'Jornada', 'status' => $journey ? ($journey->outcome ? 'complete' : 'draft') : 'empty', 'pending' => $journey ? 0 : 1],
            ['key' => 'briefing', 'label' => 'Briefing', 'status' => $case->briefing_status === 'complete' ? 'approved' : ($case->briefingMessages()->exists() || filled($case->briefing_data) ? 'needs_review' : 'empty'), 'pending' => count($this->briefingContext->gaps($case))],
            ['key' => 'viability', 'label' => 'Viabilidade', 'status' => $viability?->status ?? 'empty', 'pending' => $viability ? max(0, 4 - $viability->deliverables()->where('status', '!=', 'pending')->count()) : 1],
            ['key' => 'budget', 'label' => 'Orçamento', 'status' => $budget?->status === 'approved' ? 'approved' : ($budget ? 'draft' : 'empty'), 'pending' => $budget?->items()->count() ? 0 : 1],
            ['key' => 'documents', 'label' => 'Documentos', 'status' => $case->documents()->where('status', 'sent')->exists() ? 'complete' : ($case->documents()->exists() ? 'draft' : 'empty'), 'pending' => $case->documents()->exists() ? 0 : 1],
            ['key' => 'production', 'label' => 'Produção', 'status' => $case->productionTasks()->where('status', '!=', 'done')->exists() ? 'draft' : ($case->productionTasks()->exists() ? 'complete' : 'empty'), 'pending' => $case->productionTasks()->where('status', '!=', 'done')->count()],
            ['key' => 'post-event', 'label' => 'Pós-evento', 'status' => $case->postEventReport?->status === 'closed' ? 'complete' : ($case->postEventReport ? 'draft' : 'empty'), 'pending' => $case->postEventReport ? 0 : 1],
        ];
        $ready = collect($modules)->whereIn('status', ['approved', 'complete'])->count();

        return [
            'id' => $case->id,
            'title' => $case->title,
            'clientName' => $case->client_name,
            'stageLabel' => $case->stage->label(),
            'nextAction' => $case->next_action,
            'readiness' => (int) round(($ready / count($modules)) * 100),
            'moduleStatuses' => $modules,
        ];
    }
}
