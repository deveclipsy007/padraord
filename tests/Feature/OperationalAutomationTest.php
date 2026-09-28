<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\OperationalAutomations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OperationalAutomationTest extends TestCase
{
    use RefreshDatabase;

    private function pending(User $user): int
    {
        $case = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente', 'stage' => 'briefing', 'owner_id' => $user->id]);

        return DB::table('client_pending_items')->insertGetId(['opportunity_id' => $case->id, 'owner_id' => $user->id, 'title' => 'Confirmar equipe', 'kind' => 'answer', 'awaiting' => 'client', 'due_date' => today()->subDay(), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_activation_checks_preview_and_repeated_runs_create_one_task(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->pending($user);
        $this->actingAs($user)->post('/operations/rules/overdue/enable', ['enabled' => true, 'fingerprint' => 'old'])->assertSessionHasErrors('automation');
        $preview = app(OperationalAutomations::class)->preview('overdue');
        $this->post('/operations/rules/overdue/enable', ['enabled' => true, 'fingerprint' => $preview['fingerprint']])->assertRedirect();
        $this->post('/operations/rules/overdue/run')->assertRedirect();
        $this->post('/operations/rules/overdue/run')->assertRedirect();
        $this->assertDatabaseCount('activities', 1);
        $this->assertDatabaseCount('operational_rule_runs', 1);
        $run = DB::table('operational_rule_runs')->first();
        $this->post('/operations/runs/'.$run->id.'/undo')->assertRedirect();
        $this->assertDatabaseHas('activities', ['id' => $run->activity_id, 'status' => 'cancelled']);
        $this->post('/operations/rules/overdue/run')->assertRedirect();
        $this->assertDatabaseCount('activities', 1);
    }

    public function test_undo_preserves_modified_task_and_disabled_owner_stops_automation(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->pending($user);
        DB::table('operational_rules')->insert(['rule_key' => 'overdue', 'enabled' => true, 'enabled_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        $service = app(OperationalAutomations::class);
        $service->run('overdue');
        $run = DB::table('operational_rule_runs')->first();
        Activity::find($run->activity_id)->update(['title' => 'Trabalho revisado']);
        $this->actingAs($user)->post('/operations/runs/'.$run->id.'/undo')->assertSessionHasErrors('automation');
        $this->pending($user);
        $user->update(['is_active' => false]);
        $this->assertSame(0, $service->run('overdue'));
        $this->assertDatabaseCount('activities', 1);
    }

    public function test_producer_can_choose_focus_but_cannot_choose_management(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/workspace/focus', ['focus' => 'finance'])->assertRedirect();
        $this->assertSame('finance', $user->fresh()->workspace_focus);
        $this->post('/workspace/focus', ['focus' => 'management'])->assertForbidden();
    }
}
