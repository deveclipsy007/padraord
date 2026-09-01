<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OnboardingProgress extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'completed_steps', 'dismissed', 'completed_at'];

    protected function casts(): array
    {
        return ['completed_steps' => 'array', 'dismissed' => 'boolean', 'completed_at' => 'datetime'];
    }
}
