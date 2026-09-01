<?php

namespace App\AI;

final class DemoAiProvider implements AiProvider
{
    public function analyzeBriefing(string $transcript, array $context = []): AiCompletion
    {
        $excerpt = mb_substr(trim($transcript), 0, 500);
        $facts = [];
        $changes = [];
        foreach (BriefingContext::LABELS as $field => $label) {
            if (preg_match('/(?:^|\n)\s*'.preg_quote($label, '/').'\s*:\s*([^\n]+)/iu', $transcript, $match)) {
                $value = trim($match[1]);
                $facts[] = ['key' => $label, 'value' => $value, 'evidence' => trim($match[0])];
                $changes[] = ['field' => $field, 'current' => (string) ($context['briefing'][$field] ?? ''), 'suggested' => $value, 'reason' => 'Campo explicitamente identificado no texto. Demonstração determinística; confirme o conteúdo.'];
            }
        }
        if ($changes) {
            return AiCompletion::success(['summary' => 'Demonstração: '.count($changes).' informações explícitas organizadas.', 'facts' => $facts, 'missing_questions' => [], 'risks' => ['Modo demonstração: apenas campos explicitamente rotulados foram extraídos.'], 'suggested_changes' => $changes], 'demo', 'deterministic', 'briefing-demo-v2');
        }

        return AiCompletion::success([
            'summary' => 'Demonstração: contexto recebido para revisão humana.',
            'facts' => [['key' => 'contexto recebido', 'value' => $excerpt, 'evidence' => $excerpt]],
            'missing_questions' => ['Qual é o objetivo?', 'Qual é o público, a data e o local?', 'Qual é o limite de investimento?'],
            'risks' => ['Demonstração determinística: não substitui análise do produtor.'],
            'suggested_changes' => [['field' => 'scope', 'current' => (string) ($context['briefing']['scope'] ?? $context['scope'] ?? ''), 'suggested' => $excerpt, 'reason' => 'Organizar o texto original como rascunho de escopo; revisar antes de aceitar.']],
        ], 'demo', 'deterministic', 'briefing-demo-v1');
    }
}
