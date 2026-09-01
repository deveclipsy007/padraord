<?php

namespace App\Models;

use App\Enums\OpportunityStage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Opportunity extends Model
{
    use HasFactory;

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
            'next_action_at' => 'datetime',
            'event_date' => 'date',
            'estimated_value_cents' => 'integer',
            'last_viewed_at' => 'datetime',
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
