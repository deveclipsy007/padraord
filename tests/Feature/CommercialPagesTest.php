<?php

namespace Tests\Feature;

use App\Enums\CommercialStage;
use App\Models\Activity;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CommercialPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_pipeline_uses_commercial_stage_and_shared_filters(): void
    {
        $user = User::factory()->create();
        Opportunity::create(['title' => 'Lead visível', 'client_name' => 'Cliente A', 'stage' => 'lead', 'owner_id' => $user->id, 'commercial_stage' => CommercialStage::LEAD, 'priority' => 'high', 'origin' => 'referral']);
        Opportunity::create(['title' => 'Caso de produção', 'client_name' => 'Cliente B', 'stage' => 'production', 'owner_id' => $user->id, 'commercial_stage' => CommercialStage::VIABILITY_CONTRACTED]);
        Opportunity::create(['title' => 'Outro responsável', 'client_name' => 'Cliente C', 'stage' => 'lead', 'owner_id' => User::factory()->create()->id, 'commercial_stage' => CommercialStage::LEAD]);

        $this->actingAs($user)->get('/pipeline?owner='.$user->id.'&priority=high&view=list')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Pipeline')
                ->where('filters.view', 'list')
                ->where('filters.owner', (string) $user->id)
                ->has('stages', 6)
                ->has('opportunities', 1)
                ->where('opportunities.0.title', 'Lead visível'));
    }

    public function test_today_defaults_to_my_queue_and_can_show_the_team(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $mine = Activity::create(['title' => 'Minha tarefa', 'user_id' => $user->id, 'type' => 'task', 'priority' => 'normal', 'status' => 'todo', 'due_at' => now()->addDay()]);
        $theirs = Activity::create(['title' => 'Tarefa da equipe', 'user_id' => $other->id, 'type' => 'task', 'priority' => 'high', 'status' => 'todo', 'due_at' => now()->addDay()]);

        $this->actingAs($user)->get('/?scope=mine')->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('queueFilters.scope', 'mine')
            ->where('todayQueue.0.id', $mine->id)
            ->missing('todayQueue.1'));
        $this->actingAs($user)->get('/?scope=team')->assertInertia(fn (Assert $page) => $page
            ->where('queueFilters.scope', 'team')
            ->has('todayQueue', 2)
            ->where('todayQueue.1.id', $theirs->id));
    }

    public function test_clients_are_paginated_and_archived_records_are_excluded_by_default(): void
    {
        $user = User::factory()->create();
        $active = Client::create(['name' => 'Cliente ativo']);
        $archived = Client::create(['name' => 'Cliente arquivado', 'archived_at' => now(), 'archived_by' => $user->id, 'archive_reason' => 'Teste']);

        $this->actingAs($user)->get('/clients?status=active&per_page=25')->assertInertia(fn (Assert $page) => $page
            ->component('Clients')
            ->where('filters.status', 'active')
            ->where('clients.data.0.id', $active->id)
            ->missing('clients.data.1'));
        $this->actingAs($user)->get('/clients?status=archived')->assertInertia(fn (Assert $page) => $page
            ->where('clients.data.0.id', $archived->id));
    }

    public function test_global_search_groups_clients_contacts_opportunities_and_activities(): void
    {
        $user = User::factory()->create();
        $client = Client::create(['name' => 'Horizonte Energia']);
        $contact = Contact::create(['client_id' => $client->id, 'name' => 'Marina Horizonte']);
        $case = Opportunity::create(['title' => 'Conferência Horizonte', 'client_id' => $client->id, 'client_name' => $client->name, 'stage' => 'lead']);
        Activity::create(['title' => 'Ligar para Horizonte', 'opportunity_id' => $case->id, 'user_id' => $user->id, 'type' => 'follow_up', 'priority' => 'normal', 'status' => 'todo']);

        $this->actingAs($user)->getJson('/search?q=Horizonte')->assertOk()
            ->assertJsonPath('groups.0.type', 'opportunities')
            ->assertJsonFragment(['id' => $client->id, 'title' => $client->name])
            ->assertJsonFragment(['id' => $contact->id, 'title' => $contact->name]);
    }

    public function test_activity_quick_actions_update_status_and_preserve_audit(): void
    {
        $user = User::factory()->create();
        $activity = Activity::create(['title' => 'Retorno', 'user_id' => $user->id, 'type' => 'follow_up', 'priority' => 'normal', 'status' => 'todo']);

        $this->actingAs($user)->post("/activities/{$activity->id}/complete")->assertRedirect();
        $this->assertDatabaseHas('activities', ['id' => $activity->id, 'status' => 'done']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'activity.completed', 'subject_id' => $activity->id]);
    }

    public function test_agenda_filters_activities_on_the_server(): void
    {
        $user = User::factory()->create();
        Activity::create(['title' => 'Alta prioridade', 'user_id' => $user->id, 'type' => 'task', 'priority' => 'high', 'status' => 'todo']);
        Activity::create(['title' => 'Outra pessoa', 'user_id' => User::factory()->create()->id, 'type' => 'task', 'priority' => 'high', 'status' => 'todo']);

        $this->actingAs($user)->get('/agenda?owner='.$user->id.'&priority=high')->assertInertia(fn (Assert $page) => $page
            ->component('Agenda')
            ->where('filters.owner', (string) $user->id)
            ->has('activities', 1)
            ->where('activities.0.title', 'Alta prioridade'));
    }
}
