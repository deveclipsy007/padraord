<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\AssistantPreview;
use App\Models\Budget;
use App\Models\Document;
use App\Models\Opportunity;
use App\Models\ProductionTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_exposes_metrics_from_real_operational_records(): void
    {
        $user = User::factory()->create(['name' => 'Produtor']);
        $other = User::factory()->create(['name' => 'Outra pessoa']);
        $active = Opportunity::create([
            'title' => 'Caso ativo',
            'client_name' => 'Cliente ativo',
            'stage' => 'lead',
            'briefing_status' => 'awaiting_review',
            'owner_id' => $user->id,
        ]);
        Opportunity::create(['title' => 'Caso perdido', 'client_name' => 'Cliente antigo', 'stage' => 'lost']);

        Activity::create([
            'opportunity_id' => $active->id,
            'user_id' => $user->id,
            'title' => 'Cobrar retorno',
            'type' => 'follow_up',
            'priority' => 'high',
            'due_at' => now()->subHour(),
            'status' => 'pending',
        ]);
        ProductionTask::create([
            'opportunity_id' => $active->id,
            'assigned_to' => $other->id,
            'title' => 'Revisar medidas',
            'status' => 'todo',
            'priority' => 'normal',
            'due_date' => today()->subDay(),
        ]);
        Budget::create(['opportunity_id' => $active->id, 'version' => 1, 'status' => 'review']);
        Document::create(['opportunity_id' => $active->id, 'type' => 'proposal', 'version' => 1, 'status' => 'review', 'title' => 'Proposta']);
        AssistantPreview::create([
            'user_id' => $user->id,
            'mode' => 'demo',
            'message' => 'Organizar contexto',
            'context' => ['opportunity_id' => $active->id],
            'actions' => [],
            'status' => 'preview',
        ]);

        $this->actingAs($user)->get('/history')->assertInertia(fn ($page) => $page
            ->where('metrics.activeCases', 1)
            ->where('metrics.openProductionTasks', 1)
            ->where('metrics.overdueWork', 2)
            ->where('metrics.pendingReviews', 4)
            ->where('metrics.measured', true)
            ->has('metrics.workload', 2));
    }
}
