<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsageEvent extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'event', 'context'];

    protected function casts(): array
    {
        return ['context' => 'array'];
    }
}
