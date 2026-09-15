<?php

namespace Tests\Feature;

use App\Contracts\CommercialChannel;
use App\Models\Client;
use App\Models\Document;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\DocumentRevisions;
use App\Services\DocumentSharing;
use App\Services\EventFinance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CommercialDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_proposal_release_requires_complete_sections_and_preserves_its_sources(): void
    {
        [$actor, $case, $document] = $this->releasedProposal();

        $this->assertSame('Projeto técnico, operação e acompanhamento.', data_get($document->release_snapshot, 'sections.inclusions'));
        $this->assertSame('Não inclui locação de mobiliário.', data_get($document->release_snapshot, 'sections.exclusions'));
        $this->assertSame($case->briefing_revision, data_get($document->release_snapshot, 'sources.briefing_revision'));
        $this->assertSame(1, data_get($document->release_snapshot, 'sources.budget_revision'));

        $incomplete = app(DocumentRevisions::class)->draft($case, $actor, 'proposal', [
            'title' => 'Proposta incompleta',
            'purpose' => 'management',
            'expected_version' => 1,
            'sections' => [
                'objective' => 'Objetivo claro.',
                'scope' => 'Escopo claro.',
                'inclusions' => 'Operação.',
                'conditions' => 'Condições registradas.',
            ],
        ]);

        try {
            app(DocumentRevisions::class)->review($case, $incomplete, $actor);
            $this->fail('Uma proposta sem exclusões não pode ser liberada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('document', $exception->errors());
        }
    }

    public function test_released_proposal_freezes_structured_content_sources_and_public_presentation(): void
    {
        [$actor, $case, $document] = $this->releasedProposal();

        $this->assertSame('document-release-v1', $document->release_snapshot['schema']);
        $this->assertNotEmpty($document->release_hash);
        $this->assertStringContainsString('Fora do escopo', $document->release_snapshot['html']);
        $this->assertStringContainsString('Não inclui locação de mobiliário.', $document->release_snapshot['html']);

        $content = $document->content;
        $content['sections']['scope'] = 'Texto alterado depois da liberação.';
        $document->update(['content' => $content]);

        $this->actingAs($actor)
            ->get("/opportunities/{$case->id}/documents/{$document->id}/release")
            ->assertOk()
            ->assertSee('Iluminação e som para a plenária.')
            ->assertDontSee('Texto alterado depois da liberação.');

        $share = app(DocumentSharing::class)->create($case, $document->fresh(), $actor);
        $this->get($share['url'])
            ->assertOk()
            ->assertSee('Iluminação e som para a plenária.')
            ->assertDontSee('Texto alterado depois da liberação.');
    }

    public function test_contract_requires_an_approved_template_and_payment_plan_then_requires_reopening_after_signature(): void
    {
        Storage::fake('local');
        config([
            'commercial.contract_template_approved' => true,
            'commercial.contract_template_evidence' => 'Modelo jurídico interno aprovado para teste.',
        ]);
        $actor = User::factory()->create(['can_approve_commercial' => true]);
        $client = Client::create([
            'name' => 'Instituto Horizonte',
            'legal_name' => 'Instituto Horizonte Cultural Ltda',
            'tax_id' => '11222333000181',
            'billing_address' => ['logradouro' => 'Rua do Teatro', 'cidade' => 'Recife', 'uf' => 'PE'],
        ]);
        $case = Opportunity::create([
            'title' => 'Convenção Horizonte',
            'client_name' => $client->name,
            'client_id' => $client->id,
            'stage' => 'contract',
            'event_date' => today()->addMonth(),
            'briefing_revision' => 1,
            'briefing_approval' => ['revision' => 1, 'fields' => ['scope' => 'Convenção anual']],
        ]);
        $case->budgets()->create([
            'version' => 1,
            'revision' => 1,
            'purpose' => 'execution',
            'status' => 'approved',
            'snapshot' => ['demo' => false, 'totalCents' => 120000],
        ]);
        $finance = app(EventFinance::class);
        $planId = $finance->savePlan($case, $actor, [
            'revision' => 0,
            'total_cents' => 120000,
            'installments' => [['label' => 'Assinatura', 'share_bps' => 10000, 'trigger' => 'assinatura']],
        ]);
        $finance->acceptPlan($case, $actor, $planId, ['revision' => 1, 'evidence' => 'Condições comerciais aprovadas.']);

        $document = app(DocumentRevisions::class)->draft($case, $actor, 'contract', [
            'title' => 'Contrato da Convenção Horizonte',
            'purpose' => 'contract',
            'expected_version' => 0,
            'sections' => [
                'objective' => 'Produzir a convenção anual.',
                'scope' => 'Iluminação e som para a plenária.',
                'conditions' => 'Pagamentos conforme plano registrado.',
                'clauses' => 'Cláusula de cancelamento e responsabilidades das partes.',
            ],
        ]);

        app(DocumentRevisions::class)->review($case, $document, $actor);
        $document->refresh();
        $this->assertSame('reviewed', $document->status);
        $this->assertSame($planId, data_get($document->release_snapshot, 'sources.payment_plan.id'));
        $this->assertStringContainsString('Cláusulas', $document->release_snapshot['html']);

        app(DocumentRevisions::class)->sent($case, $document, $actor, 'Registro de envio do contrato.');
        app(DocumentRevisions::class)->externalSignature($case, $document->fresh(), $actor, [
            'signer_name' => 'Ana Horizonte',
            'signed_at' => today()->toDateString(),
            'method' => 'plataforma_externa',
            'evidence' => 'Protocolo de assinatura #2026-09.',
        ]);

        try {
            app(DocumentRevisions::class)->draft($case, $actor, 'contract', [
                'title' => 'Contrato alterado',
                'purpose' => 'contract',
                'expected_version' => 1,
                'sections' => ['objective' => 'Novo', 'scope' => 'Novo', 'conditions' => 'Novo', 'clauses' => 'Novo'],
            ]);
            $this->fail('A assinatura deveria bloquear uma nova versão antes da reabertura.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('document', $exception->errors());
        }

        app(DocumentRevisions::class)->reopen($case, $document->fresh(), $actor, 'Aditivo solicitado pelo cliente.');
        $replacement = app(DocumentRevisions::class)->draft($case, $actor, 'contract', [
            'title' => 'Contrato com aditivo',
            'purpose' => 'contract',
            'expected_version' => 1,
            'sections' => ['objective' => 'Novo', 'scope' => 'Novo', 'conditions' => 'Novo', 'clauses' => 'Novo'],
        ]);
        $this->assertSame(2, $replacement->version);
    }

    public function test_acceptance_creates_a_project_onboarding_receipt_and_outbox_exactly_once(): void
    {
        [$actor, $case, $document] = $this->releasedProposal();
        $share = app(DocumentSharing::class)->create($case, $document, $actor);

        $this->post($share['url'].'/decision', [
            'decision' => 'accepted',
            'message' => 'Aprovado para iniciar.',
            'decided_by_name' => 'Ana Horizonte',
        ])->assertRedirect();

        $this->assertDatabaseHas('delivery_projects', [
            'opportunity_id' => $case->id,
            'source_document_id' => $document->id,
            'source_hash' => $document->release_hash,
            'status' => 'onboarding',
        ]);
        $this->assertDatabaseHas('commercial_acceptance_receipts', [
            'document_id' => $document->id,
            'document_hash' => $document->release_hash,
        ]);
        $this->assertDatabaseCount('delivery_project_onboarding_steps', 3);
        $this->assertDatabaseCount('commercial_acceptance_outbox', 1);
        $this->assertDatabaseHas('commercial_acceptance_outbox', ['channel' => 'manual', 'status' => 'pending']);

        $this->post($share['url'].'/decision', [
            'decision' => 'accepted',
            'message' => 'Aprovado para iniciar.',
            'decided_by_name' => 'Ana Horizonte',
        ])->assertRedirect();

        $this->assertDatabaseCount('delivery_projects', 1);
        $this->assertDatabaseCount('commercial_acceptance_receipts', 1);
        $this->assertDatabaseCount('commercial_acceptance_outbox', 1);
        $this->assertDatabaseCount('delivery_project_onboarding_steps', 3);
    }

    public function test_manual_channel_prepares_an_acceptance_without_external_delivery(): void
    {
        $prepared = app(CommercialChannel::class)->prepare('commercial.accepted', [
            'opportunity_id' => 42,
            'document_id' => 84,
        ]);

        $this->assertSame('manual', $prepared['channel']);
        $this->assertSame('commercial.accepted', $prepared['event']);
        $this->assertSame(['opportunity_id' => 42, 'document_id' => 84], $prepared['payload']);
    }

    /** @return array{0: User, 1: Opportunity, 2: Document} */
    private function releasedProposal(): array
    {
        Storage::fake('local');
        config([
            'commercial.rules_approved' => true,
            'commercial.rules_evidence' => 'Regras comerciais aprovadas para teste.',
        ]);
        $actor = User::factory()->create(['can_approve_commercial' => true]);
        $case = Opportunity::create([
            'title' => 'Convenção Horizonte',
            'client_name' => 'Instituto Horizonte',
            'stage' => 'proposal',
            'briefing_revision' => 1,
            'briefing_approval' => ['revision' => 1, 'fields' => ['scope' => 'Convenção anual']],
        ]);
        $case->budgets()->create([
            'version' => 1,
            'revision' => 1,
            'purpose' => 'management',
            'status' => 'approved',
            'snapshot' => ['demo' => false, 'totalCents' => 120000],
        ]);
        $document = app(DocumentRevisions::class)->draft($case, $actor, 'proposal', [
            'title' => 'Proposta de Gestão Horizonte',
            'purpose' => 'management',
            'expected_version' => 0,
            'sections' => [
                'objective' => 'Criar uma convenção anual com clareza operacional.',
                'scope' => 'Iluminação e som para a plenária.',
                'inclusions' => 'Projeto técnico, operação e acompanhamento.',
                'exclusions' => 'Não inclui locação de mobiliário.',
                'conditions' => 'Valores e entregas conforme fontes registradas.',
            ],
        ]);
        $revisions = app(DocumentRevisions::class);
        $revisions->review($case, $document, $actor);
        $revisions->sent($case, $document->fresh(), $actor, 'Registro de envio comercial #2026-09.');

        return [$actor, $case->fresh(), $document->fresh()];
    }
}
