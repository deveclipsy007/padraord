<?php

namespace Tests\Feature;

use App\Models\BriefRequirement;
use App\Models\Budget;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\BriefRequirementService;
use App\Services\EventBriefService;
use App\Services\ProductionOperations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BriefRequirementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_requirements_complement_production_preview_and_reopening_invalidates_it(): void
    {
        $actor = User::factory()->create();
        $case = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente']);
        $brief = app(EventBriefService::class)->ensure($case);
        $brief->update(['status' => 'approved', 'approved_at' => now()]);
        $budget = Budget::create(['opportunity_id' => $case->id, 'version' => 1, 'status' => 'approved', 'purpose' => 'management', 'snapshot' => ['demo' => false]]);
        $budget->items()->create(['category' => 'Estrutura', 'description' => 'Palco', 'quantity' => 1, 'unit' => 'pacote', 'unit_cost_cents' => 1000, 'margin_percent' => 0]);
        BriefRequirement::create(['event_brief_id' => $brief->id, 'area' => 'acessibilidade', 'requirement' => 'Conferir rota acessível', 'source' => 'Cliente', 'classification' => 'fact', 'status' => 'confirmed', 'quantity' => 1, 'unit' => 'serviço']);
        BriefRequirement::create(['event_brief_id' => $brief->id, 'area' => 'som', 'requirement' => 'Hipótese não confirmada', 'source' => 'Reunião', 'classification' => 'hypothesis', 'status' => 'draft', 'quantity' => 1, 'unit' => 'pacote']);
        $service = app(ProductionOperations::class);
        $preview = $service->prepareFromApprovedScope($case, $actor);
        $this->assertCount(2, $preview->items);
        $this->assertSame('Conferir rota acessível', $preview->items[1]['description']);
        app(EventBriefService::class)->reopen($case, $actor, 0, 'Cliente solicitou revisão');
        $this->expectException(ValidationException::class);
        $service->confirmScopePreview($case, $preview, $actor);
    }

    public function test_hypothesis_requires_item_confirmation_and_generates_a_need_only_once(): void
    {
        $actor = User::factory()->create();
        $this->actingAs($actor);
        $case = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente']);
        $this->post("/opportunities/{$case->id}/event-brief/requirements", ['revision' => 0, 'area' => 'som', 'requirement' => 'Sonorização plenária', 'quantity' => '2.00', 'unit' => 'caixa', 'priority' => 'obrigatorio', 'classification' => 'hypothesis', 'source' => 'Reunião comercial', 'evidence_segment_ids' => []])->assertSessionDoesntHaveErrors();
        $requirement = BriefRequirement::firstOrFail();
        $this->assertSame('hypothesis', $requirement->classification);
        $this->assertDatabaseCount('supplier_needs', 0);
        $service = app(BriefRequirementService::class);
        $preview = $service->previewNeeds($case, $actor);
        $this->post("/opportunities/{$case->id}/event-brief/needs-preview/{$preview->id}/confirm", ['requirement_ids' => [$requirement->id], 'confirm_uncertain_ids' => []])->assertSessionHasErrors('confirmation');
        $payload = ['requirement_ids' => [$requirement->id], 'confirm_uncertain_ids' => [$requirement->id]];
        $this->post("/opportunities/{$case->id}/event-brief/needs-preview/{$preview->id}/confirm", $payload)->assertSessionDoesntHaveErrors();
        $this->post("/opportunities/{$case->id}/event-brief/needs-preview/{$preview->id}/confirm", $payload)->assertSessionDoesntHaveErrors();
        $this->assertDatabaseCount('supplier_needs', 1);
        $this->assertDatabaseHas('supplier_needs', ['category' => 'som', 'scope' => 'Sonorização plenária']);
        $this->assertSame($actor->id, $requirement->fresh()->confirmed_by);
        $this->assertSame('hypothesis', $requirement->fresh()->classification);
    }

    public function test_cross_case_evidence_invalid_area_and_stale_preview_are_refused(): void
    {
        $actor = User::factory()->create();
        $this->actingAs($actor);
        $case = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente']);
        $this->post("/opportunities/{$case->id}/event-brief/requirements", ['revision' => 0, 'area' => 'qualquer_coisa', 'requirement' => 'Item', 'classification' => 'fact'])->assertSessionHasErrors('area');
        $this->post("/opportunities/{$case->id}/event-brief/requirements", ['revision' => 0, 'area' => 'som', 'requirement' => 'Som', 'quantity' => '1', 'unit' => 'pacote', 'priority' => 'obrigatorio', 'classification' => 'fact', 'source' => 'Cliente', 'evidence_segment_ids' => [999999]])->assertSessionHasErrors('evidence_segment_ids');
        $service = app(BriefRequirementService::class);
        $service->save($case, $actor, null, ['revision' => 0, 'area' => 'som', 'requirement' => 'Som', 'quantity' => '1', 'unit' => 'pacote', 'priority' => 'obrigatorio', 'classification' => 'fact', 'source' => 'Cliente', 'evidence_segment_ids' => []]);
        $preview = $service->previewNeeds($case, $actor);
        app(EventBriefService::class)->mutate($case, $actor, 1, ['objective' => 'Contexto alterado']);
        $this->post("/opportunities/{$case->id}/event-brief/needs-preview/{$preview->id}/confirm", ['requirement_ids' => [BriefRequirement::firstOrFail()->id]])->assertSessionHasErrors('revision');
        $this->assertDatabaseCount('supplier_needs', 0);
    }
}
