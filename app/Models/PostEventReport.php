<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostEventReport extends Model
{
    use HasFactory;

    protected $fillable = ['opportunity_id', 'summary', 'occurrences', 'actual_total_cents', 'planned_total_cents', 'learnings', 'supplier_evaluations', 'closure_items', 'status', 'closed_by', 'closed_at'];

    protected function casts(): array
    {
        return ['occurrences' => 'array', 'actual_total_cents' => 'integer', 'planned_total_cents' => 'integer', 'supplier_evaluations' => 'array', 'closure_items' => 'array', 'closed_at' => 'datetime'];
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
