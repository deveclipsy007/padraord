<?php

namespace App\AI;

use App\Models\Opportunity;

class BriefingContext
{
    public const LABELS = ['objective' => 'Objetivo', 'audience' => 'Público', 'event_date' => 'Data', 'location' => 'Local', 'budget' => 'Investimento disponível', 'scope' => 'Escopo', 'restrictions' => 'Restrições', 'references' => 'Referências'];

    public function build(Opportunity $o): array
    {
        $last = $o->aiRuns()->where('status', 'success')->latest('id')->first();

        return ['case_id' => $o->id, 'revision' => $o->briefing_revision, 'opportunity_title' => $o->title, 'client_name' => $o->client_name,
            'briefing' => $this->fields($o), 'previous_summary' => mb_substr($last?->output_payload['summary'] ?? '', 0, 4000)];
    }

    public function fields(Opportunity $o): array
    {
        return array_replace(array_fill_keys(BriefingPayload::FIELDS, ''), array_filter(['objective' => $o->objective, 'location' => $o->location, 'event_date' => $o->event_date?->format('Y-m-d')], fn ($v) => $v !== null), $o->briefing_data ?? []);
    }

    public function gaps(Opportunity $o): array
    {
        $fields = $this->fields($o);

        return array_values(array_map(fn ($key) => ['field' => $key, 'question' => 'Qual é '.match ($key) {
            'objective' => 'o objetivo do evento?','audience' => 'o público esperado?','event_date' => 'a data prevista?','location' => 'o local?','budget' => 'o investimento disponível?',default => 'o escopo esperado?'
        }], array_filter(['objective', 'audience', 'event_date', 'location', 'budget', 'scope'], fn ($k) => trim($fields[$k]) === '' || preg_match('/^(?:ainda\s+)?(?:a\s+(?:definir|confirmar)|pendente|n[aã]o\s+(?:informad[oa]|definid[oa])|desconhecid[oa]|tbd)[.!]?$/iu', trim($fields[$k])))));
    }
}
