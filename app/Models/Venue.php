<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venue extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'address' => 'array',
            'has_loading_dock' => 'boolean',
            'has_freight_elevator' => 'boolean',
            'has_generator_area' => 'boolean',
            'has_kitchen' => 'boolean',
        ];
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    public function technicalValidations(): HasMany
    {
        return $this->hasMany(TechnicalValidation::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'ativo');
    }

    /**
     * Confere se uma medida declarada passa pelo acesso de carga do local.
     * Devolve o que não cabe, com os dois números, para a equipe decidir —
     * o sistema não escolhe desmontar a estrutura nem trocar de local.
     *
     * @param  array<string, float|int|string|null>  $measurements
     * @return list<string>
     */
    public function accessConflicts(array $measurements): array
    {
        $conflicts = [];
        $checks = [
            'largura' => ['door_width_m', 'largura da porta'],
            'width' => ['door_width_m', 'largura da porta'],
            'altura' => ['door_height_m', 'altura da porta'],
            'height' => ['door_height_m', 'altura da porta'],
        ];

        foreach ($measurements as $key => $rawValue) {
            $normalised = mb_strtolower(trim((string) $key));
            if (! isset($checks[$normalised])) {
                continue;
            }
            [$column, $label] = $checks[$normalised];
            $limit = $this->{$column};
            $value = self::metres($rawValue);
            if ($limit === null || $value === null) {
                continue;
            }
            if ($value > (float) $limit) {
                $conflicts[] = sprintf(
                    '%s de %s m não passa pela %s de %s m.',
                    ucfirst($normalised),
                    self::format($value),
                    $label,
                    self::format((float) $limit),
                );
            }
        }

        return $conflicts;
    }

    /** Aceita "8", "8m", "8,5 m" e "8.5". Qualquer outra coisa é ignorada. */
    private static function metres(mixed $value): ?float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        if (! is_string($value)) {
            return null;
        }
        $cleaned = str_replace(',', '.', trim(preg_replace('/\s*m(etros?)?\s*$/iu', '', $value) ?? ''));

        return is_numeric($cleaned) ? (float) $cleaned : null;
    }

    private static function format(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', ''), '0'), ',');
    }
}
