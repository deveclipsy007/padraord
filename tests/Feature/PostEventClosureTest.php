<?php

namespace Tests\Feature;

use App\Models\Opportunity;
use App\Models\PostEventReport;
use App\Models\ProductionTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostEventClosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_closure_explains_unfinished_production_tasks_and_keeps_the_report_draft(): void
    {
        $user = User::factory()->create();
        $opportunity = Opportunity::create(['title' => 'Evento em andamento', 'client_name' => 'Cliente', 'stage' => 'post_event']);
        ProductionTask::create(['opportunity_id' => $opportunity->id, 'assigned_to' => $user->id, 'title' => 'Conferir comprovantes', 'status' => 'todo', 'priority' => 'normal']);

        $this->actingAs($user)->post("/opportunities/{$opportunity->id}/post-event", [
            'summary' => 'Resumo do evento.',
            'learnings' => 'Aprendizado do evento.',
            'actual_total' => '10000,00',
            'status' => 'closed',
        ])->assertRedirect()->assertSessionHasErrors('status');

        $this->assertDatabaseHas('post_event_reports', ['opportunity_id' => $opportunity->id, 'status' => 'draft']);
        $this->assertNotSame('closed', $opportunity->fresh()->stage?->value);
    }

    public function test_closure_records_the_actor_and_audit_after_all_tasks_are_done(): void
    {
        $user = User::factory()->create();
        $opportunity = Opportunity::create(['title' => 'Evento concluído', 'client_name' => 'Cliente', 'stage' => 'post_event']);
        ProductionTask::create(['opportunity_id' => $opportunity->id, 'assigned_to' => $user->id, 'title' => 'Conferir comprovantes', 'status' => 'done', 'priority' => 'normal']);

        $this->actingAs($user)->post("/opportunities/{$opportunity->id}/post-event", [
            'summary' => 'Resumo do evento.',
            'learnings' => 'Aprendizado do evento.',
            'actual_total' => '10000,00',
            'status' => 'closed',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $report = PostEventReport::firstOrFail();
        $this->assertSame('closed', $report->status);
        $this->assertSame($user->id, $report->closed_by);
        $this->assertNotNull($report->closed_at);
        $this->assertSame('closed', $opportunity->fresh()->stage?->value);
        $this->assertDatabaseHas('audit_logs', ['action' => 'post_event.closed', 'subject_id' => $report->id]);
    }

    public function test_occurrence_and_extra_are_appended_without_erasing_the_event_memory(): void
    {
        $user = User::factory()->create();
        $opportunity = Opportunity::create(['title' => 'Evento com ocorrência', 'client_name' => 'Cliente', 'stage' => 'post_event']);
        PostEventReport::create(['opportunity_id' => $opportunity->id, 'summary' => 'Resumo preservado.', 'learnings' => 'Aprendizado preservado.', 'occurrences' => [['description' => 'Registro anterior', 'solution' => 'Resolvido', 'extraCents' => 0]]]);

        $this->actingAs($user)->post("/opportunities/{$opportunity->id}/post-event", [
            'summary' => 'Resumo preservado.',
            'learnings' => 'Aprendizado preservado.',
            'occurrence_description' => 'Atraso de fornecedor',
            'occurrence_solution' => 'Substituição aprovada',
            'occurrence_extra_total' => '250,00',
            'status' => 'review',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $occurrences = PostEventReport::firstOrFail()->occurrences;
        $this->assertCount(2, $occurrences);
        $this->assertSame('Atraso de fornecedor', $occurrences[1]['description']);
        $this->assertSame(25000, $occurrences[1]['extraCents']);
    }
}
