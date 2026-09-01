<?php

namespace Tests\Feature;

use App\Models\Opportunity;
use App\Models\Supplier;
use App\Models\SupplierQuote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_hint_does_not_merge_records(): void
    {
        Supplier::create(['name' => 'Luz', 'email' => 'luz@example.test']);
        $this->actingAs(User::factory()->create())->getJson('/suppliers/duplicates?email=luz@example.test')->assertOk()->assertJsonCount(1);
        $this->assertDatabaseCount('suppliers', 1);
    }

    public function test_profile_and_pagination_are_available(): void
    {
        $s = Supplier::create(['name' => 'Luz Produções', 'service' => 'Iluminação']);
        $this->actingAs(User::factory()->create())->get('/suppliers?q=Luz')->assertInertia(fn ($p) => $p->has('suppliers.data', 1)->where('filters.q', 'Luz'));
        $this->get('/suppliers/'.$s->id)->assertInertia(fn ($p) => $p->component('SupplierProfile')->where('supplier.name', 'Luz Produções'));
    }

    public function test_pending_inquiry_has_no_invented_price(): void
    {
        $s = Supplier::create(['name' => 'Luz']);
        $o = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente', 'stage' => 'briefing']);
        $this->actingAs(User::factory()->create())->post('/suppliers/'.$s->id.'/inquiries', ['opportunity_id' => $o->id, 'service' => 'Palco'])->assertRedirect();
        $this->assertDatabaseHas('supplier_inquiries', ['supplier_id' => $s->id, 'status' => 'requested']);
        $this->assertDatabaseCount('supplier_quotes', 0);
    }

    public function test_total_quote_preserves_price_basis_and_revision(): void
    {
        $s = Supplier::create(['name' => 'Luz']);
        $o = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente', 'stage' => 'briefing']);
        $this->actingAs(User::factory()->create())->post('/suppliers/'.$s->id.'/quotes', ['opportunity_id' => $o->id, 'service' => 'Palco', 'unit_cost' => '1000,00', 'valid_until' => '2027-01-01', 'evidence' => 'Mensagem recebida', 'price_basis' => 'total', 'quantity' => '2', 'unit' => 'peça'])->assertRedirect();
        $q = SupplierQuote::first();
        $this->assertSame('total', $q->price_basis);
        $this->assertSame('2.00', $q->quantity);
        $this->post('/suppliers/'.$s->id.'/quotes', ['opportunity_id' => $o->id, 'service' => 'Palco revisado', 'unit_cost' => '1200,00', 'valid_until' => '2027-01-01', 'evidence' => 'Nova mensagem', 'price_basis' => 'total', 'quantity' => '2', 'unit' => 'peça', 'supersedes_id' => $q->id])->assertRedirect();
        $this->assertDatabaseCount('supplier_quotes', 2);
        $this->assertSame(100000, $q->fresh()->unit_cost_cents);
    }
}
