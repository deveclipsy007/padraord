<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BriefingMessage extends Model
{
    protected $fillable = [
        'opportunity_id',
        'reply_to_message_id',
        'role',
        'source',
        'body',
        'metadata',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'accepted_at' => 'datetime',
        ];
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_message_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'reply_to_message_id');
    }
}
