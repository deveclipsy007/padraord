<?php

namespace Tests\Feature;

use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContextAudioSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_audio_pipeline_cost_and_odoo_outbox_schema_is_portable_and_idempotent(): void
    {
        $this->assertTrue(Schema::hasColumns('audio_upload_sessions', [
            'uuid', 'opportunity_id', 'user_id', 'expected_bytes', 'received_bytes',
            'expected_chunks', 'received_chunks', 'temporary_path', 'status', 'expires_at',
        ]));
        $this->assertTrue(Schema::hasColumns('context_audio_assets', [
            'case_context_entry_id', 'original_path', 'prepared_path', 'digest',
            'original_bytes', 'prepared_bytes', 'duration_ms', 'status', 'provider_request_id',
        ]));
        $this->assertTrue(Schema::hasColumns('ai_cost_entries', [
            'operation', 'provider', 'model', 'estimated_amount_micros',
            'reported_amount_micros', 'reconciled_amount_micros', 'currency',
            'idempotency_key', 'status',
        ]));
        $this->assertTrue(Schema::hasColumns('odoo_cost_outbox', [
            'idempotency_key', 'payload', 'status', 'attempts', 'next_attempt_at',
            'external_id', 'last_error',
        ]));

        $user = User::factory()->create();
        $opportunity = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente', 'stage' => 'briefing']);
        $base = [
            'uuid' => 'f285dba8-4190-4bc4-9910-b7924cf04070',
            'opportunity_id' => $opportunity->id,
            'user_id' => $user->id,
            'original_name' => 'reuniao.m4a',
            'declared_mime' => 'audio/mp4',
            'expected_bytes' => 100,
            'expected_chunks' => 1,
            'temporary_path' => 'context-audio/tmp/f285dba8',
            'status' => 'uploading',
            'expires_at' => now()->addHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
        DB::table('audio_upload_sessions')->insert($base);

        $this->expectException(QueryException::class);
        DB::table('audio_upload_sessions')->insert($base);
    }
}
