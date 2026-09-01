<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiCostEntry extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'audio_seconds' => 'integer',
            'input_tokens' => 'integer',
            'cached_input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'estimated_amount_micros' => 'integer',
            'reported_amount_micros' => 'integer',
            'reconciled_amount_micros' => 'integer',
        ];
    }
}
