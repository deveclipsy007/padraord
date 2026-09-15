<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionTask extends Model
{
    use HasFactory;

    protected $fillable = ['opportunity_id', 'assigned_to', 'title', 'description', 'status', 'priority', 'due_date', 'sort_order', 'phase', 'dependency_id', 'blocked_reason', 'started_at', 'completed_at', 'technical_validation_id', 'source_preview_id', 'source_item_index', 'revision', 'scheduled_starts_at', 'scheduled_ends_at'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'started_at' => 'datetime', 'completed_at' => 'datetime', 'scheduled_starts_at' => 'datetime', 'scheduled_ends_at' => 'datetime', 'revision' => 'integer'];
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function dependency(): BelongsTo
    {
        return $this->belongsTo(self::class, 'dependency_id');
    }

    public function dependents(): HasMany
    {
        return $this->hasMany(self::class, 'dependency_id');
    }

    public function blockers(): HasMany
    {
        return $this->hasMany(CaseBlocker::class, 'production_task_id');
    }

    public function technicalValidation(): BelongsTo
    {
        return $this->belongsTo(TechnicalValidation::class);
    }

    public function sourcePreview(): BelongsTo
    {
        return $this->belongsTo(ProductionTaskPreview::class, 'source_preview_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ProductionTaskAssignment::class)->orderByDesc('is_responsible')->orderBy('id');
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(ProductionChecklist::class);
    }
}
