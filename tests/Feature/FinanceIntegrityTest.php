<?php

namespace Tests\Feature;

use App\Models\Opportunity;
use App\Models\User;
use App\Services\DocumentRevisions;
use App\Services\EventBriefService;
use App\Services\EventFinance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FinanceIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function setupFinance(): array
    {
        $u = User::factory()->create(['can_approve_commercial' => true]);
        $this->actingAs($u);

        return [Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente']), $u, app(EventFinance::class)];
    }

    public function test_percentages_and_stale_updates_do_not_change_plan(): void
    {
        [$c,$u,$s] = $this->setupFinance();
        $base = "/opportunities/{$c->id}/finance/plan";
        $data = ['revision' => 0, 'total_cents' => 101, 'installments' => [['label' => 'Parcela', 'share_bps' => 9999, 'trigger' => 'assinatura']]];
        $this->post($base, $data)->assertSessionHasErrors('installments');
        $this->assertDatabaseCount('payment_plans', 0);
        $data['installments'][0]['share_bps'] = 10000;
        $this->post($base, $data)->assertSessionDoesntHaveErrors();
        $this->post($base, $data)->assertSessionHasErrors('revision');
        $this->assertSame(1, DB::table('payment_plans')->first()->revision);
    }

    public function test_event_relative_dates_and_unresolved_triggers_are_explicit_and_stale_preview_is_refused(): void
    {
        [$c,$u,$s] = $this->setupFinance();
        app(EventBriefService::class)->mutate($c, $u, 0, ['starts_at' => '2026-10-10T09:00', 'ends_at' => '2026-10-11T18:00']);
        $id = $s->savePlan($c, $u, ['revision' => 0, 'total_cents' => 10000, 'installments' => [['label' => 'Antes', 'share_bps' => 2500, 'trigger' => 'dias_antes_evento', 'offset_days' => 5], ['label' => 'Depois', 'share_bps' => 2500, 'trigger' => 'dias_apos_evento', 'offset_days' => 2], ['label' => 'Entrega', 'share_bps' => 2500, 'trigger' => 'entrega', 'milestone' => 'Evento entregue'], ['label' => 'Marco', 'share_bps' => 2500, 'trigger' => 'marco', 'milestone' => 'Laudo técnico']]]);
        $s->acceptPlan($c, $u, $id, ['revision' => 1, 'evidence' => 'Condição confirmada']);
        $p = $s->previewReceivables($c, $u, $id);
        $items = json_decode(DB::table('receivable_previews')->where('id', $p)->value('items'), true);
        $this->assertSame('2026-10-05', $items[0]['due_at']);
        $this->assertSame('2026-10-13', $items[1]['due_at']);
        $this->assertNull($items[2]['due_at']);
        app(EventBriefService::class)->mutate($c, $u, 1, ['ends_at' => '2026-10-12T18:00']);
        $this->post("/opportunities/{$c->id}/finance/preview/$p/confirm")->assertSessionHasErrors('preview');
        $this->assertDatabaseCount('receivables', 0);
        $s->confirmReceivables($c, $u, $s->previewReceivables($c, $u, $id));
        $r = DB::table('receivables')->where('trigger', 'marco')->first();
        $this->assertFalse(collect($s->ledger($c, 'receivables'))->firstWhere('id', $r->id)['overdue']);
        $s->confirmDue($c, $u, $r->id, ['revision' => 0, 'due_at' => '2026-10-15', 'evidence' => 'Laudo recebido e conferido']);
        $this->assertDatabaseHas('receivables', ['id' => $r->id, 'due_at' => '2026-10-15']);
    }

    public function test_proposal_preserves_payment_terms_and_old_draft_detects_changed_plan(): void
    {
        [$c,$u,$s] = $this->setupFinance();
        $id = $s->savePlan($c, $u, ['revision' => 0, 'total_cents' => 101, 'installments' => [['label' => 'Integral', 'share_bps' => 10000, 'trigger' => 'assinatura']]]);
        $doc = app(DocumentRevisions::class)->draft($c, $u, 'proposal', ['title' => 'Proposta', 'purpose' => 'management', 'expected_version' => 0, 'sections' => ['objective' => 'Evento', 'scope' => 'Som', 'conditions' => 'A combinar']]);
        $this->assertSame(101, data_get($doc->content, 'sources.payment_plan.total_cents'));
        $html = view('documents.proposal', ['document' => $doc])->render();
        $this->assertStringContainsString('Condições de pagamento', $html);
        $this->assertStringContainsString('1,01', $html);
        $s->savePlan($c, $u, ['revision' => 1, 'total_cents' => 200, 'installments' => [['label' => 'Integral', 'share_bps' => 10000, 'trigger' => 'assinatura']]]);
        $this->assertSame(101, data_get($doc->fresh()->content, 'sources.payment_plan.total_cents'));
        $this->expectException(ValidationException::class);
        app(DocumentRevisions::class)->current($c, $doc);
    }

    public function test_cross_case_settlement_and_changed_idempotency_request_are_refused(): void
    {
        [$c,$u,$s] = $this->setupFinance();
        $id = $s->savePlan($c, $u, ['revision' => 0, 'total_cents' => 1000, 'installments' => [['label' => 'Integral', 'share_bps' => 10000, 'trigger' => 'assinatura']]]);
        $s->acceptPlan($c, $u, $id, ['revision' => 1, 'evidence' => 'Aceite registrado']);
        $s->confirmReceivables($c, $u, $s->previewReceivables($c, $u, $id));
        $r = DB::table('receivables')->first();
        $data = ['revision' => 0, 'amount_cents' => 100, 'request_key' => 'integrity-01', 'paid_at' => today()->toDateString(), 'evidence' => 'Conferido'];
        $s->settle($c, $u, 'receivables', $r->id, $data);
        $other = Opportunity::create(['title' => 'Outro', 'client_name' => 'Outro']);
        $this->post("/opportunities/{$other->id}/finance/receivables/{$r->id}/settle", $data)->assertNotFound();
        $data['amount_cents'] = 200;
        $this->post("/opportunities/{$c->id}/finance/receivables/{$r->id}/settle", $data)->assertSessionHasErrors('request_key');
        $this->assertDatabaseCount('financial_settlements', 1);
    }
}
