<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiRun extends Model
{
    protected $fillable = [
        'context_revision', 'decisions',
        'opportunity_id',
        'briefing_message_id',
        'action',
        'provider',
        'model',
        'prompt_version',
        'status',
        'input_hash',
        'input_text',
        'output_payload',
        'input_tokens',
        'output_tokens',
        'duration_ms',
        'cost_micros',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'context_revision' => 'integer', 'decisions' => 'array',
            'output_payload' => 'array',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'duration_ms' => 'integer',
            'cost_micros' => 'integer',
        ];
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function briefingMessage(): BelongsTo
    {
        return $this->belongsTo(BriefingMessage::class);
    }
}
