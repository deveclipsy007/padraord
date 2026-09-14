<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BriefProgramBlock extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['sequence' => 'integer', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'evidence_segment_ids' => 'array'];
    }
}
