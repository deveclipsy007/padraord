<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BriefFieldSource extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['segment_ids' => 'array', 'source_snapshot' => 'array', 'confirmed_at' => 'datetime', 'extracted_at' => 'datetime', 'approved_at' => 'datetime'];
    }
}
