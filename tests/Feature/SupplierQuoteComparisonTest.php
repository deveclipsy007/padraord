<?php

namespace Tests\Feature;

use App\Models\Opportunity;
use App\Models\Supplier;
use App\Models\SupplierQuote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierQuoteComparisonTest extends TestCase
{
    use RefreshDatabase;

    public function test_comparison_normalizes_unit_and_package_prices_and_exports_a_recorded_decision(): void
    {
        $user = User::factory()->create();
        $case = Opportunity::create(['title' => 'Convenção', 'client_name' => 'Cliente', 'stage' => 'briefing']);
        $unitQuote = $this->quote($case, ['service' => 'Operação de luz', 'unit_cost_cents' => 15000, 'price_basis' => 'unit', 'quantity' => 2, 'unit' => 'diária']);
        $packageQuote = $this->quote($case, ['service' => 'Operação de luz + montagem', 'unit_cost_cents' => 36000, 'price_basis' => 'total', 'quantity' => 2, 'unit' => 'diária']);

        $this->actingAs($user)->post('/opportunities/'.$case->id.'/quote-comparisons', [
            'title' => 'Luz para a convenção',
            'quote_ids' => [$unitQuote->id, $packageQuote->id],
            'scope_difference' => 'A segunda alternativa inclui montagem; a primeira considera apenas operação.',
            'decision_quote_id' => $packageQuote->id,
            'justification' => 'A montagem incluída reduz o risco operacional e cabe no orçamento aprovado.',
        ])->assertRedirect();

        $comparison = \App\Models\SupplierQuoteComparison::with('items')->firstOrFail();
        $this->assertSame('decided', $comparison->status);
        $this->assertSame($packageQuote->id, $comparison->decision_quote_id);
        $this->assertSame(30000, $comparison->items->firstWhere('source_quote_id', $unitQuote->id)->normalized_total_cents);
        $this->assertSame(36000, $comparison->items->firstWhere('source_quote_id', $packageQuote->id)->normalized_total_cents);
        $this->assertSame('Operação de luz', $unitQuote->fresh()->service);
        $this->assertDatabaseHas('audit_logs', ['action' => 'supplier.quote_comparison_created']);
        $this->get('/suppliers?tab=quotes&opportunity_id='.$case->id)->assertInertia(fn ($page) => $page
            ->component('Suppliers')
            ->has('comparisons', 1)
            ->where('comparisons.0.status', 'decided')
            ->where('comparisons.0.decision_quote_id', $packageQuote->id));

        $export = $this->get('/opportunities/'.$case->id.'/quote-comparisons/'.$comparison->id.'/export');
        $export->assertOk()->assertHeader('content-type', 'application/json');
        $export->assertJsonPath('decision.quote_id', $packageQuote->id)
            ->assertJsonPath('items.0.normalized_total_cents', 30000)
            ->assertJsonPath('scope_difference', 'A segunda alternativa inclui montagem; a primeira considera apenas operação.');
    }

    public function test_comparison_requires_an_explicit_scope_difference_when_the_services_differ(): void
    {
        $case = Opportunity::create(['title' => 'Convenção', 'client_name' => 'Cliente', 'stage' => 'briefing']);
        $first = $this->quote($case, ['service' => 'Som']);
        $second = $this->quote($case, ['service' => 'Som e microfones']);

        $this->actingAs(User::factory()->create())->from('/suppliers')->post('/opportunities/'.$case->id.'/quote-comparisons', [
            'title' => 'Alternativas de som',
            'quote_ids' => [$first->id, $second->id],
        ])->assertRedirect('/suppliers')->assertSessionHasErrors('scope_difference');

        $this->assertDatabaseCount('supplier_quote_comparisons', 0);
    }

    public function test_expired_or_superseded_quotes_cannot_be_decided_even_when_they_remain_in_history(): void
    {
        $case = Opportunity::create(['title' => 'Convenção', 'client_name' => 'Cliente', 'stage' => 'briefing']);
        $expired = $this->quote($case, ['valid_until' => today()->subDay()]);
        $oldRevision = $this->quote($case, ['service' => 'Palco', 'unit_cost_cents' => 40000]);
        $currentRevision = $this->quote($case, ['service' => 'Palco revisado', 'unit_cost_cents' => 45000, 'supersedes_id' => $oldRevision->id]);

        $this->actingAs(User::factory()->create())->from('/suppliers')->post('/opportunities/'.$case->id.'/quote-comparisons', [
            'title' => 'Decisão inválida',
            'quote_ids' => [$expired->id, $currentRevision->id],
            'scope_difference' => 'A primeira alternativa expirou; a segunda é revisão de palco.',
            'decision_quote_id' => $expired->id,
            'justification' => 'Não deveria ser aceita.',
        ])->assertRedirect('/suppliers')->assertSessionHasErrors('decision_quote_id');

        $this->from('/suppliers')->post('/opportunities/'.$case->id.'/quote-comparisons', [
            'title' => 'Revisão inválida',
            'quote_ids' => [$oldRevision->id, $currentRevision->id],
            'scope_difference' => 'A segunda entrada substitui formalmente a primeira.',
            'decision_quote_id' => $oldRevision->id,
            'justification' => 'Não deveria ser aceita.',
        ])->assertRedirect('/suppliers')->assertSessionHasErrors('decision_quote_id');

        $this->assertDatabaseCount('supplier_quote_comparisons', 0);
    }

    private function quote(Opportunity $case, array $overrides = []): SupplierQuote
    {
        $supplier = Supplier::create(['name' => 'Fornecedor '.(Supplier::count() + 1), 'service' => 'Produção']);

        return SupplierQuote::create($overrides + [
            'supplier_id' => $supplier->id,
            'opportunity_id' => $case->id,
            'service' => 'Operação de luz',
            'unit_cost_cents' => 20000,
            'price_basis' => 'total',
            'quantity' => 1,
            'unit' => 'pacote',
            'valid_until' => today()->addMonth(),
            'evidence' => 'Proposta recebida por e-mail.',
        ]);
    }
}
