<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BriefRequirement extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2', 'evidence_segment_ids' => 'array', 'confirmed_at' => 'datetime'];
    }
}
