<?php

namespace Tests\Feature;

use App\Enums\CommercialStage;
use App\Models\Activity;
use App\Models\CaseJourney;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\CaseJourneyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_stage_is_mapped_to_the_new_commercial_stage_without_changing_it(): void
    {
        $case = Opportunity::create([
            'title' => 'Caso legado',
            'client_name' => 'Cliente legado',
            'stage' => 'budget',
        ]);

        $this->assertSame('budget', $case->fresh()->stage->value);
        $this->assertSame(CommercialStage::VIABILITY_OFFER, $case->fresh()->commercial_stage);
    }

    public function test_qualification_checklist_can_be_saved_and_requires_the_minimum_for_meeting(): void
    {
        $user = User::factory()->create();
        $client = Client::create(['name' => 'Horizonte']);
        $contact = Contact::create(['client_id' => $client->id, 'name' => 'Marina']);
        $case = Opportunity::create([
            'title' => 'Conferência',
            'client_id' => $client->id,
            'client_name' => $client->name,
            'contact_id' => $contact->id,
            'contact_name' => $contact->name,
            'owner_id' => $user->id,
            'stage' => 'lead',
        ]);

        $this->actingAs($user)->post("/opportunities/{$case->id}/next-action", [
            'title' => 'Confirmar qualificação',
            'user_id' => $user->id,
            'due_at' => '2026-09-05 10:00:00',
        ])->assertRedirect();
        $this->actingAs($user)->post("/opportunities/{$case->id}/commercial-stage", [
            'to' => CommercialStage::QUALIFICATION->value,
            'revision' => 0,
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $this->actingAs($user)->post("/opportunities/{$case->id}/qualification", [
            'need_summary' => 'Aproximar a comunidade de educadores.',
            'decision_maker_status' => 'unknown',
            'event_date_status' => 'unknown',
            'budget_status' => 'unknown',
            'fit_status' => 'high',
            'status' => 'qualified',
            'notes' => 'Data e investimento serão confirmados na reunião.',
        ])->assertRedirect();

        $this->assertDatabaseHas('opportunity_qualifications', [
            'opportunity_id' => $case->id,
            'status' => 'qualified',
            'event_date_status' => 'unknown',
        ]);

        $this->actingAs($user)->post("/opportunities/{$case->id}/commercial-stage", [
            'to' => CommercialStage::MEETING->value,
            'revision' => 1,
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $this->assertSame(CommercialStage::MEETING, $case->fresh()->commercial_stage);
    }

    public function test_lead_cannot_advance_without_a_next_action_and_deadline(): void
    {
        $user = User::factory()->create();
        $case = Opportunity::create(['title' => 'Sem próximo passo', 'client_name' => 'Cliente', 'stage' => 'lead', 'owner_id' => $user->id]);

        $this->actingAs($user)->post("/opportunities/{$case->id}/commercial-stage", [
            'to' => CommercialStage::QUALIFICATION->value,
            'revision' => 0,
        ])->assertSessionHasErrors('transition');
    }

    public function test_loss_requires_a_reason_category_and_note(): void
    {
        $user = User::factory()->create();
        $case = Opportunity::create(['title' => 'Perdido', 'client_name' => 'Cliente', 'stage' => 'lead', 'owner_id' => $user->id]);

        $this->actingAs($user)->post("/opportunities/{$case->id}/commercial-stage", [
            'to' => CommercialStage::LOST->value,
            'revision' => 0,
        ])->assertSessionHasErrors(['reason_category', 'reason_note']);

        $this->actingAs($user)->post("/opportunities/{$case->id}/commercial-stage", [
            'to' => CommercialStage::LOST->value,
            'revision' => 0,
            'reason_category' => 'no_budget',
            'reason_note' => 'O investimento aprovado ficou abaixo do mínimo.',
        ])->assertRedirect();

        $this->assertSame(CommercialStage::LOST, $case->fresh()->commercial_stage);
        $this->assertDatabaseHas('audit_logs', ['action' => 'opportunity.commercial_stage_changed']);
    }

    public function test_only_one_active_next_action_is_kept_per_opportunity(): void
    {
        $user = User::factory()->create();
        $case = Opportunity::create(['title' => 'Próxima ação', 'client_name' => 'Cliente', 'stage' => 'lead', 'owner_id' => $user->id]);

        $this->actingAs($user)->post("/opportunities/{$case->id}/next-action", [
            'title' => 'Marcar conversa inicial',
            'user_id' => $user->id,
            'due_at' => '2026-09-05 10:00:00',
        ])->assertRedirect();
        $this->actingAs($user)->post("/opportunities/{$case->id}/next-action", [
            'title' => 'Enviar perguntas de qualificação',
            'user_id' => $user->id,
            'due_at' => '2026-09-06 10:00:00',
        ])->assertRedirect();

        $this->assertSame(1, Activity::query()->where('opportunity_id', $case->id)->where('is_next_action', true)->whereNotIn('status', ['done', 'cancelled'])->count());
        $this->assertSame('Enviar perguntas de qualificação', $case->fresh()->next_action);
    }

    public function test_creating_opportunity_promotes_next_action_to_activity(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/opportunities', [
            'title' => 'Novo caso',
            'client_name' => 'Cliente novo',
            'next_action' => 'Confirmar reunião',
            'next_action_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'priority' => 'high',
            'origin' => 'inbound',
        ])->assertRedirect();

        $this->assertDatabaseHas('activities', ['title' => 'Confirmar reunião', 'type' => 'follow_up', 'is_next_action' => true, 'priority' => 'high']);
    }

    public function test_archiving_an_active_client_is_blocked_but_opportunities_are_never_deleted(): void
    {
        $user = User::factory()->create();
        $client = Client::create(['name' => 'Cliente ativo']);
        $case = Opportunity::create(['title' => 'Caso ativo', 'client_id' => $client->id, 'client_name' => $client->name, 'stage' => 'lead', 'owner_id' => $user->id]);

        $this->actingAs($user)->post("/clients/{$client->id}/archive", ['reason' => 'Teste'])->assertSessionHasErrors('archive');
        $this->actingAs($user)->post("/opportunities/{$case->id}/archive", ['reason' => 'Encerrado para reorganização'])->assertRedirect();

        $this->assertNotNull($case->fresh()->archived_at);
        $this->assertDatabaseHas('opportunities', ['id' => $case->id]);
    }

    public function test_viability_contract_decision_syncs_the_commercial_stage(): void
    {
        $user = User::factory()->create();
        $case = Opportunity::create(['title' => 'Viabilidade', 'client_name' => 'Cliente', 'stage' => 'briefing', 'commercial_stage' => CommercialStage::VIABILITY_OFFER]);
        CaseJourney::create(['opportunity_id' => $case->id, 'revision' => 0, 'mode' => 'demo', 'modality' => 'express', 'cycle' => 'commercial', 'viability_status' => 'not_contracted', 'management_status' => 'not_contracted']);

        app(CaseJourneyService::class)->apply($case, $user, ['action' => 'contract_viability', 'revision' => 0, 'evidence' => 'Proposta aprovada pela cliente.']);

        $this->assertSame(CommercialStage::VIABILITY_CONTRACTED, $case->fresh()->commercial_stage);
    }
}
