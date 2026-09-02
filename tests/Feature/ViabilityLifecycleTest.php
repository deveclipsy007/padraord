<?php

namespace Tests\Feature;

use App\Models\Opportunity;
use App\Models\User;
use App\Models\ViabilityProject;
use App\Services\ViabilityLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ViabilityLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_is_blocked_until_required_deliverables_have_evidence(): void
    {
        $opportunity = Opportunity::create(['title' => 'Conferência', 'client_name' => 'Horizonte', 'stage' => 'briefing']);
        $project = ViabilityProject::create([
            'opportunity_id' => $opportunity->id,
            'status' => 'in_development',
            'modality' => 'express',
        ]);
        $project->deliverables()->createMany([
            ['opportunity_id' => $project->opportunity_id, 'key' => 'concept', 'title' => 'Conceito', 'required' => true, 'status' => 'draft'],
            ['opportunity_id' => $project->opportunity_id, 'key' => 'estimate', 'title' => 'Estimativa', 'required' => true, 'status' => 'draft'],
        ]);

        try {
            app(ViabilityLifecycleService::class)->transition($project, User::factory()->create(), 'ready_for_delivery');
            $this->fail('Expected the lifecycle transition to be blocked.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('deliverables', $exception->errors());
        }
    }

    public function test_viability_can_close_without_management_without_losing_the_opportunity(): void
    {
        $opportunity = Opportunity::create([
            'title' => 'Conferência',
            'client_name' => 'Horizonte',
            'stage' => 'contract',
            'commercial_stage' => 'viability_contracted',
        ]);
        $project = ViabilityProject::create([
            'opportunity_id' => $opportunity->id,
            'status' => 'accepted',
            'modality' => 'express',
        ]);

        app(ViabilityLifecycleService::class)->transition(
            $project,
            User::factory()->create(),
            'closed_without_management',
            ['note' => 'Cliente recebeu o projeto e não seguirá para Gestão.']
        );

        $this->assertSame('closed_without_management', $project->fresh()->status);
        $this->assertNotSame('lost', $opportunity->fresh()->commercial_stage);
        $this->assertNotSame('cancelled', $opportunity->fresh()->commercial_stage);
    }

    public function test_authenticated_user_can_record_a_delivery_decision_from_the_workspace(): void
    {
        $user = User::factory()->create();
        $opportunity = Opportunity::create(['title' => 'Conferência', 'client_name' => 'Horizonte', 'stage' => 'briefing']);
        $project = ViabilityProject::create(['opportunity_id' => $opportunity->id, 'status' => 'in_development', 'modality' => 'express']);
        $project->deliverables()->create([
            'opportunity_id' => $opportunity->id,
            'key' => 'concept',
            'title' => 'Conceito',
            'required' => true,
            'status' => 'ready',
            'evidence' => ['source' => 'context-entry:1'],
        ]);

        $this->actingAs($user)
            ->post("/opportunities/{$opportunity->id}/feasibility/lifecycle", [
                'status' => 'ready_for_delivery',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('ready_for_delivery', $project->fresh()->status);
    }

    public function test_acceptance_cannot_skip_delivery(): void
    {
        $project = ViabilityProject::create([
            'opportunity_id' => Opportunity::create(['title' => 'Conferência', 'client_name' => 'Horizonte', 'stage' => 'briefing'])->id,
            'status' => 'in_development',
        ]);

        $this->expectException(ValidationException::class);
        app(ViabilityLifecycleService::class)->transition(
            $project,
            User::factory()->create(),
            'accepted',
            ['note' => 'Aceite informado.']
        );
    }

    public function test_required_deliverable_can_be_marked_ready_only_with_evidence(): void
    {
        $user = User::factory()->create();
        $opportunity = Opportunity::create(['title' => 'Conferência', 'client_name' => 'Horizonte', 'stage' => 'briefing']);
        $project = ViabilityProject::create(['opportunity_id' => $opportunity->id]);
        $deliverable = $project->deliverables()->create([
            'opportunity_id' => $opportunity->id,
            'key' => 'concept',
            'title' => 'Conceito',
            'required' => true,
            'status' => 'draft',
        ]);

        $this->actingAs($user)
            ->post("/opportunities/{$opportunity->id}/feasibility/deliverables/{$deliverable->id}", [
                'status' => 'ready',
            ])
            ->assertSessionHasErrors('evidence');

        $this->post("/opportunities/{$opportunity->id}/feasibility/deliverables/{$deliverable->id}", [
            'status' => 'ready',
            'content' => 'Conceito aprovado para revisão.',
            'evidence' => 'Mensagem da reunião de 02/09.',
        ])->assertSessionHasNoErrors();

        $this->assertSame('ready', $deliverable->fresh()->status);
        $this->assertSame('Mensagem da reunião de 02/09.', $deliverable->fresh()->evidence['note']);
    }
}
