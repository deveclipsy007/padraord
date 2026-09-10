<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'industry', 'notes', 'archived_at', 'archived_by', 'archive_reason',
        'legal_name', 'tax_id', 'tax_id_type', 'state_registration', 'municipal_registration',
        'billing_email', 'billing_address', 'default_payment_terms_days', 'segment', 'tier',
        'website', 'instagram',
    ];

    protected function casts(): array
    {
        return ['archived_at' => 'datetime', 'billing_address' => 'array'];
    }

    /**
     * Contrato exige quem assina e onde fatura. Sem isso o documento sai sem
     * as partes identificadas e não serve como instrumento.
     */
    public function missingContractData(): array
    {
        $address = $this->billing_address ?? [];

        return array_values(array_filter([
            blank($this->legal_name) ? 'razão social' : null,
            blank($this->tax_id) ? 'CNPJ ou CPF' : null,
            blank($address['logradouro'] ?? null) ? 'endereço de faturamento' : null,
            blank($address['cidade'] ?? null) ? 'cidade' : null,
            blank($address['uf'] ?? null) ? 'estado' : null,
        ]));
    }

    public function readyForContract(): bool
    {
        return $this->missingContractData() === [];
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('archived_at');
    }
}
