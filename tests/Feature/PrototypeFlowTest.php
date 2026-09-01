<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\PrototypeCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PrototypeFlowTest extends TestCase
{
    use RefreshDatabase;

    private function start(): PrototypeCase
    {
        $this->actingAs(User::factory()->create());
        $this->post('/prototype')->assertRedirect();

        return PrototypeCase::firstOrFail();
    }

    private function action(PrototypeCase $case, string $action, array $data = [])
    {
        return $this->post('/prototype/'.$case->id.'/actions', ['action' => $action, 'revision' => $case->fresh()->revision] + $data);
    }

    public function test_demo_uses_shared_extraction_without_external_calls(): void
    {
        Http::fake();
        $case = $this->start();
        $this->action($case, 'message', ['text' => "Objetivo: Conectar pessoas\nLocal: Recife"])->assertSessionHasNoErrors();
        $suggestions = collect($case->fresh()->state['suggestions'])->keyBy('field');
        $this->assertSame('Conectar pessoas', $suggestions['objective']['suggested']);
        $this->assertSame('Recife', $suggestions['location']['suggested']);
        Http::assertNothingSent();
    }

    public function test_demo_is_persistent_and_separate_from_real_work(): void
    {
        $case = $this->start();
        $this->assertSame('demo', $case->mode);
        $this->assertDatabaseCount('opportunities', 0);
        foreach (['overview', 'commercial', 'briefing', 'viability', 'suppliers', 'budget', 'documents', 'production', 'post-event', 'history'] as $section) {
            $this->get('/prototype/'.$case->id.'/'.$section)->assertOk();
        }
    }

    public function test_conflicts_and_technical_changes_are_recoverable(): void
    {
        $case = $this->start();
        $this->action($case, 'technical_save', ['description' => 'Balcão 2m × 1m', 'quantity' => 2])->assertSessionHasNoErrors();
        $this->post('/prototype/'.$case->id.'/actions', ['action' => 'technical_confirm', 'revision' => 0, 'evidence' => 'Confirmação simulada'])->assertSessionHasErrors('revision');
        $this->action($case, 'technical_confirm', ['evidence' => 'Confirmação simulada'])->assertSessionHasNoErrors();
        $this->action($case, 'technical_save', ['description' => 'Balcão 3m × 1m', 'quantity' => 3])->assertSessionHasNoErrors();
        $this->assertFalse($case->fresh()->state['technical']['confirmed']);
        $this->assertNotEmpty($case->fresh()->state['impacts']);
    }

    public function test_viability_can_close_without_management(): void
    {
        $case = $this->start();
        $this->action($case, 'contract_viability', ['evidence' => 'Contratação simulada'])->assertSessionHasNoErrors();
        $this->action($case, 'deliver_viability', ['deliverables' => ['concept', 'estimate', 'suppliers', 'schedule'], 'evidence' => 'Entrega simulada'])->assertSessionHasNoErrors();
        $this->action($case, 'accept_viability', ['evidence' => 'Aceite simulado'])->assertSessionHasNoErrors();
        $this->action($case, 'close_viability', ['evidence' => 'Projeto entregue'])->assertSessionHasNoErrors();
        $this->assertSame('viability_completed', $case->fresh()->state['outcome']);
        $this->assertSame('not_contracted', $case->fresh()->state['management']);
    }

    public function test_guest_cannot_mutate_or_export_demonstrations(): void
    {
        $this->post('/prototype')->assertRedirect('/login');
        $this->get('/prototype/1/documents/1/print')->assertRedirect('/login');
    }

    public function test_complete_management_journey_and_immutable_document_snapshot(): void
    {
        $case = $this->start();
        $briefing = ['objective' => 'Relacionamento', 'audience' => '100 pessoas', 'date' => '2026-12-01', 'location' => 'Espaço fictício', 'investment' => '50000', 'scope' => 'Conferência', 'restrictions' => '', 'references' => 'Referência fictícia'];
        $actions = [
            ['message', ['text' => 'Contexto fictício do evento']],
            ['suggestion', ['id' => 1, 'decision' => 'accepted']],
            ['briefing_save', ['briefing' => $briefing]],
            ['briefing_approve', []],
            ['contract_viability', ['evidence' => 'Contratação simulada']],
            ['deliver_viability', ['deliverables' => ['concept', 'estimate', 'suppliers', 'schedule'], 'evidence' => 'Entrega simulada']],
            ['accept_viability', ['evidence' => 'Aceite simulado']],
            ['quote', ['supplier' => 'Fornecedor fictício', 'description' => 'Balcões', 'cost' => '1000,00', 'valid_until' => today()->addMonth()->format('Y-m-d'), 'evidence' => 'Cotação fictícia']],
            ['item', ['description' => 'Balcões', 'cost' => '1000,00', 'quantity' => '2', 'quote_id' => 1]],
            ['technical_save', ['description' => 'Balcões 2m × 1m', 'quantity' => 2]],
            ['technical_confirm', ['evidence' => 'Reconfirmado no teste']],
            ['budget_review', []],
            ['document', ['document_type' => 'proposal', 'purpose' => 'management', 'text' => 'Condições fictícias']],
            ['document_review', ['id' => 1, 'evidence' => 'Revisão fictícia']],
            ['document_send', ['id' => 1, 'evidence' => 'Envio simulado']],
            ['document_accept', ['id' => 1, 'evidence' => 'Aceite simulado']],
            ['contract_management', ['evidence' => 'Contratação simulada']],
            ['task', ['text' => 'Montar balcões', 'owner' => 'Produtor de teste', 'due' => today()->addDays(3)->format('Y-m-d'), 'phase' => 'setup']],
            ['task_done', ['id' => 1]],
            ['execution', []],
            ['post_start', []],
            ['post_save', ['text' => 'Evento concluído', 'learning' => 'Reconfirmar sempre as dimensões', 'cost' => '2000,00', 'rating' => 5]],
            ['close_event', []],
        ];
        foreach ($actions as [$action, $data]) {
            $this->action($case, $action, $data)->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->assertSame('event_completed', $case->fresh()->state['outcome']);
        $this->assertSame(200000, $case->fresh()->state['documents'][0]['budget']['totalCents']);
        $this->get('/prototype/'.$case->id.'/documents/1/print')->assertOk()->assertSee('DEMONSTRAÇÃO')->assertSee('2.000,00');
        $this->assertDatabaseCount('opportunities', 0);
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_expired_quote_blocks_review_without_losing_items(): void
    {
        $case = $this->start();
        $state = $case->state;
        $state['briefingApproved'] = true;
        $case->update(['state' => $state]);
        $this->action($case, 'quote', ['supplier' => 'Fictício', 'description' => 'Palco', 'cost' => '100', 'valid_until' => today()->subDay()->format('Y-m-d'), 'evidence' => 'Teste'])->assertSessionHasNoErrors();
        $this->action($case, 'item', ['description' => 'Palco', 'cost' => '100', 'quantity' => 1, 'quote_id' => 1])->assertSessionHasNoErrors();
        $this->action($case, 'budget_review')->assertSessionHasErrors('action');
        $this->assertCount(1, $case->fresh()->state['items']);
        $this->assertSame('draft', $case->fresh()->state['budgetStatus']);
    }

    public function test_projects_and_today_show_real_work_separate_from_demo(): void
    {
        $case = $this->start();
        Activity::create(['title' => 'Cobrar cotação', 'user_id' => auth()->id(), 'type' => 'task', 'priority' => 'high', 'status' => 'todo']);
        $this->get('/')->assertOk()->assertInertia(fn ($page) => $page->has('todayQueue', 1)->where('todayQueue.0.title', 'Cobrar cotação'));
        $this->get('/projects')->assertOk()->assertInertia(fn ($page) => $page->has('cases', 1)->has('opportunities', 0));
    }

    public function test_change_preserves_document_content_and_opens_new_budget_revision(): void
    {
        $case = $this->start();
        $s = $case->state;
        $s['budgetStatus'] = 'reviewed';
        $s['documents'] = [['id' => 1, 'notes' => 'Cópia enviada preservada', 'stale' => false, 'status' => 'accepted_demo', 'purpose' => 'management']];
        $case->update(['state' => $s]);
        $this->action($case, 'technical_save', ['description' => 'Balcão alterado', 'quantity' => 3])->assertSessionHasNoErrors();
        $this->assertSame(2, $case->fresh()->state['budgetVersion']);
        $this->assertSame('Cópia enviada preservada', $case->fresh()->state['documents'][0]['notes']);
        $this->assertTrue($case->fresh()->state['documents'][0]['stale']);
    }

    public function test_execution_cannot_bypass_stale_management_document(): void
    {
        $case = $this->start();
        $s = $case->state;
        $s['management'] = 'planning';
        $s['technical']['confirmed'] = true;
        $s['budgetStatus'] = 'reviewed';
        $s['documents'] = [['id' => 1, 'stale' => true, 'status' => 'accepted_demo', 'purpose' => 'management']];
        $case->update(['state' => $s]);
        $this->action($case, 'execution')->assertSessionHasErrors('action');
        $this->assertSame('planning', $case->fresh()->state['management']);
    }

    public function test_command_search_finds_demo_with_explicit_label(): void
    {
        $case = $this->start();
        $this->getJson('/search?q=Horizonte')->assertOk()->assertJsonFragment(['href' => '/prototype/'.$case->id.'/overview', 'detail' => 'Demonstração · dados fictícios']);
    }
}
