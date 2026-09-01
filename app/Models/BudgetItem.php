<?php

namespace App\Models;

use App\Services\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetItem extends Model
{
    use HasFactory;

    protected $fillable = ['budget_id', 'category', 'description', 'quantity', 'unit', 'unit_cost_cents', 'tax_cents', 'contingency_cents', 'margin_percent', 'supplier', 'quote_valid_until', 'notes', 'supplier_quote_id', 'management_bps', 'administration_bps'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2', 'margin_percent' => 'decimal:2', 'quote_valid_until' => 'date'];
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function getSellTotalCentsAttribute(): int
    {
        return Money::breakdown($this)['total'];
    }
}
