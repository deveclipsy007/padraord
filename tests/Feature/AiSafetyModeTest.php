<?php

namespace Tests\Feature;

use App\AI\AiProvider;
use App\AI\DemoAiProvider;
use App\AI\NullAiProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiSafetyModeTest extends TestCase
{
    public function test_openai_requires_explicit_data_policy_even_with_a_key(): void
    {
        config(['ai.mode' => 'openai', 'ai.data_policy_approved' => false, 'services.openai.api_key' => 'test-only']);
        Http::fake();
        $provider = app(AiProvider::class);
        $this->assertInstanceOf(NullAiProvider::class, $provider);
        $this->assertFalse($provider->analyzeBriefing('Contexto privado')->succeeded());
        Http::assertNothingSent();
    }

    public function test_demo_is_deterministic_and_never_sends_context(): void
    {
        config(['ai.mode' => 'demo']);
        Http::fake();
        $provider = app(AiProvider::class);
        $this->assertInstanceOf(DemoAiProvider::class, $provider);
        $one = $provider->analyzeBriefing('Encontro de parceiros');
        $this->assertTrue($one->succeeded());
        $this->assertSame($one->payload, $provider->analyzeBriefing('Encontro de parceiros')->payload);
        $this->assertSame('demo', $one->provider);
        Http::assertNothingSent();
    }

    public function test_manual_never_calls_provider_and_queue_reservation_exceeds_job_timeout(): void
    {
        config(['ai.mode' => 'manual', 'services.openai.api_key' => 'test-only']);
        $this->assertInstanceOf(NullAiProvider::class, app(AiProvider::class));
        $this->assertGreaterThan(180, config('queue.connections.database.retry_after'));
    }
}
