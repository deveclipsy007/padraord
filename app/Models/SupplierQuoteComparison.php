<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierQuoteComparison extends Model
{
    protected $fillable = [
        'opportunity_id',
        'created_by',
        'decision_quote_id',
        'title',
        'scope_difference',
        'justification',
        'status',
        'decided_at',
    ];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function decisionQuote(): BelongsTo
    {
        return $this->belongsTo(SupplierQuote::class, 'decision_quote_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplierQuoteComparisonItem::class, 'comparison_id');
    }
}
