<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierQuote extends Model
{
    protected $fillable = ['supplier_id', 'opportunity_id', 'service', 'unit_cost_cents', 'valid_until', 'conditions', 'evidence', 'price_basis', 'quantity', 'unit', 'supersedes_id'];

    protected function casts(): array
    {
        return ['valid_until' => 'date', 'unit_cost_cents' => 'integer', 'quantity' => 'decimal:2'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
