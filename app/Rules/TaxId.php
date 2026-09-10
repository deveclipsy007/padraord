<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * CNPJ ou CPF com dígito verificador conferido. Sem pacote externo: o cálculo
 * é público e estável, e a dependência não se justifica.
 *
 * Guarda-se apenas os dígitos; a formatação é da interface.
 */
class TaxId implements ValidationRule
{
    public function __construct(private string $type = 'cnpj') {}

    public static function digits(?string $value): string
    {
        return preg_replace('/\D/', '', (string) $value) ?? '';
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digits = self::digits(is_string($value) ? $value : '');

        if ($this->type === 'estrangeiro') {
            if ($digits === '' && trim((string) $value) === '') {
                $fail('Informe o documento fiscal do cliente estrangeiro.');
            }

            return;
        }

        $valid = $this->type === 'cpf' ? self::isCpf($digits) : self::isCnpj($digits);

        if (! $valid) {
            $fail($this->type === 'cpf' ? 'Informe um CPF válido.' : 'Informe um CNPJ válido.');
        }
    }

    public static function isCpf(string $digits): bool
    {
        if (strlen($digits) !== 11 || preg_match('/^(\d)\1{10}$/', $digits)) {
            return false;
        }

        foreach ([9, 10] as $position) {
            $sum = 0;
            for ($i = 0; $i < $position; $i++) {
                $sum += (int) $digits[$i] * (($position + 1) - $i);
            }
            $remainder = ($sum * 10) % 11;
            $expected = $remainder === 10 ? 0 : $remainder;
            if ($expected !== (int) $digits[$position]) {
                return false;
            }
        }

        return true;
    }

    public static function isCnpj(string $digits): bool
    {
        if (strlen($digits) !== 14 || preg_match('/^(\d)\1{13}$/', $digits)) {
            return false;
        }

        foreach ([[12, [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]], [13, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]]] as [$position, $weights]) {
            $sum = 0;
            foreach ($weights as $index => $weight) {
                $sum += (int) $digits[$index] * $weight;
            }
            $remainder = $sum % 11;
            $expected = $remainder < 2 ? 0 : 11 - $remainder;
            if ($expected !== (int) $digits[$position]) {
                return false;
            }
        }

        return true;
    }
}
