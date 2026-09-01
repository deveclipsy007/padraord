<?php

namespace App\Models;

use App\Enums\CommercialStage;
use App\Enums\OpportunityStage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Opportunity extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $opportunity): void {
            if ($opportunity->getAttribute('commercial_stage') === null) {
                $stage = $opportunity->getAttribute('stage');
                $stageValue = $stage instanceof OpportunityStage ? $stage->value : (string) $stage;
                $opportunity->setAttribute('commercial_stage', match ($stageValue) {
                    'qualification' => CommercialStage::QUALIFICATION,
                    'briefing' => CommercialStage::INITIAL_BRIEFING,
                    'budget', 'proposal', 'negotiation' => CommercialStage::VIABILITY_OFFER,
                    'contract', 'pre_production', 'production', 'post_event', 'closed' => CommercialStage::VIABILITY_CONTRACTED,
                    'lost' => CommercialStage::LOST,
                    'cancelled' => CommercialStage::CANCELLED,
                    default => CommercialStage::LEAD,
                });
            }
        });
    }

    protected $fillable = [
        'briefing_data', 'briefing_revision', 'briefing_approval',
        'title',
        'client_id',
        'contact_id',
        'owner_id',
        'client_name',
        'contact_name',
        'contact_email',
        'stage',
        'next_action',
        'next_action_at',
        'event_date',
        'estimated_value_cents',
        'briefing_status',
        'commercial_stage', 'origin', 'priority', 'commercial_revision',
        'archived_at', 'archived_by', 'archive_reason', 'reason_category', 'reason_note',
        'location',
        'objective',
        'stage_note',
        'last_viewed_at',
    ];

    protected function casts(): array
    {
        return [
            'briefing_data' => 'array', 'briefing_revision' => 'integer', 'briefing_approval' => 'array',
            'stage' => OpportunityStage::class,
            'commercial_stage' => CommercialStage::class,
            'next_action_at' => 'datetime',
            'event_date' => 'date',
            'estimated_value_cents' => 'integer',
            'last_viewed_at' => 'datetime',
            'archived_at' => 'datetime',
            'commercial_revision' => 'integer',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function qualification(): HasOne
    {
        return $this->hasOne(OpportunityQualification::class);
    }

    public function briefingMessages(): HasMany
    {
        return $this->hasMany(BriefingMessage::class);
    }

    public function aiRuns(): HasMany
    {
        return $this->hasMany(AiRun::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function productionTasks(): HasMany
    {
        return $this->hasMany(ProductionTask::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function postEventReport(): HasOne
    {
        return $this->hasOne(PostEventReport::class);
    }
}
