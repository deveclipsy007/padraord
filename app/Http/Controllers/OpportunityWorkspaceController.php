<?php

namespace App\Http\Controllers;

use App\Enums\OpportunityStage;
use App\Models\AuditLog;
use App\Models\AssistantPreview;
use App\Models\CaseContextEntry;
use App\Models\CaseJourney;
use App\Models\Opportunity;
use App\Models\ViabilityProject;
use App\Services\AssistancePreparation;
use App\Services\CaseWorkspaceSummary;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OpportunityWorkspaceController extends Controller
{
    public function show(Opportunity $opportunity, CaseWorkspaceSummary $workspaceSummary): Response
    {
        $opportunity->updateQuietly(['last_viewed_at' => now()]);

        $stageCases = collect(OpportunityStage::cases())
            ->reject(fn (OpportunityStage $stage) => in_array($stage, [OpportunityStage::LOST, OpportunityStage::CANCELLED], true))
            ->values();

        $currentIndex = $stageCases->search(fn (OpportunityStage $stage) => $stage === $opportunity->stage);
        $readiness = [
            ['key' => 'contact', 'label' => 'Contato principal definido', 'complete' => filled($opportunity->contact_name)],
            ['key' => 'date', 'label' => 'Data do evento conhecida', 'complete' => filled($opportunity->event_date)],
            ['key' => 'briefing', 'label' => 'Briefing com contexto', 'complete' => $opportunity->briefingMessages()->exists()],
            ['key' => 'budget', 'label' => 'Orçamento revisado', 'complete' => $opportunity->budgets()->where('status', 'approved')->exists()],
        ];
        $summary = $workspaceSummary->for($opportunity);
        $latestContext = CaseContextEntry::where('opportunity_id', $opportunity->id)->latest('id')->first();
        $contextPreview = $latestContext ? AssistantPreview::find(data_get($latestContext->metadata, 'preview_id')) : null;

        return Inertia::render('OpportunityShow', [
            'nextStep' => app(AssistancePreparation::class)->nextStep($opportunity),
            'preparation' => app(AssistancePreparation::class)->latest($opportunity),
            'briefingRevision' => $opportunity->briefing_revision,
            'opportunity' => [
                'id' => $opportunity->id,
                'title' => $opportunity->title,
                'clientName' => $opportunity->client_name,
                'contactName' => $opportunity->contact_name,
                'contactEmail' => $opportunity->contact_email,
                'stage' => $opportunity->stage->value,
                'stageLabel' => $opportunity->stage->label(),
                'eventDate' => $opportunity->event_date?->format('d/m/Y'),
                'location' => $opportunity->location,
                'objective' => $opportunity->objective,
                'estimatedValueCents' => $opportunity->estimated_value_cents,
                'nextAction' => $opportunity->next_action,
                'briefingStatus' => $opportunity->briefing_status,
            ],
            'stages' => $stageCases->map(fn (OpportunityStage $stage, int $index): array => [
                'id' => $stage->value,
                'label' => $stage->label(),
                'active' => $stage === $opportunity->stage,
                'complete' => $index < $currentIndex,
            ])->values(),
            'readiness' => [
                'items' => $readiness,
                'complete' => collect($readiness)->where('complete', true)->count(),
                'total' => count($readiness),
            ],
            'stats' => [
                'messages' => $opportunity->briefingMessages()->count(),
                'budgetItems' => $opportunity->budgets()->latest('version')->first()?->items()->count() ?? 0,
                'tasks' => $opportunity->productionTasks()->count(),
            ],
            'history' => AuditLog::query()
                ->where('subject_type', Opportunity::class)
                ->where('subject_id', $opportunity->id)
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (AuditLog $log): array => [
                    'action' => $log->action,
                    'metadata' => $log->metadata,
                    'createdAt' => $log->created_at->format('d/m/Y H:i'),
                ])->values(),
            'moduleStatuses' => $summary['moduleStatuses'],
            'contextPreview' => $contextPreview && $contextPreview->status === 'preview' ? ['id' => $contextPreview->id, 'entryId' => $latestContext->id, 'status' => $contextPreview->status, 'actions' => $contextPreview->actions] : null,
        ]);
    }

    public function prepare(Request $request, Opportunity $opportunity)
    {
        $data = $request->validate(['revision' => ['required', 'integer', 'min:0']]);
        app(AssistancePreparation::class)->prepare($opportunity, $request->user(), $data['revision']);

        return back()->with('success', 'Roteiro de cotações, conteúdo inicial e tarefa de revisão preparados. Sem preços inventados.');
    }
}
