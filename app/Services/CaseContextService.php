<?php

namespace App\Services;

use App\Models\AssistantPreview;
use App\Models\AuditLog;
use App\Models\CaseContextEntry;
use App\Models\Opportunity;
use App\Models\User;
use App\Models\ViabilityProject;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CaseContextService
{
    private const BRIEFING = ['objetivo' => 'objective', 'público' => 'audience', 'publico' => 'audience', 'data' => 'event_date', 'local' => 'location', 'investimento' => 'budget', 'orçamento' => 'budget', 'escopo' => 'scope', 'restrições' => 'restrictions', 'restricoes' => 'restrictions', 'referências' => 'references', 'referencias' => 'references'];

    private const VIABILITY = ['conceito' => 'concept', 'experiência' => 'experience', 'experiencia' => 'experience', 'premissas técnicas' => 'technical_assumptions', 'premissas tecnicas' => 'technical_assumptions', 'estimativa' => 'estimate_notes', 'fornecedores' => 'supplier_needs', 'cronograma' => 'schedule_notes'];

    public function createPreview(Opportunity $case, User $user, array $data): AssistantPreview
    {
        $body = trim($data['body']);
        $digest = hash('sha256', $data['kind'].'|'.$data['phase'].'|'.$body);
        $entry = CaseContextEntry::firstOrCreate(['opportunity_id' => $case->id, 'digest' => $digest], ['user_id' => $user->id, 'kind' => $data['kind'], 'phase' => $data['phase'], 'status' => 'received', 'body' => $body, 'metadata' => ['source' => 'case_composer']]);
        $existing = AssistantPreview::where('user_id', $user->id)->where('status', 'preview')->get()->first(fn (AssistantPreview $preview) => ($preview->context['entry_id'] ?? null) === $entry->id);
        if ($existing) {
            return $existing;
        }

        $changes = $this->extract($body, $case);
        $preview = AssistantPreview::create([
            'user_id' => $user->id, 'mode' => 'review', 'message' => $body,
            'context' => ['entry_id' => $entry->id, 'opportunity_id' => $case->id, 'case_hash' => $this->caseFingerprint($case), 'briefing_revision' => $case->briefing_revision, 'viability_revision' => ViabilityProject::where('opportunity_id', $case->id)->value('revision') ?? 0],
            'actions' => $changes, 'status' => 'preview',
        ]);
        $entry->update(['status' => 'review', 'metadata' => ['source' => 'case_composer', 'preview_id' => $preview->id, 'modules' => array_values(array_unique(array_column($changes, 'module')))]]);

        return $preview;
    }

    public function confirm(Opportunity $case, CaseContextEntry $entry, AssistantPreview $preview, User $user, array $modules, ?array $selectedChanges = null): array
    {
        abort_unless($entry->opportunity_id === $case->id && $preview->user_id === $user->id && ($preview->context['entry_id'] ?? null) === $entry->id, 404);

        return DB::transaction(function () use ($case, $entry, $preview, $user, $modules, $selectedChanges): array {
            $locked = AssistantPreview::whereKey($preview->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'confirmed') {
                return $locked->result ?? [];
            }
            $freshCase = Opportunity::whereKey($case->id)->lockForUpdate()->firstOrFail();
            $context = $locked->context;
            if ($this->caseFingerprint($freshCase) !== ($context['case_hash'] ?? null) || $freshCase->briefing_revision !== ($context['briefing_revision'] ?? null) || (ViabilityProject::where('opportunity_id', $case->id)->value('revision') ?? 0) !== ($context['viability_revision'] ?? 0)) {
                throw ValidationException::withMessages(['preview' => 'O caso mudou após a análise. Gere uma nova comparação antes de confirmar.']);
            }
            $accepted = array_values(array_filter(
                $locked->actions,
                fn (array $change, int $index) => in_array($change['module'], $modules, true) && ($selectedChanges === null || in_array($index, $selectedChanges, true)),
                ARRAY_FILTER_USE_BOTH,
            ));
            $briefing = $freshCase->briefing_data ?? [];
            foreach ($accepted as $change) {
                if ($change['module'] === 'briefing') {
                    $briefing[$change['field']] = $change['suggested'];
                }
            }
            if (in_array('briefing', $modules, true) && $briefing !== ($freshCase->briefing_data ?? [])) {
                $freshCase->update(['briefing_data' => $briefing, 'briefing_revision' => $freshCase->briefing_revision + 1, 'briefing_status' => 'awaiting_review']);
            }
            $viabilityChanges = array_filter($accepted, fn (array $change) => $change['module'] === 'viability');
            if ($viabilityChanges) {
                $project = ViabilityProject::firstOrNew(['opportunity_id' => $case->id]);
                foreach ($viabilityChanges as $change) {
                    $project->{$change['field']} = $change['suggested'];
                }
                $project->revision = ($project->revision ?? 0) + 1;
                $project->status = 'draft';
                $project->save();
            }
            $result = ['modules' => array_values(array_unique(array_column($accepted, 'module'))), 'changes' => count($accepted), 'links' => $this->links($case, $modules)];
            $locked->update(['status' => 'confirmed', 'result' => $result]);
            $entry->update(['status' => 'applied', 'revision' => $entry->revision + 1]);
            AuditLog::create(['user_id' => $user->id, 'subject_type' => Opportunity::class, 'subject_id' => $case->id, 'action' => 'context.preview_confirmed', 'metadata' => ['entry_id' => $entry->id, 'modules' => $result['modules'], 'changes' => $result['changes']]]);

            return $result;
        }, 3);
    }

    private function extract(string $body, Opportunity $case): array
    {
        $changes = [];
        foreach (preg_split('/\R/u', $body) ?: [] as $line) {
            if (! preg_match('/^\s*([^:]{2,50})\s*:\s*(.+)\s*$/u', $line, $match)) {
                continue;
            }
            $label = mb_strtolower(trim($match[1]));
            $value = trim($match[2]);
            $module = isset(self::BRIEFING[$label]) ? 'briefing' : (isset(self::VIABILITY[$label]) ? 'viability' : null);
            $field = self::BRIEFING[$label] ?? self::VIABILITY[$label] ?? null;
            if (! $module || ! $field) {
                continue;
            }
            $current = $module === 'briefing' ? data_get($case->briefing_data, $field) : ViabilityProject::where('opportunity_id', $case->id)->value($field);
            $changes[] = ['module' => $module, 'field' => $field, 'current' => $current, 'suggested' => $value, 'reason' => 'Informação explicitamente identificada no contexto.', 'kind' => 'fact', 'evidence' => $line, 'impacts' => $module === 'briefing' && $field === 'scope' ? ['viability', 'budget', 'documents', 'production'] : [$module]];
        }

        return $changes;
    }

    public function caseFingerprint(Opportunity $case): string
    {
        return hash('sha256', json_encode(collect($case->getAttributes())->except(['updated_at', 'last_viewed_at', 'briefing_data', 'briefing_revision', 'briefing_status'])->all()));
    }

    private function links(Opportunity $case, array $modules): array
    {
        return array_values(array_filter([
            in_array('briefing', $modules, true) ? ['label' => 'Revisar Briefing', 'href' => "/opportunities/{$case->id}/briefing"] : null,
            in_array('viability', $modules, true) ? ['label' => 'Abrir Viabilidade', 'href' => "/opportunities/{$case->id}/feasibility"] : null,
        ]));
    }
}
