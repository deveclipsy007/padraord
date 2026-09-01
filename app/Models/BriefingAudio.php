<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BriefingAudio extends Model
{
    protected $table = 'briefing_audio';

    protected $guarded = [];

    protected $hidden = ['path', 'digest'];

    protected function casts(): array
    {
        return ['segments' => 'array', 'speaker_names' => 'array', 'expires_at' => 'datetime', 'revision' => 'integer', 'forwarded_revision' => 'integer'];
    }
}
