<?php

namespace Tests\Feature;

use App\Enums\OpportunityStage;
use App\Models\Budget;
use App\Models\Opportunity;
use App\Models\ProductionTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_sent_to_the_team_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_a_team_member_can_login_and_open_the_opportunity_hub(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        $opportunity = Opportunity::create([
            'title' => 'Conferência Horizonte 2026',
            'client_name' => 'Horizonte Educação',
            'stage' => OpportunityStage::BRIEFING,
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/');

        $this->get(route('opportunities.show', $opportunity))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('OpportunityShow')
                ->where('opportunity.title', 'Conferência Horizonte 2026')
                ->has('readiness'));
    }

    public function test_stage_transition_records_the_reason_and_updates_the_pipeline(): void
    {
        $user = User::factory()->create();
        $opportunity = Opportunity::create([
            'title' => 'Jornada Aurora',
            'client_name' => 'Aurora Eventos',
            'contact_name' => 'Marina Costa',
            'stage' => OpportunityStage::LEAD,
        ]);

        $response = $this->actingAs($user)->post(route('opportunities.stage', $opportunity), [
            'stage' => OpportunityStage::QUALIFICATION->value,
            'note' => 'Contato inicial confirmado.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('opportunities', [
            'id' => $opportunity->id,
            'stage' => OpportunityStage::QUALIFICATION->value,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'opportunity.stage_changed',
            'subject_id' => $opportunity->id,
        ]);
    }

    public function test_a_budget_item_and_production_task_are_persisted_for_the_event(): void
    {
        $user = User::factory()->create();
        $opportunity = Opportunity::create([
            'title' => 'Festival Vértice',
            'client_name' => 'Vértice Cultura',
            'stage' => OpportunityStage::BUDGET,
        ]);

        $this->actingAs($user)->post(route('opportunities.budget.items.store', $opportunity), [
            'category' => 'Audiovisual',
            'description' => 'Locação de painel de LED',
            'quantity' => 1,
            'unit' => 'diária',
            'unit_cost_cents' => 120000,
            'revision' => 0,
            'margin_percent' => 20,
        ])->assertRedirect();

        $this->actingAs($user)->post(route('opportunities.production.tasks.store', $opportunity), [
            'title' => 'Confirmar fornecedor de audiovisual',
            'due_date' => '2026-09-10',
            'priority' => 'high',
        ])->assertRedirect();

        $task = ProductionTask::query()->firstOrFail();
        $this->actingAs($user)->patch(route('production.tasks.update', $task), ['status' => 'done'])->assertRedirect();

        $this->assertDatabaseCount('budgets', 1);
        $this->assertDatabaseHas('budget_items', ['description' => 'Locação de painel de LED']);
        $this->assertDatabaseHas('production_tasks', ['id' => $task->id, 'status' => 'done']);
    }

    public function test_feedback_is_stored_with_the_current_context(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('feedback.store'), [
            'rating' => 5,
            'category' => 'usability',
            'comment' => 'A trilha de etapas ficou clara.',
            'context' => '/opportunities/1',
        ])->assertRedirect();

        $this->assertDatabaseHas('prototype_feedback', [
            'rating' => 5,
            'category' => 'usability',
            'user_id' => $user->id,
        ]);
    }

    public function test_the_operational_navigation_has_real_pages_for_the_pilot(): void
    {
        $user = User::factory()->create();
        $opportunity = Opportunity::create([
            'title' => 'Jornada Completa',
            'client_name' => 'Cliente Piloto',
            'stage' => OpportunityStage::PROPOSAL,
        ]);

        foreach (['pipeline', 'briefings', 'agenda', 'clients', 'history', 'help'] as $route) {
            $this->actingAs($user)->get(route($route))->assertOk();
        }

        $this->actingAs($user)->get(route('opportunities.document', [$opportunity, 'proposal']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('DocumentWorkspace')->where('document.type', 'proposal'));

        $this->actingAs($user)->get(route('opportunities.post-event', $opportunity))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('PostEvent'));
    }

    public function test_global_search_returns_opportunities_and_shortcuts(): void
    {
        $user = User::factory()->create();
        $opportunity = Opportunity::create([
            'title' => 'Busca Horizonte',
            'client_name' => 'Cliente Horizonte',
            'stage' => OpportunityStage::LEAD,
        ]);

        $this->actingAs($user)->getJson('/search?q=Horizonte')
            ->assertOk()
            ->assertJsonPath('opportunities.0.id', $opportunity->id)
            ->assertJsonFragment(['label' => 'Pipeline']);
    }

    public function test_advancing_without_critical_context_returns_a_readiness_warning(): void
    {
        $user = User::factory()->create();
        $opportunity = Opportunity::create([
            'title' => 'Sem contexto',
            'client_name' => 'Cliente',
            'stage' => OpportunityStage::LEAD,
        ]);

        $this->actingAs($user)->post(route('opportunities.stage', $opportunity), [
            'stage' => OpportunityStage::QUALIFICATION->value,
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseHas('opportunities', ['id' => $opportunity->id, 'stage' => OpportunityStage::LEAD->value]);
    }

    public function test_a_draft_budget_can_be_reviewed_in_demo_without_claiming_approval(): void
    {
        $user = User::factory()->create();
        $opportunity = Opportunity::create(['title' => 'Orçamento aprovado', 'client_name' => 'Cliente', 'stage' => OpportunityStage::BUDGET]);
        $budget = Budget::create(['opportunity_id' => $opportunity->id, 'version' => 1, 'status' => 'draft']);
        $budget->items()->create(['category' => 'Equipe', 'description' => 'Coordenação', 'quantity' => 1, 'unit' => 'diária', 'unit_cost_cents' => 100000, 'margin_percent' => 20]);

        $this->actingAs($user)->post(route('opportunities.budget.approve', $opportunity), ['revision' => 0, 'demo' => true])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('budgets', ['id' => $budget->id, 'status' => 'reviewed_demo', 'approved_by' => null]);
    }
}
