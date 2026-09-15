<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionTaskAssignment extends Model
{
    protected $fillable = ['production_task_id', 'user_id', 'role', 'is_responsible'];

    protected function casts(): array
    {
        return ['is_responsible' => 'boolean'];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(ProductionTask::class, 'production_task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
