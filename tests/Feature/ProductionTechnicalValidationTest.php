<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\ProductionOperations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductionTechnicalValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dependency_cycle_is_rejected_when_creating_a_task(): void
    {
        $user = User::factory()->create();
        $case = Opportunity::create(['title' => 'Caso técnico', 'client_name' => 'Cliente', 'stage' => 'production']);
        $first = app(ProductionOperations::class)->createTask($case, $user, ['title' => 'Medir palco']);
        $second = app(ProductionOperations::class)->createTask($case, $user, ['title' => 'Liberar montagem', 'dependency_id' => $first->id]);

        $this->expectException(ValidationException::class);
        app(ProductionOperations::class)->updateTask($first, $user, ['dependency_id' => $second->id]);
    }

    public function test_task_with_technical_validation_cannot_finish_until_supplier_reconfirms(): void
    {
        $user = User::factory()->create();
        $case = Opportunity::create(['title' => 'Caso técnico', 'client_name' => 'Cliente', 'stage' => 'production']);
        $validation = app(ProductionOperations::class)->saveTechnicalValidation($case, $user, [
            'reference' => 'Planta do palco',
            'measurements' => ['width' => '8m', 'depth' => '4m'],
            'evidence' => 'Visita técnica 10/09',
            'supplier_name' => 'Luz Norte',
        ]);
        $task = app(ProductionOperations::class)->createTask($case, $user, ['title' => 'Liberar estrutura', 'technical_validation_id' => $validation->id]);

        try {
            app(ProductionOperations::class)->transition($task, $user, 'done');
            $this->fail('A validação técnica pendente deveria bloquear a conclusão.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('technical_validation', $exception->errors());
        }

        app(ProductionOperations::class)->confirmTechnicalValidation($case, $validation, $user, 'Fornecedor reconfirmou medidas por e-mail.');
        app(ProductionOperations::class)->transition($task->fresh(), $user, 'done');
        $this->assertDatabaseHas('production_tasks', ['id' => $task->id, 'status' => 'done']);
    }

    public function test_scope_preview_can_be_confirmed_once_without_duplicate_tasks(): void
    {
        $user = User::factory()->create();
        $case = Opportunity::create(['title' => 'Caso contratado', 'client_name' => 'Cliente', 'stage' => 'production']);
        $budget = Budget::create(['opportunity_id' => $case->id, 'version' => 1, 'status' => 'approved', 'purpose' => 'management', 'snapshot' => ['demo' => false]]);
        $budget->items()->create(['category' => 'Estrutura', 'description' => 'Montar palco', 'quantity' => 1, 'unit' => 'serviço', 'unit_cost_cents' => 100000, 'margin_percent' => 0]);
        $budget->items()->create(['category' => 'Luz', 'description' => 'Testar iluminação', 'quantity' => 1, 'unit' => 'serviço', 'unit_cost_cents' => 50000, 'margin_percent' => 0]);

        $preview = app(ProductionOperations::class)->prepareFromApprovedScope($case, $user);
        $result = app(ProductionOperations::class)->confirmScopePreview($case, $preview, $user);
        $again = app(ProductionOperations::class)->confirmScopePreview($case, $preview->fresh(), $user);

        $this->assertSame($result, $again);
        $this->assertDatabaseCount('production_tasks', 2);
        $this->assertDatabaseHas('production_task_previews', ['id' => $preview->id, 'status' => 'confirmed']);
    }

    public function test_changing_confirmed_measurements_invalidates_reconfirmation_and_audits_the_impact(): void
    {
        $user = User::factory()->create();
        $case = Opportunity::create(['title' => 'Caso técnico', 'client_name' => 'Cliente', 'stage' => 'production']);
        $service = app(ProductionOperations::class);
        $validation = $service->saveTechnicalValidation($case, $user, ['reference' => 'Estrutura', 'measurements' => ['width' => '8m'], 'evidence' => 'Visita 1']);
        $service->confirmTechnicalValidation($case, $validation, $user, 'Fornecedor confirmou 8m.');

        $updated = $service->saveTechnicalValidation($case, $user, ['id' => $validation->id, 'revision' => $validation->fresh()->revision, 'reference' => 'Estrutura', 'measurements' => ['width' => '9m'], 'evidence' => 'Visita 2']);

        $this->assertSame('pending', $updated->status);
        $this->assertNull($updated->confirmed_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'production.technical_validation_saved', 'subject_id' => $validation->id]);
    }

    public function test_reopening_a_completed_task_records_dependents_that_may_need_rework(): void
    {
        $user = User::factory()->create();
        $case = Opportunity::create(['title' => 'Caso de produção', 'client_name' => 'Cliente', 'stage' => 'production']);
        $first = app(ProductionOperations::class)->createTask($case, $user, ['title' => 'Validar medidas']);
        $second = app(ProductionOperations::class)->createTask($case, $user, ['title' => 'Liberar montagem', 'dependency_id' => $first->id]);
        app(ProductionOperations::class)->transition($first, $user, 'done');
        app(ProductionOperations::class)->transition($second, $user, 'done');

        app(ProductionOperations::class)->transition($first->fresh(), $user, 'todo');

        $this->assertDatabaseHas('audit_logs', ['action' => 'production.task_reopened', 'subject_id' => $first->id]);
        $this->assertSame('done', $second->fresh()->status);
    }
}
