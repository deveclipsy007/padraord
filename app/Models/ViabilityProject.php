<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ViabilityProject extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['revision' => 'integer', 'snapshot' => 'array'];
    }

    public function deliverables(): HasMany
    {
        return $this->hasMany(ViabilityDeliverable::class);
    }
}
