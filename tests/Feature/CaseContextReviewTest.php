<?php

namespace Tests\Feature;

use App\Models\AssistantPreview;
use App\Models\CaseContextEntry;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\CaseContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseContextReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirming_a_case_change_applies_only_an_allowed_case_field_with_human_review(): void
    {
        $user = User::factory()->create();
        $opportunity = Opportunity::create([
            'title' => 'Conferência Horizonte',
            'client_name' => 'Horizonte',
            'stage' => 'briefing',
        ]);
        $entry = CaseContextEntry::create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $user->id,
            'kind' => 'text',
            'phase' => 'briefing',
            'status' => 'review',
            'body' => 'Local: Centro de Eventos',
            'digest' => hash('sha256', 'context-review-case'),
        ]);
        $preview = AssistantPreview::create([
            'user_id' => $user->id,
            'mode' => 'review',
            'message' => 'Local explícito.',
            'context' => [
                'entry_id' => $entry->id,
                'opportunity_id' => $opportunity->id,
                'case_hash' => app(CaseContextService::class)->caseFingerprint($opportunity->fresh()),
                'briefing_revision' => 0,
                'viability_revision' => 0,
            ],
            'actions' => [[
                'module' => 'case',
                'field' => 'location',
                'current' => null,
                'suggested' => 'Centro de Eventos',
                'reason' => 'Informação explicitamente identificada.',
                'kind' => 'fact',
                'evidence' => 'Local: Centro de Eventos',
                'impacts' => ['briefing', 'viability'],
            ]],
            'status' => 'preview',
        ]);

        app(CaseContextService::class)->confirm($opportunity, $entry, $preview, $user, ['case']);

        $this->assertSame('Centro de Eventos', $opportunity->fresh()->location);
        $this->assertSame('confirmed', $preview->fresh()->status);
    }

    public function test_explicit_location_in_manual_context_becomes_a_reviewable_case_draft(): void
    {
        $user = User::factory()->create();
        $opportunity = Opportunity::create([
            'title' => 'Conferência Horizonte',
            'client_name' => 'Horizonte',
            'stage' => 'briefing',
        ]);

        $preview = app(CaseContextService::class)->createPreview($opportunity, $user, [
            'kind' => 'text',
            'phase' => 'briefing',
            'body' => 'Local: Centro de Eventos',
        ]);

        $this->assertSame('case', $preview->actions[0]['module']);
        $this->assertSame('location', $preview->actions[0]['field']);
        $this->assertSame('Centro de Eventos', $preview->actions[0]['suggested']);
    }
}
