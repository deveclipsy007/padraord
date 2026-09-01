<?php

namespace Tests\Feature;

use App\AI\AiProvider;
use App\Models\AiSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiConsumptionTest extends TestCase
{
    use RefreshDatabase;

    private function configured(): void
    {
        AiSetting::create(['id' => 1, 'mode' => 'openai', 'credential_source' => 'settings', 'api_key' => 'fake-secret', 'policy_approved' => true, 'monthly_micros' => 1000000, 'processing_micros' => 100000, 'input_price' => 150000, 'output_price' => 600000]);
    }

    public function test_repeated_input_is_billed_once_and_reuses_valid_result(): void
    {
        $this->configured();
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => json_encode(['summary' => 'Reunião', 'facts' => [], 'missing_questions' => [], 'risks' => [], 'suggested_changes' => []])]]], 'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 100]])]);
        $a = app(AiProvider::class)->analyzeBriefing('Contexto fictício', ['case_id' => 1]);
        $b = app(AiProvider::class)->analyzeBriefing('Contexto fictício', ['case_id' => 1]);
        $this->assertTrue($a->succeeded());
        $this->assertSame($a->payload, $b->payload);
        Http::assertSentCount(1);
        $this->assertSame(75, (int) DB::table('ai_consumptions')->value('charged_micros'));
    }

    public function test_reserved_or_uncertain_attempt_is_not_repeated(): void
    {
        $this->configured();
        Http::fake(['api.openai.com/*' => Http::response([], 500)]);
        $this->assertFalse(app(AiProvider::class)->analyzeBriefing('Falha incerta')->succeeded());
        $this->assertFalse(app(AiProvider::class)->analyzeBriefing('Falha incerta')->succeeded());
        Http::assertSentCount(1);
        $this->assertNull(DB::table('ai_consumptions')->value('charged_micros'));
    }

    public function test_processing_limit_blocks_before_external_request(): void
    {
        $this->configured();
        AiSetting::find(1)->update(['processing_micros' => 1]);
        Http::fake();
        $this->assertFalse(app(AiProvider::class)->analyzeBriefing('Dados')->succeeded());
        Http::assertNothingSent();
    }

    public function test_outstanding_reservations_count_against_monthly_limit(): void
    {
        $this->configured();
        DB::table('ai_consumptions')->insert(['request_key' => str_repeat('a', 64), 'month' => now()->format('Y-m'), 'action' => 'pending', 'status' => 'reserved', 'reserved_micros' => 999999, 'input_price' => 150000, 'output_price' => 600000, 'created_at' => now(), 'updated_at' => now()]);
        Http::fake();
        $this->assertFalse(app(AiProvider::class)->analyzeBriefing('Outra reunião')->succeeded());
        Http::assertNothingSent();
    }

    public function test_long_input_is_chunked_and_replayed_without_new_calls(): void
    {
        $this->configured();
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => json_encode(['summary' => 'Resumo consolidado', 'facts' => [], 'missing_questions' => [], 'risks' => [], 'suggested_changes' => []])]]], 'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 100]])]);
        $text = str_repeat('Contexto da reunião. ', 2000);
        $this->assertTrue(app(AiProvider::class)->analyzeBriefing($text)->succeeded());
        $this->assertTrue(app(AiProvider::class)->analyzeBriefing($text)->succeeded());
        Http::assertSentCount(2);
        $this->assertSame(150, (int) DB::table('ai_consumptions')->value('charged_micros'));
    }
}
