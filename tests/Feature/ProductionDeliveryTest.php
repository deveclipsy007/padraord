<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Opportunity;
use App\Models\Supplier;
use App\Models\SupplierQuote;
use App\Models\User;
use App\Services\ProductionOperations;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductionDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_assignment_rejects_an_overlapping_schedule_for_the_same_responsible(): void
    {
        $actor = User::factory()->create();
        $responsible = User::factory()->create();
        $opportunity = Opportunity::create(['title' => 'Convenção', 'client_name' => 'Cliente', 'stage' => 'production']);
        $operations = app(ProductionOperations::class);
        $first = $operations->createTask($opportunity, $actor, [
            'title' => 'Montagem de palco',
            'phase' => 'setup',
            'scheduled_starts_at' => '2026-09-20 08:00:00',
            'scheduled_ends_at' => '2026-09-20 12:00:00',
            'team' => [['user_id' => $responsible->id, 'role' => 'Coordenação de montagem', 'is_responsible' => true]],
        ]);
        $second = $operations->createTask($opportunity, $actor, ['title' => 'Passagem de som', 'phase' => 'setup']);

        $this->assertDatabaseHas('production_task_assignments', [
            'production_task_id' => $first->id,
            'user_id' => $responsible->id,
            'role' => 'Coordenação de montagem',
            'is_responsible' => true,
        ]);

        try {
            $operations->scheduleTask($second, $actor, [
                'scheduled_starts_at' => '2026-09-20 11:00:00',
                'scheduled_ends_at' => '2026-09-20 13:00:00',
                'team' => [['user_id' => $responsible->id, 'role' => 'Coordenação de som', 'is_responsible' => true]],
            ]);
            $this->fail('A mesma pessoa não pode ser escalada em tarefas simultâneas.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('scheduled_starts_at', $exception->errors());
        }

        $this->assertNull($second->fresh()->scheduled_starts_at);
    }

    public function test_setup_and_teardown_checklist_requires_image_evidence_before_completion(): void
    {
        $actor = User::factory()->create();
        $opportunity = Opportunity::create(['title' => 'Convenção', 'client_name' => 'Cliente', 'stage' => 'production']);
        $operations = app(ProductionOperations::class);
        $task = $operations->createTask($opportunity, $actor, ['title' => 'Montagem de palco', 'phase' => 'setup']);
        $checklist = $operations->createChecklist($opportunity, $actor, [
            'task_id' => $task->id,
            'phase' => 'setup',
            'title' => 'Liberação de montagem',
            'items' => [
                ['title' => 'Estrutura posicionada', 'requires_photo' => true],
                ['title' => 'Energia conferida', 'requires_photo' => false],
            ],
        ]);
        $photo = Attachment::create([
            'opportunity_id' => $opportunity->id,
            'uploaded_by' => $actor->id,
            'original_name' => 'estrutura.png',
            'path' => 'case-attachments/test/estrutura.png',
            'mime_type' => 'image/png',
            'size_bytes' => 321,
            'module' => 'production',
            'sha256' => str_repeat('a', 64),
        ]);
        $photoRequired = $checklist->items()->where('requires_photo', true)->firstOrFail();
        $plainItem = $checklist->items()->where('requires_photo', false)->firstOrFail();

        try {
            $operations->completeChecklistItem($opportunity, $photoRequired, $actor);
            $this->fail('O item que exige foto não pode ser concluído sem evidência.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('photo_attachment_id', $exception->errors());
        }

        $operations->completeChecklistItem($opportunity, $photoRequired, $actor, $photo->id);
        $operations->completeChecklistItem($opportunity, $plainItem, $actor);

        $this->assertDatabaseHas('production_checklist_items', [
            'id' => $photoRequired->id,
            'photo_attachment_id' => $photo->id,
        ]);
        $this->assertSame('completed', $checklist->fresh()->status);
    }

    public function test_timeline_orders_dependencies_and_rejects_a_task_scheduled_before_its_predecessor(): void
    {
        $actor = User::factory()->create();
        $opportunity = Opportunity::create(['title' => 'Convenção', 'client_name' => 'Cliente', 'stage' => 'production']);
        $operations = app(ProductionOperations::class);
        $predecessor = $operations->createTask($opportunity, $actor, [
            'title' => 'Liberar acesso do local',
            'phase' => 'preparation',
            'scheduled_starts_at' => '2026-09-20 08:00:00',
            'scheduled_ends_at' => '2026-09-20 10:00:00',
        ]);
        $dependent = $operations->createTask($opportunity, $actor, [
            'title' => 'Montar estrutura',
            'phase' => 'setup',
            'dependency_id' => $predecessor->id,
        ]);

        try {
            $operations->scheduleTask($dependent, $actor, [
                'scheduled_starts_at' => '2026-09-20 09:30:00',
                'scheduled_ends_at' => '2026-09-20 12:00:00',
            ]);
            $this->fail('Uma tarefa dependente não pode começar antes da anterior terminar.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('scheduled_starts_at', $exception->errors());
        }

        $operations->scheduleTask($dependent, $actor, [
            'scheduled_starts_at' => '2026-09-20 10:00:00',
            'scheduled_ends_at' => '2026-09-20 12:00:00',
        ]);

        $timeline = $operations->timeline($opportunity);

        $this->assertSame([$predecessor->id, $dependent->id], array_column($timeline, 'id'));
        $this->assertSame($predecessor->id, $timeline[1]['dependency_id']);
        $this->assertSame(CarbonImmutable::parse('2026-09-20 10:00:00')->toIso8601String(), $timeline[1]['scheduled_starts_at']);
        $this->assertSame(CarbonImmutable::parse('2026-09-20 12:00:00')->toIso8601String(), $timeline[1]['scheduled_ends_at']);
    }

    public function test_service_order_is_frozen_and_receipt_is_registered_once(): void
    {
        $actor = User::factory()->create();
        $opportunity = Opportunity::create([
            'title' => 'Convenção',
            'client_name' => 'Cliente',
            'stage' => 'production',
            'event_date' => '2026-09-20',
        ]);
        $supplier = Supplier::create(['name' => 'Som Ao Vivo', 'service' => 'Sonorização', 'status' => 'active']);
        $quote = SupplierQuote::create([
            'supplier_id' => $supplier->id,
            'opportunity_id' => $opportunity->id,
            'service' => 'Sonorização completa',
            'unit_cost_cents' => 250000,
            'quantity' => 1,
            'unit' => 'diária',
            'valid_until' => '2026-12-01',
            'conditions' => 'Inclui montagem e técnico responsável.',
            'evidence' => 'Cotação formal recebida em 10/09/2026.',
        ]);
        $operations = app(ProductionOperations::class);
        $task = $operations->createTask($opportunity, $actor, ['title' => 'Operar sonorização', 'phase' => 'event']);
        $order = $operations->createServiceOrder($opportunity, $actor, [
            'supplier_id' => $supplier->id,
            'source_quote_id' => $quote->id,
            'task_ids' => [$task->id],
            'title' => 'Ordem de serviço · sonorização',
        ]);

        $issued = $operations->issueServiceOrder($opportunity, $order, $actor);
        $quote->update(['conditions' => 'Condição alterada depois da emissão.']);
        $receipt = Attachment::create([
            'opportunity_id' => $opportunity->id,
            'uploaded_by' => $actor->id,
            'original_name' => 'recebimento.pdf',
            'path' => 'case-attachments/test/recebimento.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 456,
            'module' => 'production',
            'sha256' => str_repeat('b', 64),
        ]);

        $received = $operations->receiveServiceOrder($opportunity, $issued, $actor, $receipt->id);

        $this->assertSame('received', $received->status);
        $this->assertSame('Inclui montagem e técnico responsável.', $received->scope['quote']['conditions']);
        $this->assertSame($receipt->id, $received->receipt_attachment_id);
        $this->assertDatabaseCount('production_service_orders', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'production.service_order_received', 'subject_id' => $order->id]);
    }

    public function test_production_workspace_exposes_the_delivery_console_without_attachment_paths(): void
    {
        $actor = User::factory()->create();
        $this->actingAs($actor);
        $opportunity = Opportunity::create(['title' => 'Convenção', 'client_name' => 'Cliente', 'stage' => 'production']);
        $operations = app(ProductionOperations::class);
        $task = $operations->createTask($opportunity, $actor, [
            'title' => 'Montagem de palco',
            'phase' => 'setup',
            'scheduled_starts_at' => '2026-09-20 08:00:00',
            'scheduled_ends_at' => '2026-09-20 12:00:00',
        ]);
        $operations->createChecklist($opportunity, $actor, [
            'task_id' => $task->id,
            'phase' => 'setup',
            'title' => 'Liberação de montagem',
            'items' => [['title' => 'Estrutura pronta', 'requires_photo' => true]],
        ]);

        $this->get("/opportunities/{$opportunity->id}/production")
            ->assertInertia(fn ($page) => $page
                ->component('Production')
                ->has('timeline', 1)
                ->has('timeline.0.team', 1)
                ->has('teamMembers', 1)
                ->has('checklists', 1)
                ->has('checklists.0.items', 1)
                ->has('serviceOrders', 0)
                ->has('attachmentLinks', 1)
                ->has('attachments', 0));
    }
}
