<?php

namespace Tests\Feature;

use App\Enums\CommercialStage;
use App\Models\EventBrief;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\CaseWorkspaceSummary;
use App\Services\CommercialStageTransitionService;
use App\Services\EventBriefService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EventBriefTest extends TestCase
{
    use RefreshDatabase;

    private function case(): Opportunity
    {
        $this->actingAs(User::factory()->create());

        return Opportunity::create(['title' => 'Congresso', 'client_name' => 'Cliente']);
    }

    private function complete(): array
    {
        return ['event_name' => 'Congresso anual', 'event_type' => 'congresso', 'event_format' => 'presencial',
            'starts_at' => '2026-10-10T09:00', 'ends_at' => '2026-10-11T18:00', 'setup_starts_at' => '2026-10-09T09:00', 'teardown_ends_at' => '2026-10-12T10:00',
            'timezone' => 'America/Sao_Paulo', 'date_confidence' => 'confirmed', 'venue_status' => 'confirmed', 'city' => 'Bombinhas', 'state' => 'SC', 'location_note' => 'Centro de eventos',
            'audience_expected_min' => 100, 'audience_expected_max' => 150, 'audience_confidence' => 'confirmed', 'audience_profile' => 'Profissionais',
            'objective' => 'Reunir parceiros', 'scope_summary' => 'Plenária com palco, som e credenciamento', 'budget_declared_cents' => 10000000, 'budget_confidence' => 'confirmed',
            'success_criteria' => [['metric' => 'Participantes', 'target' => '100']]];
    }

    public function test_canonical_context_drives_workspace_and_viability_readiness(): void
    {
        $case = $this->case();
        $this->patch("/opportunities/{$case->id}/event-brief", ['revision' => 0, 'fields' => ['objective' => 'Reunir parceiros']])->assertSessionDoesntHaveErrors();
        $summary = app(CaseWorkspaceSummary::class)->for($case->fresh());
        $this->assertSame('needs_review', collect($summary['moduleStatuses'])->firstWhere('key', 'briefing')['status']);
        $case->update(['commercial_stage' => CommercialStage::INITIAL_BRIEFING]);
        app(CommercialStageTransitionService::class)->transition($case->fresh(), auth()->user(), ['revision' => $case->fresh()->commercial_revision, 'to' => CommercialStage::VIABILITY_OFFER->value]);
        $this->assertSame(CommercialStage::VIABILITY_OFFER, $case->fresh()->commercial_stage);
    }

    public function test_multiday_event_and_typed_fields_are_saved_without_writing_legacy_json(): void
    {
        $case = $this->case();
        $legacy = DB::table('opportunities')->where('id', $case->id)->value('briefing_data');
        $this->patch("/opportunities/{$case->id}/event-brief", ['revision' => 0, 'fields' => $this->complete()])->assertSessionDoesntHaveErrors();
        $brief = EventBrief::where('opportunity_id', $case->id)->firstOrFail();
        $this->assertSame(10000000, $brief->budget_declared_cents);
        $this->assertSame('2026-10-11', $brief->ends_at->format('Y-m-d'));
        $this->assertSame(1, $brief->revision);
        $this->assertSame($legacy, DB::table('opportunities')->where('id', $case->id)->value('briefing_data'));
        $this->get("/opportunities/{$case->id}/briefing")->assertInertia(fn ($p) => $p->where('eventBrief.event_name', 'Congresso anual'));
    }

    public function test_incomplete_approval_names_missing_critical_information(): void
    {
        $case = $this->case();
        $this->post("/opportunities/{$case->id}/event-brief/approve", ['revision' => 0])->assertSessionHasErrors('approval');
        $brief = app(EventBriefService::class)->ensure($case);
        $this->assertContains('ends_at', $brief->missing_critical);
        $this->assertNotSame('approved', $brief->status);
    }

    public function test_approval_requires_explicit_reopen_and_preserves_snapshot(): void
    {
        $case = $this->case();
        $this->patch("/opportunities/{$case->id}/event-brief", ['revision' => 0, 'fields' => $this->complete()])->assertSessionDoesntHaveErrors();
        $this->post("/opportunities/{$case->id}/event-brief/approve", ['revision' => 1])->assertSessionDoesntHaveErrors();
        $brief = EventBrief::where('opportunity_id', $case->id)->firstOrFail();
        $this->assertSame('approved', $brief->status);
        $this->patch("/opportunities/{$case->id}/event-brief", ['revision' => 2, 'fields' => ['objective' => 'Mudança silenciosa']])->assertSessionHasErrors('approval');
        $this->post("/opportunities/{$case->id}/event-brief/reopen", ['revision' => 2, 'reason' => 'Cliente ajustou o objetivo'])->assertSessionDoesntHaveErrors();
        $this->patch("/opportunities/{$case->id}/event-brief", ['revision' => 3, 'fields' => ['objective' => 'Novo objetivo']])->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('event_brief_revisions', ['event_brief_id' => $brief->id, 'revision' => 2, 'action' => 'approved']);
        $approved = json_decode(DB::table('event_brief_revisions')->where('event_brief_id', $brief->id)->where('revision', 2)->value('snapshot'), true);
        $this->assertSame('Reunir parceiros', $approved['objective']);
    }

    public function test_stale_and_invalid_edits_are_rejected_atomically(): void
    {
        $case = $this->case();
        $this->patch("/opportunities/{$case->id}/event-brief", ['revision' => 0, 'fields' => $this->complete()])->assertSessionDoesntHaveErrors();
        $this->patch("/opportunities/{$case->id}/event-brief", ['revision' => 0, 'fields' => ['objective' => 'Atrasado']])->assertSessionHasErrors('revision');
        $this->patch("/opportunities/{$case->id}/event-brief", ['revision' => 1, 'fields' => ['ends_at' => '2026-10-01T08:00']])->assertSessionHasErrors('fields.ends_at');
        $this->patch("/opportunities/{$case->id}/event-brief", ['revision' => 1, 'fields' => ['event_type' => 'inexistente', 'budget_declared_cents' => -1, 'audience_expected_max' => 50]])->assertSessionHasErrors();
        $this->assertSame(1, EventBrief::where('opportunity_id', $case->id)->firstOrFail()->revision);
    }

    public function test_legacy_import_preserves_all_original_values_and_revision_without_inventing_approval(): void
    {
        $case = $this->case();
        $fields = ['objective' => 'Objetivo', 'audience' => '150 pessoas', 'event_date' => '2026-10-10', 'location' => 'Local ainda em estudo', 'budget' => 'Até R$ 100 mil', 'scope' => 'Som e palco', 'restrictions' => 'Sem ruído após 22h', 'references' => 'https://example.test'];
        DB::table('opportunities')->where('id', $case->id)->update(['briefing_data' => json_encode($fields), 'briefing_revision' => 7]);
        $brief = app(EventBriefService::class)->ensure($case->fresh());
        $this->assertSame($fields, $brief->legacy_snapshot);
        $this->assertSame(7, $brief->revision);
        $this->assertSame('Objetivo', $brief->objective);
        $this->assertSame('150 pessoas', $brief->audience_profile);
        $this->assertNull($brief->budget_declared_cents);
        $this->assertNull($brief->approved_at);
    }

    public function test_legacy_review_endpoint_updates_canonical_brief_only(): void
    {
        $case = $this->case();
        $this->post("/opportunities/{$case->id}/briefing/review", ['revision' => 0, 'action' => 'save', 'fields' => ['objective' => 'Objetivo revisado']])->assertSessionDoesntHaveErrors();
        $this->assertNull(DB::table('opportunities')->where('id', $case->id)->value('briefing_data'));
        $this->assertSame('Objetivo revisado', EventBrief::where('opportunity_id', $case->id)->firstOrFail()->objective);
    }
}
