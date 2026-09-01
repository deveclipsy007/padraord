<?php

namespace Tests\Feature;

use App\Models\AiSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ActionPlannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_planning_is_metered_cached_and_never_creates_supplier(): void
    {
        AiSetting::create(['id' => 1, 'mode' => 'openai', 'credential_source' => 'settings', 'api_key' => 'fake-key', 'policy_approved' => true, 'monthly_micros' => 10000000, 'processing_micros' => 1000000, 'input_price' => 150000, 'output_price' => 600000, 'retention_days' => 30]);
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => json_encode(['fields' => [['key' => 'name', 'value' => 'Luz Horizonte']], 'questions' => ['Qual o contato?']])]]], 'usage' => ['prompt_tokens' => 60, 'completion_tokens' => 30]])]);
        $this->actingAs(User::factory()->create());
        $data = ['message' => 'Cadastre Luz Horizonte', 'kind' => 'supplier.create'];
        $this->postJson('/assistant/interpret', $data)->assertOk()->assertJsonPath('data.name', 'Luz Horizonte');
        $this->postJson('/assistant/interpret', $data)->assertOk();
        Http::assertSentCount(1);
        $this->assertDatabaseCount('suppliers', 0);
        $this->assertDatabaseCount('ai_consumptions', 1);
    }

    public function test_manual_mode_does_not_call_external_api(): void
    {
        Http::fake();
        config(['ai.mode' => 'manual']);
        $this->actingAs(User::factory()->create())->postJson('/assistant/interpret', ['message' => 'Teste', 'kind' => 'supplier.create'])->assertUnprocessable();
        Http::assertNothingSent();
    }
}
