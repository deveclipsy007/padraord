<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssistantPreview extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['context' => 'array', 'actions' => 'array', 'result' => 'array'];
    }
}
