<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionChecklist extends Model
{
    protected $fillable = [
        'opportunity_id', 'production_task_id', 'phase', 'title', 'status', 'revision',
        'created_by', 'completed_by', 'completed_at',
    ];

    protected function casts(): array
    {
        return ['revision' => 'integer', 'completed_at' => 'datetime'];
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(ProductionTask::class, 'production_task_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductionChecklistItem::class)->orderBy('sort_order')->orderBy('id');
    }
}
