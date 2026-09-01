<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrototypeCase extends Model
{
    protected $fillable = ['created_by', 'title', 'mode', 'revision', 'state'];

    protected function casts(): array
    {
        return ['state' => 'array', 'revision' => 'integer'];
    }
}
