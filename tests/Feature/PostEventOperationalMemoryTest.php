<?php

namespace Tests\Feature;

use App\Models\Opportunity;
use App\Models\PostEventReport;
use App\Models\ProductionTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostEventOperationalMemoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_closure_requires_pending_items_to_be_resolved_or_formally_assigned(): void
    {
        $user = User::factory()->create();
        $case = Opportunity::create(['title' => 'Evento com pendência', 'client_name' => 'Cliente', 'stage' => 'post_event']);
        ProductionTask::create(['opportunity_id' => $case->id, 'assigned_to' => $user->id, 'title' => 'Conferir comprovantes', 'status' => 'done', 'priority' => 'normal']);

        $this->actingAs($user)->post("/opportunities/{$case->id}/post-event", [
            'summary' => 'Resumo.',
            'learnings' => 'Aprendizado.',
            'planned_total' => '1000,00',
            'actual_total' => '1200,00',
            'closure_items' => [['title' => 'Enviar nota fiscal', 'status' => 'pending']],
            'status' => 'closed',
        ])->assertRedirect()->assertSessionHasErrors('status');

        $this->assertDatabaseHas('post_event_reports', ['opportunity_id' => $case->id, 'status' => 'draft']);
    }

    public function test_closure_persists_plan_actual_evaluation_and_idempotently_reopens(): void
    {
        $user = User::factory()->create();
        $case = Opportunity::create(['title' => 'Evento encerrado', 'client_name' => 'Cliente', 'stage' => 'post_event']);
        ProductionTask::create(['opportunity_id' => $case->id, 'assigned_to' => $user->id, 'title' => 'Conferir comprovantes', 'status' => 'done', 'priority' => 'normal']);
        $payload = [
            'summary' => 'Resumo.',
            'learnings' => 'Aprendizado.',
            'planned_total' => '1000,00',
            'actual_total' => '1200,00',
            'supplier_evaluations' => [['supplier' => 'Luz Norte', 'rating' => 4, 'notes' => 'Pontual']],
            'closure_items' => [['title' => 'Enviar nota fiscal', 'status' => 'assigned', 'assigned_to' => $user->id, 'due_at' => now()->addDay()->toDateString(), 'justification' => 'Financeiro concluirá amanhã.']],
            'status' => 'closed',
        ];

        $this->actingAs($user)->post("/opportunities/{$case->id}/post-event", $payload)->assertRedirect()->assertSessionHasNoErrors();
        $report = PostEventReport::firstOrFail();
        $this->assertSame(100000, $report->planned_total_cents);
        $this->assertSame(120000, $report->actual_total_cents);
        $this->assertSame('Luz Norte', $report->supplier_evaluations[0]['supplier']);

        $this->actingAs($user)->post("/opportunities/{$case->id}/post-event", $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('post_event_reports', 1);

        $this->actingAs($user)->post("/opportunities/{$case->id}/post-event/reopen", ['reason' => 'Reabrir para anexar comprovante.'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('post_event_reports', ['id' => $report->id, 'status' => 'review', 'closed_at' => null]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'post_event.reopened', 'subject_id' => $report->id]);
    }

    public function test_closed_memory_rejects_changed_values_until_explicit_reopen(): void
    {
        $user = User::factory()->create();
        $case = Opportunity::create(['title' => 'Evento imutável', 'client_name' => 'Cliente', 'stage' => 'post_event']);
        ProductionTask::create(['opportunity_id' => $case->id, 'assigned_to' => $user->id, 'title' => 'Conferir comprovantes', 'status' => 'done', 'priority' => 'normal']);
        $base = ['summary' => 'Resumo original.', 'learnings' => 'Aprendizado original.', 'planned_total' => '1000,00', 'actual_total' => '1000,00', 'status' => 'closed'];
        $this->actingAs($user)->post("/opportunities/{$case->id}/post-event", $base)->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($user)->post("/opportunities/{$case->id}/post-event", array_merge($base, ['actual_total' => '1100,00']))->assertRedirect()->assertSessionHasErrors('status');
        $this->assertDatabaseHas('post_event_reports', ['opportunity_id' => $case->id, 'actual_total_cents' => 100000]);
    }
}
