<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionChecklistItem extends Model
{
    protected $fillable = [
        'production_checklist_id', 'title', 'requires_photo', 'photo_attachment_id',
        'completed_by', 'completed_at', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['requires_photo' => 'boolean', 'completed_at' => 'datetime'];
    }

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(ProductionChecklist::class, 'production_checklist_id');
    }

    public function photoAttachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class, 'photo_attachment_id');
    }
}
