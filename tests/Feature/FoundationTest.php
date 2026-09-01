<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_clients_contacts_and_opportunities_are_editable_and_normalized(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post('/clients', ['name' => 'Horizonte', 'industry' => 'Eventos'])->assertRedirect();
        $client = Client::firstOrFail();
        $this->patch("/clients/{$client->id}", ['name' => 'Horizonte SA'])->assertRedirect();
        $this->post("/clients/{$client->id}/contacts", ['name' => 'Ana', 'email' => 'ana@example.com'])->assertRedirect();
        $contact = Contact::firstOrFail();
        $this->patch("/clients/{$client->id}/contacts/{$contact->id}", ['name' => 'Ana Silva'])->assertRedirect();
        $this->post('/opportunities', ['title' => 'Conferência', 'client_id' => $client->id, 'contact_id' => $contact->id])->assertRedirect();
        $opportunity = Opportunity::firstOrFail();
        $this->assertEquals('Horizonte SA', $opportunity->client_name);
        $this->assertEquals('Ana Silva', $opportunity->contact_name);
        $this->patch("/opportunities/{$opportunity->id}", ['title' => 'Conferência 2026', 'client_id' => $client->id, 'contact_id' => $contact->id])->assertRedirect();
        $this->get("/clients/{$client->id}")->assertOk();
        $this->get("/opportunities/{$opportunity->id}/edit")->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'client.updated']);
    }

    public function test_contact_cannot_be_linked_to_another_client(): void
    {
        $this->actingAs(User::factory()->create());
        $client = Client::create(['name' => 'A']);
        $other = Client::create(['name' => 'B']);
        $contact = Contact::create(['client_id' => $other->id, 'name' => 'B']);
        $this->post('/opportunities', ['title' => 'Evento', 'client_id' => $client->id, 'contact_id' => $contact->id])->assertSessionHasErrors('contact_id');
        $this->patch("/clients/{$client->id}/contacts/{$contact->id}", ['name' => 'Inválido'])->assertNotFound();
    }

    public function test_team_management_is_admin_only_and_does_not_grant_commercial_authority(): void
    {
        $producer = User::factory()->create();
        $this->actingAs($producer)->post('/team', ['name' => 'Novo'])->assertForbidden();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post('/team', ['name' => 'Novo', 'email' => 'novo@example.com', 'password' => 'TemporaryPass123!', 'role' => 'admin'])->assertRedirect();
        $new = User::where('email', 'novo@example.com')->firstOrFail();
        $this->assertFalse($new->can_approve_commercial);
        $this->post("/team/{$new->id}/password", ['password' => 'ChangedPassword123!'])->assertRedirect();
        $this->assertTrue(Hash::check('ChangedPassword123!', $new->fresh()->password));
        $this->patch("/team/{$new->id}", ['name' => 'Novo', 'email' => $new->email, 'role' => 'producer', 'is_active' => false])->assertRedirect();
        $this->actingAs($new->fresh())->get('/pipeline')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_independent_tasks_can_be_assigned_updated_and_cancelled(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/activities', ['title' => 'Preparar referências', 'user_id' => $user->id, 'priority' => 'high', 'type' => 'task', 'status' => 'todo', 'due_at' => '2026-09-01T10:00'])->assertRedirect();
        $task = Activity::firstOrFail();
        $this->assertNull($task->opportunity_id);
        $this->patch("/activities/{$task->id}", ['title' => $task->title, 'user_id' => $user->id, 'priority' => 'high', 'type' => 'task', 'status' => 'done'])->assertRedirect();
        $this->assertNotNull($task->fresh()->completed_at);
        $this->get('/agenda')->assertOk();
        $this->delete("/activities/{$task->id}")->assertRedirect();
        $this->assertDatabaseHas('activities', ['id' => $task->id, 'status' => 'cancelled']);
    }

    public function test_self_deactivation_and_unvalidated_commercial_permission_are_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->patch("/team/{$admin->id}", ['name' => $admin->name, 'email' => $admin->email, 'role' => 'producer', 'is_active' => false])->assertSessionHasErrors('is_active');
        $this->post('/team', ['name' => 'Outro', 'email' => 'other@example.com', 'password' => 'TemporaryPassword12!', 'role' => 'admin', 'can_approve_commercial' => true])->assertRedirect();
        $this->assertFalse(User::where('email', 'other@example.com')->firstOrFail()->can_approve_commercial);
    }
}
