<?php

namespace Tests\Feature;

use App\Models\AiSetting;
use App\Services\AiCostLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AiCostLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_reservation_is_idempotent_and_reported_cost_is_kept_separate_from_estimate(): void
    {
        AiSetting::create([
            'id' => 1, 'mode' => 'openai', 'credential_source' => 'settings', 'api_key' => 'secret',
            'policy_approved' => true, 'monthly_micros' => 1_000_000, 'processing_micros' => 500_000,
            'input_price' => 200_000, 'output_price' => 1_200_000,
        ]);
        $ledger = app(AiCostLedger::class);
        $first = $ledger->reserve([
            'operation' => 'transcription', 'provider' => 'openai', 'model' => 'gpt-4o-transcribe-diarize',
            'idempotency_key' => hash('sha256', 'meeting-1-transcription'), 'estimated_amount_micros' => 120_000,
            'pricing_version' => '2026-08-31', 'audio_seconds' => 3600,
        ]);
        $second = $ledger->reserve([
            'operation' => 'transcription', 'provider' => 'openai', 'model' => 'gpt-4o-transcribe-diarize',
            'idempotency_key' => hash('sha256', 'meeting-1-transcription'), 'estimated_amount_micros' => 120_000,
            'pricing_version' => '2026-08-31', 'audio_seconds' => 3600,
        ]);
        $this->assertSame($first->id, $second->id);
        $ledger->report($first, ['audio_seconds' => 3600], 95_000, 'req_123');

        $first->refresh();
        $this->assertSame(120_000, $first->estimated_amount_micros);
        $this->assertSame(95_000, $first->reported_amount_micros);
        $this->assertSame('reported', $first->status);
        $this->assertDatabaseCount('ai_cost_entries', 1);
    }

    public function test_outstanding_reservations_block_new_paid_work_before_the_limit_is_exceeded(): void
    {
        AiSetting::create([
            'id' => 1, 'mode' => 'openai', 'credential_source' => 'settings', 'api_key' => 'secret',
            'policy_approved' => true, 'monthly_micros' => 150_000, 'processing_micros' => 100_000,
            'input_price' => 200_000, 'output_price' => 1_200_000,
        ]);
        $ledger = app(AiCostLedger::class);
        $ledger->reserve(['operation' => 'transcription', 'provider' => 'openai', 'model' => 'audio', 'idempotency_key' => hash('sha256', 'one'), 'estimated_amount_micros' => 90_000, 'pricing_version' => 'v1']);

        $this->expectException(ValidationException::class);
        $ledger->reserve(['operation' => 'extraction', 'provider' => 'openai', 'model' => 'text', 'idempotency_key' => hash('sha256', 'two'), 'estimated_amount_micros' => 70_000, 'pricing_version' => 'v1']);
    }
}
