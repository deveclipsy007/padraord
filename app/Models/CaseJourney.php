<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaseJourney extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['revision' => 'integer', 'deliverables' => 'array', 'evidence' => 'array'];
    }
}
