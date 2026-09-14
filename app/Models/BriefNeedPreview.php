<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BriefNeedPreview extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['items' => 'array', 'result' => 'array', 'brief_revision' => 'integer'];
    }
}
