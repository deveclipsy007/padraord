<?php

namespace Tests\Feature;

use App\Models\Opportunity;
use App\Models\Supplier;
use App\Models\SupplierQuote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SupplierNeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_can_track_a_supplier_need_and_select_a_quote_without_contracting_it(): void
    {
        $actor = User::factory()->create();
        $opportunity = Opportunity::create(['title' => 'Festival', 'client_name' => 'Vértice', 'stage' => 'budget']);
        $supplier = Supplier::create(['name' => 'Som Vivo']);
        $quote = SupplierQuote::create([
            'supplier_id' => $supplier->id,
            'opportunity_id' => $opportunity->id,
            'service' => 'Sistema de som',
            'unit_cost_cents' => 120000,
            'price_basis' => 'total',
            'quantity' => 1,
            'unit' => 'pacote',
            'valid_until' => today()->addWeek(),
            'evidence' => 'Cotação por e-mail',
        ]);

        $this->actingAs($actor)
            ->post("/opportunities/{$opportunity->id}/supplier-needs", [
                'category' => 'Áudio',
                'scope' => 'Sistema de som para auditório',
                'quantity' => '1',
                'unit' => 'pacote',
            ])
            ->assertRedirect();

        $needId = (int) DB::table('supplier_needs')->value('id');

        $this->post("/opportunities/{$opportunity->id}/supplier-needs/{$needId}/quotes/{$quote->id}/select", [
            'note' => 'Escopo e prazo confirmados.',
        ])->assertRedirect();

        $this->assertDatabaseHas('supplier_quote_selections', [
            'supplier_need_id' => $needId,
            'supplier_quote_id' => $quote->id,
        ]);
        $this->assertDatabaseMissing('supplier_quote_selections', ['contracted_at' => now()]);
    }
}
