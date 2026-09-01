<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContextAudioAsset extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(CaseContextEntry::class, 'case_context_entry_id');
    }
}
