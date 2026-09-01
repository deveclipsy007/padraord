<?php

namespace Tests\Feature;

use App\Models\Opportunity;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_can_register_supplier_and_evidenced_quote(): void
    {
        $this->actingAs(User::factory()->create())->post('/suppliers', ['name' => 'Palco local', 'service' => 'Estruturas'])->assertRedirect();
        $supplier = Supplier::firstOrFail();
        $case = Opportunity::create(['title' => 'Caso', 'client_name' => 'Cliente', 'stage' => 'budget']);
        $this->post('/suppliers/'.$supplier->id.'/quotes', ['opportunity_id' => $case->id, 'service' => 'Palco', 'unit_cost' => '1234,56', 'valid_until' => '2027-01-01', 'conditions' => 'Montagem inclusa', 'evidence' => 'Cotação recebida por e-mail, referência 123'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('supplier_quotes', ['unit_cost_cents' => 123456, 'opportunity_id' => $case->id]);
        $this->get('/suppliers')->assertOk();
    }
}
