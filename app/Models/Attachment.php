<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    protected $guarded = [];

    protected $hidden = ['path'];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer', 'archived_at' => 'datetime'];
    }
}
