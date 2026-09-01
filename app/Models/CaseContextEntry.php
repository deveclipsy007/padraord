<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CaseContextEntry extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'revision' => 'integer',
            'case_revision' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function audioAsset(): HasOne
    {
        return $this->hasOne(ContextAudioAsset::class);
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function segments(): HasMany
    {
        return $this->hasMany(CaseContextSegment::class)->orderBy('sequence');
    }
}
