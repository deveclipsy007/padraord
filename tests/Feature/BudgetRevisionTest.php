<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Opportunity;
use App\Models\Supplier;
use App\Models\SupplierQuote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetRevisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_reading_budget_does_not_create_a_draft(): void
    {
        $case = Opportunity::create(['title' => 'Caso', 'client_name' => 'Cliente', 'stage' => 'budget']);
        $this->actingAs(User::factory()->create())->get("/opportunities/{$case->id}/budget")->assertOk();
        $this->assertDatabaseCount('budgets', 0);
    }

    public function test_pending_commercial_rules_block_final_approval_even_for_admin(): void
    {
        $case = Opportunity::create(['title' => 'Caso', 'client_name' => 'Cliente', 'stage' => 'budget']);
        $budget = $case->budgets()->create(['version' => 1, 'status' => 'draft']);
        $budget->items()->create(['category' => 'Equipe', 'description' => 'Equipe', 'quantity' => 1, 'unit' => 'dia', 'unit_cost_cents' => 10000]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post("/opportunities/{$case->id}/budget/approve", ['revision' => 0])->assertSessionHasErrors('approval');
        $this->assertSame('draft', $budget->fresh()->status);
    }

    public function test_stale_draft_cannot_overwrite_newer_items(): void
    {
        $case = Opportunity::create(['title' => 'Caso', 'client_name' => 'Cliente', 'stage' => 'budget']);
        $user = User::factory()->create();
        $item = ['category' => 'Equipe', 'description' => 'Equipe', 'quantity' => '1.25', 'unit' => 'dia', 'unit_cost' => '100,05', 'revision' => 0];
        $this->actingAs($user)->post("/opportunities/{$case->id}/budget/items", $item)->assertSessionHasNoErrors();
        $this->post("/opportunities/{$case->id}/budget/items", $item)->assertSessionHasErrors('revision');
        $this->assertDatabaseCount('budget_items', 1);
        $this->assertSame(12506, Budget::first()->items->first()->sell_total_cents);
    }

    public function test_legacy_rounding_is_preserved_without_recalculating_existing_prices(): void
    {
        $case = Opportunity::create(['title' => 'Legado', 'client_name' => 'Cliente', 'stage' => 'budget']);
        $budget = $case->budgets()->create(['version' => 1, 'status' => 'draft']);
        $item = $budget->items()->create(['category' => 'Equipe', 'description' => 'Equipe', 'quantity' => '1.25', 'unit' => 'dia', 'unit_cost_cents' => 101, 'margin_percent' => 20]);
        $this->assertSame(152, $item->fresh()->sell_total_cents);
    }

    public function test_approved_budget_is_immutable_and_copy_preserves_original_items(): void
    {
        $case = Opportunity::create(['title' => 'Caso', 'client_name' => 'Cliente', 'stage' => 'budget']);
        $supplier = Supplier::create(['name' => 'Equipe']);
        $quote = SupplierQuote::create(['supplier_id' => $supplier->id, 'opportunity_id' => $case->id, 'service' => 'Equipe', 'unit_cost_cents' => 10000, 'valid_until' => today()->addMonth(), 'evidence' => 'Documento autorizado']);
        $budget = $case->budgets()->create(['version' => 1, 'status' => 'draft']);
        $item = $budget->items()->create(['category' => 'Equipe', 'description' => 'Equipe', 'quantity' => 1, 'unit' => 'dia', 'unit_cost_cents' => 10000, 'supplier_quote_id' => $quote->id]);
        config(['commercial.rules_approved' => true, 'commercial.rules_evidence' => 'Decisão TESTE']);
        $this->actingAs(User::factory()->create(['can_approve_commercial' => true]))->post("/opportunities/{$case->id}/budget/approve", ['revision' => 0])->assertSessionHasNoErrors();
        $snapshot = $budget->fresh()->snapshot;
        $this->delete("/opportunities/{$case->id}/budget/items/{$item->id}", ['revision' => 1])->assertSessionHasErrors('budget');
        $this->post("/opportunities/{$case->id}/budget/versions", ['revision' => 1])->assertSessionHasNoErrors();
        $this->assertSame($snapshot, $budget->fresh()->snapshot);
        $this->assertDatabaseCount('budgets', 2);
        $this->assertSame(1, $budget->items()->count());
        $this->assertSame(1, $case->budgets()->where('version', 2)->first()->items()->count());
    }
}
