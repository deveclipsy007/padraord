<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TechnicalValidation extends Model
{
    use HasFactory;

    protected $fillable = [
        'opportunity_id', 'reference', 'measurements', 'drawing_path', 'evidence', 'supplier_name', 'status',
        'revision', 'confirmed_by', 'confirmed_at', 'invalidated_at', 'invalidated_reason', 'confirmation_evidence',
    ];

    protected function casts(): array
    {
        return ['measurements' => 'array', 'revision' => 'integer', 'confirmed_at' => 'datetime', 'invalidated_at' => 'datetime'];
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProductionTask::class);
    }
}
