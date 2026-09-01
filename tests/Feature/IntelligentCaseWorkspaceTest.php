<?php

namespace Tests\Feature;

use App\Models\AssistantPreview;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class IntelligentCaseWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_viability_has_a_real_workspace_and_persists_its_draft(): void
    {
        $user = User::factory()->create();
        $case = Opportunity::create(['title' => 'Convenção', 'client_name' => 'Cliente', 'stage' => 'briefing']);

        $this->actingAs($user)->get("/opportunities/{$case->id}/feasibility")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Feasibility')->where('opportunity.id', $case->id));

        $this->actingAs($user)->post("/opportunities/{$case->id}/feasibility", [
            'revision' => 0,
            'modality' => 'express',
            'concept' => 'Uma experiência de integração para a liderança.',
            'experience' => 'Imersiva e objetiva.',
            'technical_assumptions' => 'Auditório para 300 pessoas.',
            'estimate_notes' => 'Estimativa preliminar, sem cotação aprovada.',
            'supplier_needs' => 'Som; iluminação; cenografia',
            'schedule_notes' => 'Montagem na véspera.',
            'references' => 'Referência enviada pelo cliente.',
            'deliverables' => ['concept', 'estimate'],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('viability_projects', [
            'opportunity_id' => $case->id,
            'revision' => 1,
            'modality' => 'express',
            'concept' => 'Uma experiência de integração para a liderança.',
        ]);
        $this->assertDatabaseHas('viability_deliverables', ['opportunity_id' => $case->id, 'key' => 'concept', 'status' => 'draft']);
    }

    public function test_documents_central_keeps_proposal_and_contract_routes_compatible(): void
    {
        $case = Opportunity::create(['title' => 'Convenção', 'client_name' => 'Cliente', 'stage' => 'proposal']);
        $this->actingAs(User::factory()->create());

        $this->get("/opportunities/{$case->id}/documents")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('DocumentsHub')->where('opportunity.id', $case->id));
        $this->get("/opportunities/{$case->id}/proposal")->assertOk();
        $this->get("/opportunities/{$case->id}/proposal?purpose=management")
            ->assertInertia(fn (Assert $page) => $page->where('document.purpose', 'management'));
        $this->get("/opportunities/{$case->id}/contract")->assertOk();
    }

    public function test_text_context_creates_a_reviewable_cross_module_preview_before_mutation(): void
    {
        $user = User::factory()->create();
        $case = Opportunity::create(['title' => 'Convenção', 'client_name' => 'Cliente', 'stage' => 'briefing']);

        $response = $this->actingAs($user)->post("/opportunities/{$case->id}/context", [
            'kind' => 'text',
            'phase' => 'briefing',
            'body' => "Objetivo: integrar a liderança\nPúblico: 300 pessoas\nEscopo: palco; som; iluminação\nConceito: encontro imersivo",
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('case_context_entries', ['opportunity_id' => $case->id, 'status' => 'review']);
        $this->assertDatabaseCount('assistant_previews', 1);
        $this->assertNull($case->fresh()->briefing_data);

        $preview = AssistantPreview::firstOrFail();
        $this->actingAs($user)->post("/opportunities/{$case->id}/context/{$preview->context['entry_id']}/preview/{$preview->id}/confirm", [
            'modules' => ['briefing', 'viability'],
        ])->assertSessionHasNoErrors();

        $this->assertSame('integrar a liderança', $case->fresh()->briefing_data['objective']);
        $this->assertDatabaseHas('viability_projects', ['opportunity_id' => $case->id, 'concept' => 'encontro imersivo']);

        $this->actingAs($user)->post("/opportunities/{$case->id}/context/{$preview->context['entry_id']}/preview/{$preview->id}/confirm", [
            'modules' => ['briefing', 'viability'],
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('viability_projects', 1);
    }

    public function test_reviewer_can_apply_only_selected_changes_from_the_consolidated_preview(): void
    {
        $user = User::factory()->create();
        $case = Opportunity::create(['title' => 'Convenção', 'client_name' => 'Cliente', 'stage' => 'briefing']);

        $this->actingAs($user)->post("/opportunities/{$case->id}/context", [
            'kind' => 'text',
            'phase' => 'briefing',
            'body' => "Objetivo: integrar a liderança\nPúblico: 300 pessoas\nConceito: encontro imersivo",
        ])->assertSessionHasNoErrors();

        $preview = AssistantPreview::firstOrFail();
        $this->actingAs($user)->post("/opportunities/{$case->id}/context/{$preview->context['entry_id']}/preview/{$preview->id}/confirm", [
            'modules' => ['briefing'],
            'changes' => [0],
        ])->assertSessionHasNoErrors();

        $this->assertSame('integrar a liderança', $case->fresh()->briefing_data['objective']);
        $this->assertArrayNotHasKey('audience', $case->fresh()->briefing_data);
        $this->assertDatabaseMissing('viability_projects', ['opportunity_id' => $case->id]);
    }
}
