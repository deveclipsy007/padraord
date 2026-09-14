<?php

namespace Tests\Feature;

use App\Models\Opportunity;
use App\Models\Supplier;
use App\Models\SupplierQuote;
use App\Models\User;
use App\Services\EventFinance;
use App\Services\SupplierSourcing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayableOriginTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        $u = User::factory()->create(['can_approve_commercial' => true]);
        $this->actingAs($u);
        $c = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente']);
        $supplier = Supplier::create(['name' => 'Fornecedor', 'service' => 'som']);
        $need = app(SupplierSourcing::class)->createNeed($c, $u, ['category' => 'som', 'scope' => 'Pacote de som', 'status' => 'ready_to_quote']);
        $q = SupplierQuote::create(['supplier_id' => $supplier->id, 'opportunity_id' => $c->id, 'service' => 'Pacote de som', 'unit_cost_cents' => 10000, 'price_basis' => 'total', 'quantity' => 10, 'unit' => 'caixa', 'valid_until' => '2027-01-01', 'evidence' => 'Cotação sintética']);
        app(SupplierSourcing::class)->selectQuote($c, $need, $q, $u, 'Preço conferido');

        return [$c, $u, $q];
    }

    public function test_package_quote_is_not_multiplied_by_quantity_and_uses_need_category(): void
    {
        [$c,$u,$q] = $this->scenario();
        $s = app(EventFinance::class);
        $this->post("/opportunities/{$c->id}/finance/payables", ['origin_type' => 'quote', 'origin_id' => $q->id, 'label' => 'Pacote', 'amount_cents' => 10001])->assertSessionHasErrors('amount_cents');
        $id = $s->createPayable($c, $u, ['origin_type' => 'quote', 'origin_id' => $q->id, 'label' => 'Pacote', 'amount_cents' => 10000]);
        $this->assertDatabaseHas('payables', ['id' => $id, 'category' => 'som', 'amount_cents' => 10000]);
    }

    public function test_quote_cannot_create_duplicate_liability_through_a_budget_item(): void
    {
        [$c,$u,$q] = $this->scenario();
        $s = app(EventFinance::class);
        $s->createPayable($c, $u, ['origin_type' => 'quote', 'origin_id' => $q->id, 'label' => 'Pacote', 'amount_cents' => 10000]);
        $b = $c->budgets()->create(['version' => 1, 'status' => 'approved']);
        $item = $b->items()->create(['category' => 'som', 'description' => 'Mesmo pacote', 'quantity' => 1, 'unit_cost_cents' => 10000, 'supplier_quote_id' => $q->id]);
        $this->post("/opportunities/{$c->id}/finance/payables", ['origin_type' => 'budget_item', 'origin_id' => $item->id, 'label' => 'Pacote duplicado', 'amount_cents' => 10000])->assertSessionHasErrors('origin');
        $this->assertDatabaseCount('payables',1);
    }
}
