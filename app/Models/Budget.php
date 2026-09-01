<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Budget extends Model
{
    use HasFactory;

    protected $fillable = ['opportunity_id', 'version', 'status', 'notes', 'approved_at', 'approved_by', 'revision', 'purpose', 'calculation_mode', 'snapshot', 'reviewed_at'];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime', 'snapshot' => 'array', 'revision' => 'integer', 'reviewed_at' => 'datetime'];
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BudgetItem::class);
    }
}
