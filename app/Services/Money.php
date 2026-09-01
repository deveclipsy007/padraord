<?php

namespace App\Services;

use App\Models\BudgetItem;
use Illuminate\Validation\ValidationException;

final class Money
{
    public static function decimal(string $value, string $field = 'value'): int
    {
        $value = str_replace(',', '.', trim($value));
        if (! preg_match('/^\d{1,9}(?:\.\d{1,2})?$/D', $value)) {
            throw ValidationException::withMessages([$field => 'Informe um valor positivo, sem separador de milhar e com até duas casas decimais.']);
        }
        [$whole, $fraction] = array_pad(explode('.', $value), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    public static function ratio(int $amount, int $numerator, int $denominator): int
    {
        // Non-negative financial amounts: round half up at each disclosed calculation step.
        return intdiv($amount, $denominator) * $numerator
            + intdiv(($amount % $denominator) * $numerator + intdiv($denominator, 2), $denominator);
    }

    public static function breakdown(BudgetItem $item): array
    {
        $cost = self::ratio($item->unit_cost_cents, self::decimal((string) $item->quantity), 100);
        $management = self::ratio($cost, (int) $item->management_bps, 10000);
        $administration = self::ratio($cost + $management, (int) $item->administration_bps, 10000);
        $base = $cost + $management + $administration + (int) $item->tax_cents + (int) $item->contingency_cents;
        $legacyMarkup = self::ratio($base, self::decimal((string) ($item->margin_percent ?? 0)), 10000);
        $total = $base + $legacyMarkup;
        if ($item->budget?->calculation_mode === 'legacy' && ! $item->management_bps && ! $item->administration_bps) {
            // Preserve the previous single final rounding, without introducing new rates.
            $hundredths = $item->unit_cost_cents * self::decimal((string) $item->quantity)
                + ((int) $item->tax_cents + (int) $item->contingency_cents) * 100;
            $total = self::ratio($hundredths, 10000 + self::decimal((string) ($item->margin_percent ?? 0)), 1000000);
            $legacyMarkup = $total - $base;
        }

        return ['cost' => $cost, 'management' => $management, 'administration' => $administration, 'tax' => (int) $item->tax_cents, 'contingency' => (int) $item->contingency_cents, 'legacyMarkup' => $legacyMarkup, 'total' => $total];
    }
}
