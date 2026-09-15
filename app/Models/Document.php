<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    use HasFactory;

    protected $fillable = ['opportunity_id', 'type', 'version', 'status', 'title', 'content', 'release_snapshot', 'release_hash', 'released_at', 'notes', 'sent_at', 'accepted_at', 'signed_at', 'purpose', 'reviewed_by', 'reviewed_at', 'pdf_path', 'pdf_hash', 'send_evidence', 'reopened_by', 'reopened_at', 'reopen_reason'];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'release_snapshot' => 'array',
            'released_at' => 'datetime',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'signed_at' => 'datetime',
            'reopened_at' => 'datetime',
        ];
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }
}
