<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionServiceOrder extends Model
{
    protected $fillable = [
        'opportunity_id', 'supplier_id', 'source_quote_id', 'code', 'title', 'amount_cents',
        'scope', 'scope_hash', 'status', 'revision', 'created_by', 'issued_by', 'issued_at',
        'received_by', 'receipt_attachment_id', 'received_at',
    ];

    protected function casts(): array
    {
        return [
            'scope' => 'array',
            'amount_cents' => 'integer',
            'revision' => 'integer',
            'issued_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function sourceQuote(): BelongsTo
    {
        return $this->belongsTo(SupplierQuote::class, 'source_quote_id');
    }

    public function receiptAttachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class, 'receipt_attachment_id');
    }
}
