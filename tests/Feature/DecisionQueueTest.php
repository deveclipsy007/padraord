<?php

namespace Tests\Feature;

use App\Models\CaseBlocker;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\DecisionQueue;
use App\Services\NextActionService;
use App\Services\ProductionOperations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DecisionQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_critical_blocker_precedes_other_actions_and_explains_its_operational_impact(): void
    {
        $actor = User::factory()->create();
        $owner = User::factory()->create(['name' => 'Responsável pela decisão']);
        $case = Opportunity::create(['title' => 'Convenção anual', 'client_name' => 'Cliente', 'stage' => 'production']);
        $operations = app(ProductionOperations::class);
        $access = $operations->createTask($case, $actor, ['title' => 'Liberar acesso de carga', 'priority' => 'high']);
        $setup = $operations->createTask($case, $actor, ['title' => 'Montar estrutura', 'dependency_id' => $access->id]);
        $check = $operations->createTask($case, $actor, ['title' => 'Conferir segurança', 'dependency_id' => $setup->id]);

        $blocker = app(DecisionQueue::class)->openBlocker($case, $actor, [
            'title' => 'Confirmar acesso de carga',
            'reason' => 'O local ainda não liberou a janela de montagem.',
            'severity' => 'critical',
            'importance' => 'high',
            'effort' => 'small',
            'owner_id' => $owner->id,
            'production_task_id' => $access->id,
        ]);

        $queue = app(DecisionQueue::class)->for($case);

        $this->assertSame('blocker', $queue[0]['kind']);
        $this->assertSame($blocker->id, $queue[0]['id']);
        $this->assertSame('Confirmar acesso de carga', $queue[0]['title']);
        $this->assertSame('Responsável pela decisão', $queue[0]['owner']);
        $this->assertSame('critical', $queue[0]['severity']);
        $this->assertSame('high', $queue[0]['importance']);
        $this->assertSame('small', $queue[0]['effort']);
        $this->assertSame(1, $queue[0]['directWork']);
        $this->assertSame(2, $queue[0]['unlockedWork']);
        $this->assertSame('blocked', $access->fresh()->status);
        $this->assertSame('blocked', $queue[0]['status']);
        $this->assertSame($check->id, $check->fresh()->id);
    }

    public function test_resolving_and_reopening_a_blocker_preserves_its_history_and_task_state(): void
    {
        $actor = User::factory()->create();
        $case = Opportunity::create(['title' => 'Lançamento', 'client_name' => 'Cliente', 'stage' => 'production']);
        $operations = app(ProductionOperations::class);
        $task = $operations->createTask($case, $actor, ['title' => 'Revisar mapa de energia']);
        $operations->transition($task, $actor, 'in_progress');
        $queue = app(DecisionQueue::class);
        $blocker = $queue->openBlocker($case, $actor, [
            'title' => 'Decidir a carga elétrica',
            'reason' => 'O local não confirmou a capacidade do circuito.',
            'severity' => 'high',
            'importance' => 'high',
            'effort' => 'medium',
            'production_task_id' => $task->id,
        ]);

        $resolved = $queue->resolveBlocker($case, $blocker, $actor, 'Capacidade confirmada pelo responsável do local.');
        $this->app->forgetInstance(DecisionQueue::class);

        $this->assertSame('resolved', $resolved->status);
        $this->assertSame('in_progress', $task->fresh()->status);
        $this->assertEmpty(app(DecisionQueue::class)->for($case));

        $reopened = app(DecisionQueue::class)->reopenBlocker($case, $resolved, $actor, 'A confirmação foi revogada pelo local.');

        $this->assertSame($blocker->id, $reopened->id);
        $this->assertSame('open', $reopened->status);
        $this->assertNotNull($reopened->resolved_at);
        $this->assertNotNull($reopened->reopened_at);
        $this->assertSame('blocked', $task->fresh()->status);
        $this->assertDatabaseCount('case_blockers', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'decision_queue.blocker_resolved', 'subject_id' => $blocker->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'decision_queue.blocker_reopened', 'subject_id' => $blocker->id]);
    }

    public function test_queue_combines_the_single_explicit_next_action_with_open_blockers_without_duplicate_sources(): void
    {
        $actor = User::factory()->create();
        $case = Opportunity::create(['title' => 'Encontro de liderança', 'client_name' => 'Cliente', 'stage' => 'production']);
        $task = app(ProductionOperations::class)->createTask($case, $actor, ['title' => 'Confirmar acesso técnico']);
        app(NextActionService::class)->set($case, $actor, [
            'title' => 'Confirmar presença do decisor',
            'user_id' => $actor->id,
            'priority' => 'high',
        ]);
        app(DecisionQueue::class)->openBlocker($case, $actor, [
            'title' => 'Liberar acesso técnico',
            'reason' => 'O condomínio ainda não respondeu sobre a credencial.',
            'severity' => 'critical',
            'importance' => 'high',
            'effort' => 'small',
            'production_task_id' => $task->id,
        ]);

        $queue = app(DecisionQueue::class)->for($case);

        $this->assertCount(2, $queue);
        $this->assertSame('blocker', $queue[0]['kind']);
        $this->assertSame('next_action', $queue[1]['kind']);
        $this->assertSame('Confirmar presença do decisor', $queue[1]['title']);
        $this->assertSame('activity', $queue[1]['source']);
    }

    public function test_workspace_reads_the_recommended_next_decision_from_the_same_ranked_queue(): void
    {
        $actor = User::factory()->create();
        $this->actingAs($actor);
        $case = Opportunity::create(['title' => 'Fórum estratégico', 'client_name' => 'Cliente', 'stage' => 'production']);
        $task = app(ProductionOperations::class)->createTask($case, $actor, ['title' => 'Confirmar doca de carga']);
        app(NextActionService::class)->set($case, $actor, [
            'title' => 'Retornar ao cliente',
            'user_id' => $actor->id,
            'priority' => 'high',
        ]);
        $blocker = app(DecisionQueue::class)->openBlocker($case, $actor, [
            'title' => 'Definir janela da montagem',
            'reason' => 'O local ainda não confirmou o acesso da equipe.',
            'severity' => 'critical',
            'importance' => 'high',
            'effort' => 'small',
            'production_task_id' => $task->id,
        ]);

        $this->get("/opportunities/{$case->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('OpportunityShow')
                ->has('decisionQueue', 2)
                ->where('decisionQueue.0.id', $blocker->id)
                ->where('nextDecision.id', $blocker->id)
                ->where('nextDecision.kind', 'blocker'));
    }

    public function test_operator_can_open_resolve_and_reopen_a_blocker_from_the_workspace_routes(): void
    {
        $actor = User::factory()->create();
        $this->actingAs($actor);
        $case = Opportunity::create(['title' => 'Premiação', 'client_name' => 'Cliente', 'stage' => 'production']);
        $task = app(ProductionOperations::class)->createTask($case, $actor, ['title' => 'Receber a planta atualizada']);

        $this->post("/opportunities/{$case->id}/blockers", [
            'title' => 'Aguardar confirmação de layout',
            'reason' => 'A versão aprovada ainda não foi enviada pelo cliente.',
            'severity' => 'high',
            'importance' => 'high',
            'effort' => 'small',
            'production_task_id' => $task->id,
        ])->assertSessionDoesntHaveErrors();

        $blocker = CaseBlocker::query()->firstOrFail();
        $this->post("/opportunities/{$case->id}/blockers/{$blocker->id}/resolve", [
            'resolution' => 'O cliente enviou e aprovou a planta final.',
        ])->assertSessionDoesntHaveErrors();
        $this->post("/opportunities/{$case->id}/blockers/{$blocker->id}/reopen", [
            'reason' => 'O cliente solicitou uma nova alteração no layout.',
        ])->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('case_blockers', ['id' => $blocker->id, 'status' => 'open']);
        $this->assertDatabaseHas('production_tasks', ['id' => $task->id, 'status' => 'blocked']);
    }

    public function test_queue_marks_a_legacy_circular_dependency_instead_of_overstating_what_the_blocker_unlocks(): void
    {
        $actor = User::factory()->create();
        $case = Opportunity::create(['title' => 'Convenção', 'client_name' => 'Cliente', 'stage' => 'production']);
        $operations = app(ProductionOperations::class);
        $first = $operations->createTask($case, $actor, ['title' => 'Confirmar doca']);
        $second = $operations->createTask($case, $actor, ['title' => 'Liberar montagem', 'dependency_id' => $first->id]);
        DB::table('production_tasks')->where('id', $first->id)->update(['dependency_id' => $second->id]);

        app(DecisionQueue::class)->openBlocker($case, $actor, [
            'title' => 'Resolver acesso',
            'reason' => 'A liberação depende de uma cadeia legada inconsistente.',
            'severity' => 'high',
            'importance' => 'high',
            'effort' => 'small',
            'production_task_id' => $first->id,
        ]);

        $item = app(DecisionQueue::class)->for($case)[0];

        $this->assertTrue($item['dependencyCycle']);
        $this->assertSame(1, $item['unlockedWork']);
    }
}
