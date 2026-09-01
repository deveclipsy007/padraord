<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostEventReport extends Model
{
    use HasFactory;

    protected $fillable = ['opportunity_id', 'summary', 'occurrences', 'actual_total_cents', 'learnings', 'status'];

    protected function casts(): array
    {
        return ['occurrences' => 'array', 'actual_total_cents' => 'integer'];
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }
}
