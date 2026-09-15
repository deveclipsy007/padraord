<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseBlocker extends Model
{
    use HasFactory;

    protected $fillable = [
        'opportunity_id',
        'production_task_id',
        'owner_id',
        'stage',
        'title',
        'reason',
        'severity',
        'importance',
        'effort',
        'status',
        'resume_status',
        'resolved_at',
        'resolved_by',
        'resolution_note',
        'reopened_at',
        'reopened_by',
        'reopen_reason',
        'revision',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
            'reopened_at' => 'datetime',
            'revision' => 'integer',
        ];
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(ProductionTask::class, 'production_task_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
