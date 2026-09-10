<?php

namespace Tests\Feature;

use App\Models\Opportunity;
use App\Models\Supplier;
use App\Models\SupplierQuote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_selected_current_quote_is_imported_once_into_the_budget_draft(): void
    {
        $actor = User::factory()->create();
        $opportunity = Opportunity::create(['title' => 'Conferência', 'client_name' => 'Horizonte', 'stage' => 'budget']);
        $supplier = Supplier::create(['name' => 'Luz Horizonte']);
        $quote = SupplierQuote::create([
            'supplier_id' => $supplier->id,
            'opportunity_id' => $opportunity->id,
            'service' => 'Iluminação cênica',
            'unit_cost_cents' => 250000,
            'price_basis' => 'total',
            'quantity' => 1,
            'unit' => 'pacote',
            'valid_until' => today()->addWeek(),
            'evidence' => 'E-mail comercial #104',
        ]);

        $payload = ['revision' => 0, 'request_key' => 'quote-selection-'.$quote->id];

        $this->actingAs($actor)
            ->post("/opportunities/{$opportunity->id}/quotes/{$quote->id}/select", $payload)
            ->assertRedirect("/opportunities/{$opportunity->id}/budget?quote_id={$quote->id}");

        $this->post("/opportunities/{$opportunity->id}/quotes/{$quote->id}/select", $payload)
            ->assertRedirect("/opportunities/{$opportunity->id}/budget?quote_id={$quote->id}");

        $this->assertDatabaseCount('budget_items', 1);
        $this->assertDatabaseHas('budget_items', [
            'supplier_quote_id' => $quote->id,
            'supplier' => 'Luz Horizonte',
            'unit_cost_cents' => 250000,
        ]);
    }

    public function test_selection_uses_the_current_budget_revision_when_the_workspace_does_not_send_one(): void
    {
        $actor = User::factory()->create();
        $opportunity = Opportunity::create(['title' => 'Conferência', 'client_name' => 'Horizonte', 'stage' => 'budget']);
        $supplier = Supplier::create(['name' => 'Palco Norte']);
        $quote = SupplierQuote::create([
            'supplier_id' => $supplier->id,
            'opportunity_id' => $opportunity->id,
            'service' => 'Palco',
            'unit_cost_cents' => 80000,
            'price_basis' => 'total',
            'quantity' => 1,
            'unit' => 'pacote',
            'valid_until' => today()->addWeek(),
            'evidence' => 'Proposta comercial #200',
        ]);

        $this->actingAs($actor)
            ->post("/opportunities/{$opportunity->id}/quotes/{$quote->id}/select", ['request_key' => 'workspace-'.$quote->id])
            ->assertRedirect();

        $this->assertDatabaseHas('budget_items', ['supplier_quote_id' => $quote->id]);
    }
}
