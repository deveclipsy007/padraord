<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionTaskPreview extends Model
{
    use HasFactory;

    protected $fillable = ['opportunity_id', 'created_by', 'source_hash', 'source', 'items', 'status', 'result', 'confirmed_at'];

    protected function casts(): array
    {
        return ['source' => 'array', 'items' => 'array', 'result' => 'array', 'confirmed_at' => 'datetime'];
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
