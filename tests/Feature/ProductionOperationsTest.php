<?php

namespace Tests\Feature;

use App\Models\Opportunity;
use App\Models\User;
use App\Services\ProductionOperations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductionOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_dependency_is_kept_inside_the_same_case_and_blocks_completion_until_done(): void
    {
        $user = User::factory()->create();
        $opportunity = Opportunity::create(['title' => 'Caso de produção', 'client_name' => 'Cliente', 'stage' => 'production']);

        $first = app(ProductionOperations::class)->createTask($opportunity, $user, [
            'title' => 'Confirmar medidas',
            'phase' => 'preparation',
            'priority' => 'high',
        ]);
        $second = app(ProductionOperations::class)->createTask($opportunity, $user, [
            'title' => 'Liberar fornecedor',
            'phase' => 'setup',
            'dependency_id' => $first->id,
        ]);

        try {
            app(ProductionOperations::class)->transition($second, $user, 'done');
            $this->fail('A tarefa dependente não deveria ser concluída antes da anterior.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertSame('todo', $second->fresh()->status);
        $this->assertDatabaseHas('production_tasks', ['id' => $second->id, 'dependency_id' => $first->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'production.task_created', 'subject_id' => $second->id]);

        app(ProductionOperations::class)->transition($first->fresh(), $user, 'done');
        app(ProductionOperations::class)->transition($second->fresh(), $user, 'done');
        $this->assertDatabaseHas('production_tasks', ['id' => $second->id, 'status' => 'done']);
    }

    public function test_blocked_task_requires_a_reason_and_reopening_clears_the_block(): void
    {
        $user = User::factory()->create();
        $opportunity = Opportunity::create(['title' => 'Caso de produção', 'client_name' => 'Cliente', 'stage' => 'production']);
        $task = app(ProductionOperations::class)->createTask($opportunity, $user, ['title' => 'Aguardar planta']);

        try {
            app(ProductionOperations::class)->transition($task, $user, 'blocked');
            $this->fail('Uma tarefa bloqueada precisa de uma justificativa.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('blocked_reason', $exception->errors());
        }
    }

    public function test_task_can_be_blocked_with_reason_and_reopened(): void
    {
        $user = User::factory()->create();
        $opportunity = Opportunity::create(['title' => 'Caso de produção', 'client_name' => 'Cliente', 'stage' => 'production']);
        $task = app(ProductionOperations::class)->createTask($opportunity, $user, ['title' => 'Aguardar planta']);

        app(ProductionOperations::class)->transition($task, $user, 'blocked', 'Planta baixa pendente do cliente.');
        $this->assertDatabaseHas('production_tasks', ['id' => $task->id, 'status' => 'blocked', 'blocked_reason' => 'Planta baixa pendente do cliente.']);

        app(ProductionOperations::class)->transition($task->fresh(), $user, 'todo');
        $this->assertDatabaseHas('production_tasks', ['id' => $task->id, 'status' => 'todo', 'blocked_reason' => null]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'production.task_status_changed', 'subject_id' => $task->id]);
    }
}
