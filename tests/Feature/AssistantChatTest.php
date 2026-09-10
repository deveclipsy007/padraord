<?php

namespace Tests\Feature;

use App\Models\AiSetting;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class AssistantChatTest extends TestCase
{
    use RefreshDatabase;

    private function enable(): void
    {
        AiSetting::create(['id' => 1, 'mode' => 'openai', 'credential_source' => 'settings', 'api_key' => 'test-secret',
            'policy_approved' => true, 'monthly_micros' => 10000000, 'processing_micros' => 1000000,
            'input_price' => 150000, 'output_price' => 600000]);
    }

    private function payload(string $message = 'Crie uma tarefa para cobrar retorno'): array
    {
        return ['request_id' => (string) Str::uuid(), 'message' => $message];
    }

    private function fake(array $override = []): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => json_encode(array_replace([
            'reply' => 'Preparei a tarefa. Confira antes de confirmar.',
            'actions' => [['kind' => 'task.create', 'fields' => [['key' => 'title', 'value' => 'Cobrar retorno']]]],
        ], $override))]]], 'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 100]])]);
    }

    public function test_chat_persists_and_requires_confirmation_without_duplicate_cost_or_action(): void
    {
        $this->enable();
        $this->fake();
        $this->actingAs(User::factory()->create());
        $input = $this->payload();
        $response = $this->postJson('/assistant/chat', $input)->assertOk()->assertJsonPath('model', 'gpt-5.6-luna');
        $preview = $response->json('preview.id');
        $this->assertDatabaseCount('activities', 0);
        $this->postJson('/assistant/chat', $input)->assertOk()->assertJsonPath('preview.id', $preview);
        Http::assertSentCount(1);
        $this->assertDatabaseCount('ai_consumptions', 1);
        $this->getJson('/assistant/chat')->assertOk()->assertJsonCount(1, 'turns')->assertDontSee('test-secret');
        $this->postJson("/assistant/previews/$preview/confirm")->assertOk();
        $this->postJson("/assistant/previews/$preview/confirm")->assertOk();
        $this->assertDatabaseCount('activities', 1);
        Http::assertSent(fn ($r) => $r['model'] === 'gpt-5.6-luna');
    }

    public function test_history_is_private_to_each_user(): void
    {
        config(['ai.mode' => 'manual']);
        $this->actingAs(User::factory()->create())->postJson('/assistant/chat', $this->payload('Meu contexto privado'))->assertOk();
        $this->actingAs(User::factory()->create())->getJson('/assistant/chat')->assertJsonCount(0, 'turns')->assertDontSee('Meu contexto privado');
    }

    public function test_editing_preview_preserves_history_and_invalidates_previous_confirmation(): void
    {
        $this->enable();
        $this->fake();
        $this->actingAs(User::factory()->create());
        $turn = $this->postJson('/assistant/chat', $this->payload())->json();
        $edited = $this->postJson('/assistant/chat/'.$turn['id'].'/preview', ['preview_id' => $turn['preview']['id'],
            'actions' => [['kind' => 'task.create', 'data' => ['title' => 'Cobrar orçamento revisado']]]])->assertOk()->json();
        $this->getJson('/assistant/chat')->assertJsonPath('turns.0.preview.id', $edited['id']);
        $this->postJson('/assistant/previews/'.$turn['preview']['id'].'/confirm')->assertUnprocessable();
        $this->postJson('/assistant/previews/'.$edited['id'].'/confirm')->assertOk();
        $this->assertDatabaseHas('activities', ['title' => 'Cobrar orçamento revisado']);
    }

    public function test_manual_mode_is_explicit_and_does_not_call_openai(): void
    {
        config(['ai.mode' => 'manual']);
        Http::fake();
        $this->actingAs(User::factory()->create())->postJson('/assistant/chat', $this->payload())->assertOk()->assertJsonPath('mode', 'manual')->assertJsonPath('preview', null);
        Http::assertNothingSent();
    }

    public function test_limits_and_policy_prevent_paid_call(): void
    {
        $this->enable();
        AiSetting::find(1)->update(['policy_approved' => false]);
        Http::fake();
        $this->actingAs(User::factory()->create())->postJson('/assistant/chat', $this->payload())->assertUnprocessable();
        AiSetting::find(1)->update(['policy_approved' => true, 'processing_micros' => 1]);
        $this->postJson('/assistant/chat', $this->payload())->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_unsafe_actions_are_rejected_and_usage_is_still_accounted(): void
    {
        $this->enable();
        $this->fake(['actions' => [['kind' => 'budget.approve', 'fields' => []]]]);
        $this->actingAs(User::factory()->create())->postJson('/assistant/chat', $this->payload())->assertUnprocessable();
        $this->assertDatabaseCount('assistant_previews', 0);
        $this->assertDatabaseHas('ai_consumptions', ['charged_micros' => 140]);
    }

    public function test_uncertain_request_is_not_retried_and_original_message_is_retained(): void
    {
        $this->enable();
        Http::fake(['api.openai.com/*' => Http::response([], 503)]);
        $this->actingAs(User::factory()->create());
        $input = $this->payload();
        $this->postJson('/assistant/chat', $input)->assertUnprocessable();
        $this->postJson('/assistant/chat', $input)->assertUnprocessable();
        Http::assertSentCount(1);
        $this->getJson('/assistant/chat')->assertJsonPath('turns.0.message', $input['message']);
    }

    public function test_case_change_during_response_does_not_produce_a_fresh_looking_preview(): void
    {
        $this->enable();
        $case = Opportunity::create(['title' => 'Caso', 'client_name' => 'Cliente', 'stage' => 'lead']);
        Http::fake(function () use ($case) {
            $case->update(['next_action' => 'Alterada por outra pessoa']);

            return Http::response(['choices' => [['message' => ['content' => json_encode(['reply' => 'Preparei.', 'actions' => [['kind' => 'task.create', 'fields' => [['key' => 'title', 'value' => 'Conferir']]]]])]]], 'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 100]]);
        });
        $this->actingAs(User::factory()->create())->postJson('/assistant/chat', $this->payload() + ['opportunity_id' => $case->id])->assertUnprocessable();
        $this->assertDatabaseCount('assistant_previews', 0);
    }
}
