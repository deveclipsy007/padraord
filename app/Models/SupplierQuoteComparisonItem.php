<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierQuoteComparisonItem extends Model
{
    protected $fillable = ['comparison_id', 'source_quote_id', 'normalized_total_cents', 'snapshot'];

    protected function casts(): array
    {
        return ['normalized_total_cents' => 'integer', 'snapshot' => 'array'];
    }

    public function comparison(): BelongsTo
    {
        return $this->belongsTo(SupplierQuoteComparison::class, 'comparison_id');
    }

    public function sourceQuote(): BelongsTo
    {
        return $this->belongsTo(SupplierQuote::class, 'source_quote_id');
    }
}
