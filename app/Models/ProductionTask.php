<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionTask extends Model
{
    use HasFactory;

    protected $fillable = ['opportunity_id', 'assigned_to', 'title', 'description', 'status', 'priority', 'due_date', 'sort_order'];

    protected function casts(): array
    {
        return ['due_date' => 'date'];
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }
}
