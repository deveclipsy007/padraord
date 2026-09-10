<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierNeed extends Model
{
    protected $fillable = [
        'opportunity_id', 'category', 'scope', 'quantity', 'unit', 'required_date',
        'status', 'technical_requirements', 'revision',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2', 'required_date' => 'date', 'revision' => 'integer'];
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }
}
