<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OdooCostOutbox extends Model
{
    protected $table = 'odoo_cost_outbox';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'next_attempt_at' => 'datetime',
            'exported_at' => 'datetime',
            'attempts' => 'integer',
            'payload_version' => 'integer',
        ];
    }
}
