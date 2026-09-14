<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventBrief extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        $casts = ['revision' => 'integer', 'completeness_score' => 'integer', 'is_recurring' => 'boolean', 'has_vip' => 'boolean', 'budget_includes_taxes' => 'boolean'];
        foreach (['starts_at', 'ends_at', 'setup_starts_at', 'teardown_ends_at', 'approved_at'] as $field) {
            $casts[$field] = 'datetime';
        }
        foreach (['alternative_dates', 'audience_segments', 'success_criteria', 'brand_assets', 'constraints', 'risks', 'deadlines', 'references', 'legacy_snapshot', 'missing_critical'] as $field) {
            $casts[$field] = 'array';
        }
        foreach (['budget_declared_cents', 'budget_range_min_cents', 'budget_range_max_cents', 'audience_expected_min', 'audience_expected_max'] as $field) {
            $casts[$field] = 'integer';
        }

        return $casts;
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function contextFields(): array
    {
        return [
            'objective' => $this->objective ?? '', 'audience' => $this->audience_profile ?? '',
            'event_date' => $this->starts_at?->timezone($this->timezone)->format('Y-m-d') ?? $this->legacy_date_note ?? '',
            'location' => $this->location_note ?? $this->city ?? '', 'budget' => $this->budget_notes ?? ($this->budget_declared_cents !== null ? number_format($this->budget_declared_cents / 100, 2, ',', '.') : ''),
            'scope' => $this->scope_summary ?? '', 'restrictions' => $this->restrictions_notes ?? '', 'references' => $this->references_notes ?? '',
        ];
    }
}
