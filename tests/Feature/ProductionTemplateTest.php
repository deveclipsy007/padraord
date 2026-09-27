<?php

namespace Tests\Feature;

use App\Models\Opportunity;
use App\Models\User;
use App\Services\ProductionOperations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_event_can_be_saved_as_a_template_and_applied_once_to_another_event(): void
    {
        $user = User::factory()->create();
        $source = Opportunity::create(['title' => 'Evento realizado', 'client_name' => 'Cliente A', 'stage' => 'closed']);
        $target = Opportunity::create(['title' => 'Próximo evento', 'client_name' => 'Cliente B', 'stage' => 'pre_production']);
        $task = app(ProductionOperations::class)->createTask($source, $user, ['title' => 'Montar estrutura', 'phase' => 'setup', 'priority' => 'high']);
        app(ProductionOperations::class)->transition($task, $user, 'done');

        $this->actingAs($user)->post("/production/events/{$source->id}/templates", ['name' => 'Evento corporativo'])->assertRedirect();
        $templateId = (int) \DB::table('production_task_templates')->value('id');
        $this->actingAs($user)->get("/production/events/{$target->id}")->assertInertia(fn ($page) => $page
            ->where('templates.0.name', 'Evento corporativo')
            ->where('templates.0.applied', false));
        $this->actingAs($user)->post("/production/events/{$target->id}/templates/{$templateId}/apply")->assertRedirect();
        $this->assertDatabaseHas('production_tasks', ['opportunity_id' => $target->id, 'title' => 'Montar estrutura', 'status' => 'todo']);
        $this->actingAs($user)->post("/production/events/{$target->id}/templates/{$templateId}/apply")->assertSessionHasErrors('template');
        $this->assertSame(1, $target->productionTasks()->count());
    }

    public function test_event_with_unfinished_tasks_cannot_be_published_as_template(): void
    {
        $user = User::factory()->create();
        $source = Opportunity::create(['title' => 'Ainda em curso', 'client_name' => 'Cliente', 'stage' => 'production']);
        app(ProductionOperations::class)->createTask($source, $user, ['title' => 'Finalizar palco']);

        $this->actingAs($user)->post("/production/events/{$source->id}/templates", ['name' => 'Rascunho'])->assertSessionHasErrors('source');
        $this->assertDatabaseCount('production_task_templates', 0);
    }
}
