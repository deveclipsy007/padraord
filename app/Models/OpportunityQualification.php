<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpportunityQualification extends Model
{
    use HasFactory;

    protected $fillable = [
        'opportunity_id', 'revision', 'need_summary', 'decision_maker_status', 'decision_maker_contact_id',
        'event_date_status', 'budget_status', 'fit_status', 'notes', 'status', 'qualified_at', 'qualified_by',
    ];

    protected function casts(): array
    {
        return ['revision' => 'integer', 'qualified_at' => 'datetime'];
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function decisionMaker(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'decision_maker_contact_id');
    }

    public function qualifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'qualified_by');
    }
}
