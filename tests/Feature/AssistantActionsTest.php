<?php

namespace Tests\Feature;

use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistantActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_does_not_write_and_confirmation_is_idempotent(): void
    {
        config(['ai.mode' => 'manual']);
        $this->actingAs(User::factory()->create());
        $r = $this->postJson('/assistant/messages', ['message' => 'Cadastre a Luz', 'actions' => [['kind' => 'supplier.create', 'data' => ['name' => 'Luz']]]])->assertCreated();
        $id = $r->json('id');
        $this->assertDatabaseCount('suppliers', 0);
        $this->postJson("/assistant/previews/$id/confirm")->assertOk();
        $this->postJson("/assistant/previews/$id/confirm")->assertOk();
        $this->assertDatabaseCount('suppliers', 1);
    }

    public function test_other_user_cannot_confirm_and_unsafe_action_is_rejected(): void
    {
        config(['ai.mode' => 'manual']);
        $this->actingAs(User::factory()->create());
        $this->postJson('/assistant/messages', ['message' => 'Aprovar preço', 'actions' => [['kind' => 'budget.approve', 'data' => []]]])->assertUnprocessable();
        $id = $this->postJson('/assistant/messages', ['message' => 'Cadastro', 'actions' => [['kind' => 'supplier.create', 'data' => ['name' => 'Luz']]]])->json('id');
        $this->actingAs(User::factory()->create())->postJson("/assistant/previews/$id/confirm")->assertForbidden();
    }

    public function test_changed_case_blocks_preview_and_demo_never_creates_domain_records(): void
    {
        config(['ai.mode' => 'manual']);
        $o = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente', 'stage' => 'briefing']);
        $this->actingAs(User::factory()->create());
        $id = $this->postJson('/assistant/messages', ['message' => 'Tarefa', 'opportunity_id' => $o->id, 'actions' => [['kind' => 'task.create', 'data' => ['title' => 'Cobrar retorno']]]])->json('id');
        $o->increment('briefing_revision');
        $this->postJson("/assistant/previews/$id/confirm")->assertUnprocessable();
        $this->assertDatabaseCount('activities', 0);
        config(['ai.mode' => 'demo']);
        $id = $this->postJson('/assistant/messages', ['message' => 'Fornecedor: Luz', 'actions' => [['kind' => 'supplier.create', 'data' => ['name' => 'Luz']]]])->json('id');
        $this->postJson("/assistant/previews/$id/confirm")->assertOk();
        $this->assertDatabaseCount('suppliers', 0);
    }
}
