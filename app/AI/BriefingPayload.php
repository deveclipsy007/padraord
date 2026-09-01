<?php

namespace App\AI;

final class BriefingPayload
{
    public const FIELDS = ['objective', 'audience', 'event_date', 'location', 'budget', 'scope', 'restrictions', 'references'];

    public static function schema(): array
    {
        $string = ['type' => 'string'];
        $object = fn ($props) => ['type' => 'object', 'additionalProperties' => false, 'properties' => $props, 'required' => array_keys($props)];

        return $object(['summary' => $string, 'facts' => ['type' => 'array', 'items' => $object(['key' => $string, 'value' => $string, 'evidence' => $string, 'kind' => ['type' => 'string', 'enum' => ['fact', 'hypothesis', 'conflict']]])],
            'missing_questions' => ['type' => 'array', 'items' => $string], 'risks' => ['type' => 'array', 'items' => $string],
            'suggested_changes' => ['type' => 'array', 'items' => $object(['field' => ['type' => 'string', 'enum' => self::FIELDS], 'current' => ['type' => ['string', 'null']], 'suggested' => $string, 'reason' => $string])]]);
    }

    public static function valid(array $payload): bool
    {
        if (array_diff(array_keys($payload), ['summary', 'facts', 'missing_questions', 'risks', 'suggested_changes']) || ! is_string($payload['summary'] ?? null)) {
            return false;
        }
        foreach (['facts', 'missing_questions', 'risks', 'suggested_changes'] as $key) {
            if (! is_array($payload[$key] ?? null) || ! array_is_list($payload[$key]) || count($payload[$key]) > 100) {
                return false;
            }
        }
        foreach (array_merge($payload['missing_questions'], $payload['risks']) as $value) {
            if (! is_string($value) || mb_strlen($value) > 10000) {
                return false;
            }
        }
        foreach ($payload['facts'] as $fact) {
            if (! self::strings($fact, ['key', 'value', 'evidence'], ['kind']) || (isset($fact['kind']) && ! in_array($fact['kind'], ['fact', 'hypothesis', 'conflict'], true))) {
                return false;
            }
        }
        foreach ($payload['suggested_changes'] as $change) {
            if (! is_array($change) || ! self::strings($change, ['field', 'suggested', 'reason'], ['current']) || ! array_key_exists('current', $change)
                || (! is_string($change['current']) && $change['current'] !== null)
                || ! in_array($change['field'], self::FIELDS, true)) {
                return false;
            }
        }

        return mb_strlen($payload['summary']) <= 10000;
    }

    private static function strings(mixed $object, array $keys, array $optional = []): bool
    {
        if (! is_array($object) || array_diff(array_keys($object), array_merge($keys, $optional))) {
            return false;
        }
        foreach ($keys as $key) {
            if (! is_string($object[$key] ?? null) || mb_strlen($object[$key]) > 10000) {
                return false;
            }
        }

        return true;
    }
}
