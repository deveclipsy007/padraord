<?php

namespace App\AI;

final class ContextIntelligenceSchema
{
    public const MODULES = ['case', 'briefing', 'viability', 'budget', 'documents', 'production', 'post_event'];

    public static function schema(): array
    {
        $stringList = ['type' => 'array', 'items' => ['type' => 'string']];
        $evidence = ['type' => 'array', 'items' => ['type' => 'integer']];
        $change = ['type' => 'object', 'additionalProperties' => false, 'required' => ['field', 'suggested', 'reason', 'classification', 'evidence_segment_ids'], 'properties' => [
            'field' => ['type' => 'string'], 'suggested' => ['type' => 'string'], 'reason' => ['type' => 'string'],
            'classification' => ['type' => 'string', 'enum' => ['fact', 'hypothesis', 'conflict', 'unknown']],
            'evidence_segment_ids' => $evidence,
        ]];
        $moduleProperties = [];
        foreach (self::MODULES as $module) {
            $moduleProperties[$module] = ['type' => 'array', 'items' => $change];
        }

        return ['type' => 'object', 'additionalProperties' => false, 'required' => ['summary', 'participants', 'facts', 'decisions', 'open_questions', 'risks', 'constraints', 'budget_mentions', 'supplier_mentions', 'action_items', 'module_changes'], 'properties' => [
            'summary' => ['type' => 'string'],
            'participants' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['speaker_id', 'display_name'], 'properties' => ['speaker_id' => ['type' => 'string'], 'display_name' => ['type' => ['string', 'null']]]]],
            'facts' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['key', 'value', 'classification', 'evidence_segment_ids'], 'properties' => ['key' => ['type' => 'string'], 'value' => ['type' => 'string'], 'classification' => ['type' => 'string', 'enum' => ['fact', 'hypothesis', 'conflict', 'unknown']], 'evidence_segment_ids' => $evidence]]],
            'decisions' => $stringList,
            'open_questions' => $stringList,
            'risks' => $stringList,
            'constraints' => $stringList,
            'budget_mentions' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['value', 'currency', 'meaning', 'evidence_segment_ids'], 'properties' => ['value' => ['type' => ['number', 'null']], 'currency' => ['type' => ['string', 'null']], 'meaning' => ['type' => 'string'], 'evidence_segment_ids' => $evidence]]],
            'supplier_mentions' => $stringList,
            'action_items' => $stringList,
            'module_changes' => ['type' => 'object', 'additionalProperties' => false, 'required' => self::MODULES, 'properties' => $moduleProperties],
        ]];
    }

    public static function validate(array $payload, array $segmentIds): void
    {
        foreach (['summary', 'participants', 'facts', 'decisions', 'open_questions', 'risks', 'constraints', 'budget_mentions', 'supplier_mentions', 'action_items', 'module_changes'] as $key) {
            if (! array_key_exists($key, $payload)) {
                throw new \RuntimeException("Campo obrigatório ausente: {$key}.");
            }
        }
        if (! is_string($payload['summary']) || ! is_array($payload['module_changes'])) {
            throw new \RuntimeException('A resposta estruturada é inválida.');
        }
        $allowed = array_fill_keys($segmentIds, true);
        $items = $payload['facts'];
        foreach (self::MODULES as $module) {
            if (! isset($payload['module_changes'][$module]) || ! is_array($payload['module_changes'][$module])) {
                throw new \RuntimeException("Módulo obrigatório ausente: {$module}.");
            }
            $items = array_merge($items, $payload['module_changes'][$module]);
        }
        foreach ($items as $item) {
            if (! is_array($item) || ! in_array($item['classification'] ?? null, ['fact', 'hypothesis', 'conflict', 'unknown'], true) || ! is_array($item['evidence_segment_ids'] ?? null)) {
                throw new \RuntimeException('Fato ou alteração inválida.');
            }
            foreach ($item['evidence_segment_ids'] as $id) {
                if (! isset($allowed[$id])) {
                    throw new \RuntimeException('A resposta citou um segmento inexistente.');
                }
            }
        }
    }
}
