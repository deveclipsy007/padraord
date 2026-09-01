<?php

namespace Tests\Feature;

use App\Models\Opportunity;
use App\Models\Supplier;
use App\Models\SupplierQuote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetIntakeTest extends TestCase
{
    use RefreshDatabase;

    public function test_total_package_is_imported_once_without_multiplication(): void
    {
        $o = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente', 'stage' => 'briefing']);
        $s = Supplier::create(['name' => 'Luz']);
        $q = SupplierQuote::create(['supplier_id' => $s->id, 'opportunity_id' => $o->id, 'service' => '2 refletores', 'unit_cost_cents' => 100000, 'price_basis' => 'total', 'quantity' => 2, 'unit' => 'peça', 'valid_until' => today()->addMonth(), 'evidence' => 'Mensagem']);
        $this->actingAs(User::factory()->create());
        $data = ['revision' => 0, 'quote_id' => $q->id, 'request_key' => 'test-import-123'];
        $this->post("/opportunities/$o->id/budget/intake", $data)->assertRedirect();
        $this->post("/opportunities/$o->id/budget/intake", $data)->assertRedirect();
        $item = $o->budgets()->first()->items()->first();
        $this->assertSame('1.00', $item->quantity);
        $this->assertSame(100000, $item->sell_total_cents);
        $this->assertDatabaseCount('budget_items', 1);
    }
}
