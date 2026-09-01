<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrototypeFeedback extends Model
{
    use HasFactory;

    protected $table = 'prototype_feedback';

    protected $fillable = ['user_id', 'rating', 'category', 'comment', 'context'];
}
