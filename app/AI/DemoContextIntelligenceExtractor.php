<?php

namespace App\AI;

use App\Contracts\ContextIntelligenceExtractor;
use App\Data\ContextIntelligenceResult;
use App\Models\CaseContextEntry;

class DemoContextIntelligenceExtractor implements ContextIntelligenceExtractor
{
    private const BRIEFING_FIELDS = [
        'objetivo' => 'objective',
        'público' => 'audience',
        'publico' => 'audience',
        'data' => 'event_date',
        'local' => 'location',
        'investimento' => 'budget',
        'escopo' => 'scope',
        'restrições' => 'restrictions',
        'restricoes' => 'restrictions',
        'referências' => 'references',
        'referencias' => 'references',
    ];

    public function extract(CaseContextEntry $entry, array $caseContext): ContextIntelligenceResult
    {
        $entry->loadMissing('segments');
        $changes = [];
        $facts = [];

        foreach ($entry->segments as $segment) {
            if (! preg_match('/^\s*([^:]{2,50})\s*:\s*(.+)\s*$/u', $segment->text, $match)) {
                continue;
            }
            $label = mb_strtolower(trim($match[1]));
            $field = self::BRIEFING_FIELDS[$label] ?? null;
            if (! $field) {
                continue;
            }
            $value = trim($match[2]);
            $facts[] = [
                'key' => 'briefing.'.$field,
                'value' => $value,
                'classification' => 'fact',
                'evidence_segment_ids' => [$segment->id],
            ];
            $changes[] = [
                'field' => $field,
                'suggested' => $value,
                'reason' => 'Demonstração determinística: campo explicitamente identificado no contexto.',
                'classification' => 'fact',
                'evidence_segment_ids' => [$segment->id],
            ];
        }

        $payload = [
            'summary' => $changes === []
                ? 'Demonstração: contexto preservado para revisão humana.'
                : 'Demonstração: '.count($changes).' informação(ões) explícita(s) organizada(s).',
            'participants' => $entry->segments
                ->map(fn ($segment) => ['speaker_id' => $segment->speaker_key, 'display_name' => $segment->speaker_name])
                ->unique('speaker_id')
                ->values()
                ->all(),
            'facts' => $facts,
            'decisions' => [],
            'open_questions' => $changes === [] ? ['Qual é o objetivo, público, data, local e investimento disponível?'] : [],
            'risks' => ['Modo demonstração: revise o conteúdo antes de usar qualquer rascunho.'],
            'constraints' => [],
            'budget_mentions' => [],
            'supplier_mentions' => [],
            'action_items' => [],
            'module_changes' => [
                'case' => [],
                'briefing' => $changes,
                'viability' => [],
                'budget' => [],
                'documents' => [],
                'production' => [],
                'post_event' => [],
            ],
        ];
        ContextIntelligenceSchema::validate($payload, $entry->segments->pluck('id')->all());

        return new ContextIntelligenceResult($payload, 'demo', 'deterministic', 'context-demo-v1');
    }
}
