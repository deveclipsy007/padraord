<?php

namespace Tests\Feature;

use App\Models\Opportunity;
use App\Models\User;
use App\Services\EventFinance;
use App\Services\EventProfitability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EventFinanceTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        $user = User::factory()->create(['can_approve_commercial' => true]);
        $this->actingAs($user);

        return [Opportunity::create(['title' => 'Congresso', 'client_name' => 'Cliente']), $user, app(EventFinance::class)];
    }

    private function plan($case, $user, $service): int
    {
        return $service->savePlan($case, $user, ['revision' => 0, 'total_cents' => 10001, 'installments' => [
            ['share_bps' => 3333, 'trigger' => 'assinatura', 'label' => 'Entrada'],
            ['share_bps' => 3333, 'trigger' => 'data_fixa', 'due_at' => '2026-01-01', 'label' => 'Segunda'],
            ['share_bps' => 3334, 'trigger' => 'marco', 'milestone' => 'Entrega técnica', 'label' => 'Saldo'],
        ]]);
    }

    public function test_plan_closes_to_the_cent_and_accepted_terms_are_immutable(): void
    {
        [$case,$user,$s] = $this->scenario();
        $id = $this->plan($case, $user, $s);
        $this->assertSame(10001, (int) DB::table('payment_plan_installments')->where('payment_plan_id', $id)->sum('amount_cents'));
        $s->acceptPlan($case, $user, $id, ['revision' => 1, 'evidence' => 'Cliente confirmou a condição na reunião']);
        $this->expectException(ValidationException::class);
        $s->savePlan($case, $user, ['revision' => 1, 'total_cents' => 12000, 'installments' => [['share_bps' => 10000, 'trigger' => 'assinatura', 'label' => 'Única']]]);
    }

    public function test_receivables_require_confirmed_preview_and_support_partial_settlement(): void
    {
        [$case,$user,$s] = $this->scenario();
        $id = $this->plan($case, $user, $s);
        $s->acceptPlan($case, $user, $id, ['revision' => 1, 'evidence' => 'Aceite registrado no atendimento']);
        $preview = $s->previewReceivables($case, $user, $id);
        $this->assertDatabaseCount('receivables', 0);
        $s->confirmReceivables($case, $user, $preview);
        $s->confirmReceivables($case, $user, $preview);
        $this->assertDatabaseCount('receivables', 3);
        $r = DB::table('receivables')->where('opportunity_id', $case->id)->orderBy('id')->first();
        $s->settle($case, $user, 'receivables', $r->id, ['amount_cents' => 1000, 'revision' => 0, 'request_key' => 'receipt-001', 'paid_at' => '2026-09-14', 'evidence' => 'Comprovante conferido']);
        $s->settle($case, $user, 'receivables', $r->id, ['amount_cents' => 1000, 'revision' => 0, 'request_key' => 'receipt-001', 'paid_at' => '2026-09-14', 'evidence' => 'Comprovante conferido']);
        $this->assertDatabaseCount('financial_settlements', 1);
        $this->assertSame($r->amount_cents - 1000, $s->ledger($case, 'receivables')[0]['balance_cents']);
        $this->assertTrue($s->ledger($case, 'receivables')[1]['overdue']);
        $this->expectException(ValidationException::class);
        $s->settle($case, $user, 'receivables', $r->id, ['amount_cents' => 99999, 'revision' => 1, 'request_key' => 'receipt-002', 'paid_at' => '2026-09-14', 'evidence' => 'Valor acima do saldo']);
    }

    public function test_cancel_with_settlement_requires_reason_and_preserves_paid_history(): void
    {
        [$case,$user,$s] = $this->scenario();
        $id = $this->plan($case, $user, $s);
        $s->acceptPlan($case, $user, $id, ['revision' => 1, 'evidence' => 'Cliente confirmou']);
        $s->confirmReceivables($case, $user, $s->previewReceivables($case, $user, $id));
        $r = DB::table('receivables')->first();
        $s->settle($case, $user, 'receivables', $r->id, ['amount_cents' => 100, 'revision' => 0, 'request_key' => 'settlement-cancel', 'paid_at' => '2026-09-14', 'evidence' => 'Conferido']);
        $this->post("/opportunities/{$case->id}/finance/receivables/{$r->id}/cancel", ['revision' => 1])->assertSessionHasErrors('reason');
        $this->post("/opportunities/{$case->id}/finance/receivables/{$r->id}/cancel", ['revision' => 1, 'reason' => 'Cliente cancelou; baixa preservada para conciliação'])->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('receivables', ['id' => $r->id, 'status' => 'cancelled']);
        $this->assertDatabaseCount('financial_settlements', 1);
    }

    public function test_payable_requires_same_case_approved_origin_and_authority_before_payment(): void
    {
        [$case,$user,$s] = $this->scenario();
        $b = $case->budgets()->create(['version' => 1, 'status' => 'approved']);
        $item = $b->items()->create(['category' => 'som', 'description' => 'Som', 'quantity' => 2, 'unit_cost_cents' => 10000]);
        $this->post("/opportunities/{$case->id}/finance/payables", ['label' => 'Sem origem', 'amount_cents' => 1000])->assertSessionHasErrors('origin');
        $payable = $s->createPayable($case, $user, ['origin_type' => 'budget_item', 'origin_id' => $item->id, 'label' => 'Som', 'amount_cents' => 20000, 'due_at' => '2026-10-10']);
        $payment = ['amount_cents' => 1000, 'revision' => 0, 'request_key' => 'payable-01', 'paid_at' => '2026-09-14', 'evidence' => 'Comprovante verificado'];
        $this->post("/opportunities/{$case->id}/finance/payables/$payable/settle", $payment)->assertSessionHasErrors('approval');
        $this->actingAs(User::factory()->create(['can_approve_commercial' => false]))->post("/opportunities/{$case->id}/finance/payables/$payable/approve", ['revision' => 0])->assertForbidden();
        $this->actingAs($user)->post("/opportunities/{$case->id}/finance/payables/$payable/approve", ['revision' => 0])->assertSessionDoesntHaveErrors();
        $s->settle($case, $user, 'payables', $payable, array_replace($payment, ['revision' => 1]));
        $this->assertDatabaseCount('financial_settlements', 1);
        $other = Opportunity::create(['title' => 'Outro', 'client_name' => 'Outro']);
        $this->post("/opportunities/{$other->id}/finance/payables", ['origin_type' => 'budget_item', 'origin_id' => $item->id, 'label' => 'Outro', 'amount_cents' => 100])->assertNotFound();
    }

    public function test_cost_variance_blocks_closure_and_profitability_is_provisional_until_closed(): void
    {
        [$case,$user,$s] = $this->scenario();
        $b = $case->budgets()->create(['version' => 1, 'status' => 'approved']);
        $b->items()->create(['category' => 'som', 'description' => 'Som', 'quantity' => 2, 'unit_cost_cents' => 10000, 'margin_percent' => 20]);
        $id = $s->savePlan($case, $user, ['revision' => 0, 'total_cents' => 24000, 'installments' => [['label' => 'Única', 'share_bps' => 10000, 'trigger' => 'assinatura']]]);
        $s->acceptPlan($case, $user, $id, ['revision' => 1, 'evidence' => 'Cliente confirmou']);
        $s->saveCost($case, $user, ['category' => 'som', 'actual_cents' => 25000, 'revision' => 0]);
        $p = app(EventProfitability::class)->for($case);
        $this->assertSame(20000, $p['planned_cost_cents']);
        $this->assertSame(25000, $p['actual_cost_cents']);
        $this->assertSame(-1000, $p['actual_margin_cents']);
        $this->assertFalse($p['final']);
        $this->post("/opportunities/{$case->id}/post-event", ['summary' => 'Executado', 'learnings' => 'Rever som', 'status' => 'closed'])->assertSessionHasErrors('status');
        $s->saveCost($case, $user, ['category' => 'som', 'actual_cents' => 25000, 'revision' => 1, 'variance_reason' => 'Ampliação solicitada pelo cliente na montagem']);
        $this->post("/opportunities/{$case->id}/post-event", ['summary' => 'Executado', 'learnings' => 'Rever som', 'status' => 'closed'])->assertSessionDoesntHaveErrors();
        $this->assertTrue(app(EventProfitability::class)->for($case)['final']);
        $this->expectException(ValidationException::class);
        $s->saveCost($case, $user, ['category' => 'som', 'actual_cents' => 26000, 'revision' => 2]);
    }
}
