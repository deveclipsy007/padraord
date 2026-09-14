<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientContactRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_roles_and_preferred_channel_survive_reload(): void
    {
        $client = Client::create(['name' => 'Cliente']);
        $this->actingAs(User::factory()->create())->post("/clients/{$client->id}/contacts", [
            'name' => 'Ana', 'email' => 'ana@example.test', 'is_primary' => true, 'is_decision_maker' => true,
            'department' => 'Diretoria', 'whatsapp' => '+55 47 99999-1234', 'preferred_channel' => 'whatsapp',
        ])->assertRedirect()->assertSessionDoesntHaveErrors();
        $contact = $client->contacts()->firstOrFail();
        $this->assertTrue($contact->is_primary);
        $this->assertTrue($contact->is_decision_maker);
        $this->assertSame('Diretoria', $contact->department);
        $this->assertSame('whatsapp', $contact->preferred_channel);
        $this->get("/clients/{$client->id}")->assertInertia(fn ($page) => $page->where('client.contacts.0.is_decision_maker', true));
    }

    public function test_selecting_a_new_primary_atomically_replaces_the_previous_one(): void
    {
        $client = Client::create(['name' => 'Cliente']);
        $this->actingAs(User::factory()->create());
        foreach (['Ana', 'Bruno'] as $name) {
            $this->post("/clients/{$client->id}/contacts", ['name' => $name, 'is_primary' => true])->assertSessionDoesntHaveErrors();
        }
        $this->assertSame(1, $client->contacts()->where('is_primary', true)->count());
        $this->assertSame('Bruno', $client->contacts()->where('is_primary', true)->firstOrFail()->name);
    }

    public function test_contact_revision_prevents_overwriting_a_concurrent_edit(): void
    {
        $client = Client::create(['name' => 'Cliente']);
        $contact = $client->contacts()->create(['name' => 'Ana']);
        $this->actingAs(User::factory()->create());
        $this->patch("/clients/{$client->id}/contacts/{$contact->id}", ['name' => 'Ana atualizada', 'revision' => 0])->assertSessionDoesntHaveErrors();
        $this->patch("/clients/{$client->id}/contacts/{$contact->id}", ['name' => 'Edição atrasada', 'revision' => 0])->assertSessionHasErrors('revision');
        $this->assertSame('Ana atualizada', $contact->fresh()->name);
    }

    public function test_qualification_refuses_non_decision_maker_and_accepts_a_confirmed_decision_maker(): void
    {
        $client = Client::create(['name' => 'Cliente']);
        $contact = $client->contacts()->create(['name' => 'Ana']);
        $case = Opportunity::create(['title' => 'Evento', 'client_id' => $client->id, 'client_name' => $client->name]);
        $this->actingAs(User::factory()->create());
        $payload = ['decision_maker_contact_id' => $contact->id, 'decision_maker_status' => 'identified', 'event_date_status' => 'unknown', 'budget_status' => 'unknown', 'fit_status' => 'high', 'status' => 'qualified', 'need_summary' => 'Evento anual'];
        $this->post("/opportunities/{$case->id}/qualification", $payload)->assertSessionHasErrors('decision_maker_contact_id');
        $this->patch("/clients/{$client->id}/contacts/{$contact->id}", ['name' => 'Ana', 'is_decision_maker' => true, 'revision' => 0])->assertSessionDoesntHaveErrors();
        $this->post("/opportunities/{$case->id}/qualification", $payload)->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('opportunity_qualifications', ['opportunity_id' => $case->id, 'decision_maker_contact_id' => $contact->id]);
    }

    public function test_billing_details_segmentation_and_terms_are_persisted(): void
    {
        $this->actingAs(User::factory()->create())->post('/clients', [
            'name' => 'Cliente', 'legal_name' => 'Cliente Ltda', 'billing_email' => 'financeiro@example.test', 'default_payment_terms_days' => 45,
            'municipal_registration' => '1234', 'segment' => 'cultural', 'tier' => 'recorrente',
            'billing_address' => ['logradouro' => 'Rua das Flores', 'numero' => '45', 'cidade' => 'Bombinhas', 'uf' => 'sc', 'complemento' => 'Sala 3', 'bairro' => 'Centro', 'cep' => '88215000'],
        ])->assertSessionDoesntHaveErrors();
        $client = Client::firstOrFail();
        $this->assertSame(45, $client->default_payment_terms_days);
        $this->assertSame('SC', $client->billing_address['uf']);
        $this->assertSame('cultural', $client->segment);
        $this->assertSame('1234', $client->municipal_registration);
    }

    public function test_invalid_channel_and_contact_from_another_client_are_refused(): void
    {
        $client = Client::create(['name' => 'A']);
        $other = Client::create(['name' => 'B']);
        $contact = $other->contacts()->create(['name' => 'Outro']);
        $this->actingAs(User::factory()->create());
        $this->post("/clients/{$client->id}/contacts", ['name' => 'Ana', 'preferred_channel' => 'telepatia'])->assertSessionHasErrors('preferred_channel');
        $this->patch("/clients/{$client->id}/contacts/{$contact->id}", ['name' => 'Alterado', 'is_primary' => true])->assertNotFound();
    }

    public function test_archiving_a_decision_maker_reopens_qualification_and_restore_does_not_replace_primary(): void
    {
        $client = Client::create(['name' => 'Cliente']);
        $contact = $client->contacts()->create(['name' => 'Ana', 'is_primary' => true, 'is_decision_maker' => true]);
        $case = Opportunity::create(['title' => 'Evento', 'client_id' => $client->id, 'client_name' => 'Cliente']);
        $qualification = $case->qualification()->create(['decision_maker_contact_id' => $contact->id, 'decision_maker_status' => 'identified', 'status' => 'qualified']);
        $this->actingAs(User::factory()->create());
        $this->post("/clients/{$client->id}/contacts/{$contact->id}/archive", ['reason' => 'Saiu da empresa'])->assertSessionDoesntHaveErrors();
        $this->assertSame('in_progress', $qualification->fresh()->status);
        $this->post("/clients/{$client->id}/contacts", ['name' => 'Bruno', 'is_primary' => true])->assertSessionDoesntHaveErrors();
        $this->post("/clients/{$client->id}/contacts/{$contact->id}/restore")->assertSessionDoesntHaveErrors();
        $this->assertFalse($contact->fresh()->is_primary);
        $this->assertSame(1, $client->contacts()->where('is_primary', true)->count());
    }

    public function test_duplicate_tax_id_is_refused_with_existing_client_identity(): void
    {
        $client = Client::create(['name' => 'Matriz', 'tax_id' => '11222333000181']);
        $this->actingAs(User::factory()->create());
        $this->post('/clients', ['name' => 'Repetido', 'tax_id' => '11.222.333/0001-81'])->assertSessionHasErrors('tax_id');
        $this->assertSame(1, Client::count());
        $this->patch("/clients/{$client->id}",['name' => 'Matriz atualizada', 'tax_id' => '11.222.333/0001-81'])->assertSessionDoesntHaveErrors();
    }
}
