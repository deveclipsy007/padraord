<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contact extends Model
{
    use HasFactory;

    protected $fillable = ['client_id', 'name', 'email', 'phone', 'role', 'department', 'whatsapp', 'preferred_channel', 'is_primary', 'is_decision_maker', 'revision', 'archived_at', 'archived_by', 'archive_reason'];

    protected function casts(): array
    {
        return ['archived_at' => 'datetime', 'is_primary' => 'boolean', 'is_decision_maker' => 'boolean', 'revision' => 'integer'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('archived_at');
    }
}
