<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AudioUploadSession extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
