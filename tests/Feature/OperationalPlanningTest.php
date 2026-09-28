<?php

namespace Tests\Feature;

use App\Models\Opportunity;
use App\Models\ProductionTask;
use App\Models\User;
use App\Services\OperationalControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalPlanningTest extends TestCase
{
    use RefreshDatabase;

    public function test_capacity_reports_overlap_without_double_counting_hours(): void
    {
        $this->travelTo(now()->startOfDay());
        $user = User::factory()->create();
        $case = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente', 'stage' => 'production']);
        foreach ([['08:00', '12:00'], ['10:00', '14:00']] as [$start,$end]) {
            ProductionTask::create(['opportunity_id' => $case->id, 'assigned_to' => $user->id, 'title' => 'Montagem', 'status' => 'todo', 'scheduled_starts_at' => today()->format('Y-m-d').' '.$start, 'scheduled_ends_at' => today()->format('Y-m-d').' '.$end]);
        }
        ProductionTask::create(['opportunity_id' => $case->id, 'assigned_to' => $user->id, 'title' => 'Sem horário', 'status' => 'todo']);
        $row = collect(app(OperationalControl::class)->capacity())->firstWhere('id', $user->id);
        $this->assertEquals(6, $row['hours']);
        $this->assertCount(1, $row['conflicts']);
        $this->assertSame(1, $row['unscheduled']);
    }

    public function test_stale_impact_preview_cannot_save_project_context(): void
    {
        $user = User::factory()->create();
        $case = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente', 'stage' => 'briefing', 'owner_id' => $user->id]);
        $fingerprint = app(OperationalControl::class)->impact($case, 'scope')['fingerprint'];
        ProductionTask::create(['opportunity_id' => $case->id, 'assigned_to' => $user->id, 'title' => 'Nova tarefa', 'status' => 'todo']);
        $this->actingAs($user)->patch('/opportunities/'.$case->id, ['title' => 'Evento', 'client_name' => 'Cliente', 'event_date' => '2026-12-01', 'impact_fingerprint' => $fingerprint])->assertSessionHasErrors('impact');
        $this->assertNull($case->fresh()->event_date);
    }

    public function test_project_control_and_operations_load_real_data(): void
    {
        $user = User::factory()->create();
        $case = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente', 'stage' => 'briefing']);
        $this->actingAs($user)->get('/opportunities/'.$case->id.'/control')->assertInertia(fn ($p) => $p->component('ProjectControl')->has('readiness', 7)->has('pending', 0));
        $this->get('/operations')->assertInertia(fn ($p) => $p->component('Operations')->has('rules', 2)->has('capacity', 1));
    }

    public function test_only_admin_can_configure_real_weekly_capacity(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $producer = User::factory()->create();
        $this->actingAs($producer)->post('/operations/capacity/'.$producer->id, ['hours' => 40])->assertForbidden();
        $this->actingAs($admin)->post('/operations/capacity/'.$producer->id, ['hours' => 40])->assertRedirect();
        $this->assertSame(2400, $producer->fresh()->weekly_capacity_minutes);
        $row = collect(app(OperationalControl::class)->capacity())->firstWhere('id', $producer->id);
        $this->assertEquals(40, $row['capacityHours']);
        $this->post('/operations/capacity/'.$producer->id, ['hours' => 0])->assertSessionHasErrors('hours');
    }
}
