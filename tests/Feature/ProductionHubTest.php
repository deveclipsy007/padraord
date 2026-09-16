<?php

namespace Tests\Feature;

use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductionHubTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_has_a_separate_workspace_and_preserves_project_context(): void
    {
        $user = User::factory()->create();
        $event = Opportunity::create(['title' => 'Evento real', 'client_name' => 'Cliente', 'stage' => 'pre_production']);
        Opportunity::create(['title' => 'Arquivado', 'client_name' => 'Cliente', 'archived_at' => now()]);
        $this->get('/production')->assertRedirect('/login');
        $this->actingAs($user)->get('/production')->assertOk()->assertInertia(fn (Assert $page) => $page->component('ProductionHub')->has('events', 1)->where('events.0.title', 'Evento real')->where('events.0.tasks', 0));
        $this->get("/production/events/{$event->id}")->assertOk()->assertInertia(fn (Assert $page) => $page->component('Production')->where('opportunity.id', $event->id));
        $this->get("/production/events/{$event->id}/post-event")->assertOk();
    }

    public function test_job_title_is_saved_without_granting_administrative_or_commercial_authority(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post('/team', ['name' => 'Diretora', 'email' => 'direcao@example.com', 'password' => 'TemporaryPass123!', 'role' => 'producer', 'job_title' => 'Direção'])->assertSessionHasNoErrors();
        $user = User::where('email', 'direcao@example.com')->firstOrFail();
        $this->assertSame('Direção', $user->job_title);
        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->can_approve_commercial);
        $this->actingAs($user)->patch("/team/{$admin->id}", ['job_title' => 'Direção'])->assertForbidden();
        $this->actingAs($admin)->patch("/team/{$user->id}", ['name' => $user->name, 'email' => $user->email, 'role' => 'producer', 'is_active' => true, 'job_title' => 'Financeiro'])->assertSessionHasNoErrors();
        $this->assertSame('Financeiro', $user->fresh()->job_title);
    }
}
