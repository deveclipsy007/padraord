<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseContextSegment extends Model
{
    protected $guarded = [];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(CaseContextEntry::class, 'case_context_entry_id');
    }
}
