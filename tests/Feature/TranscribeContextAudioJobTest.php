<?php

namespace Tests\Feature;

use App\Contracts\AudioTranscriber;
use App\Jobs\ExtractContextIntelligence;
use App\Jobs\TranscribeContextAudio;
use App\Models\AiSetting;
use App\Models\CaseContextEntry;
use App\Models\ContextAudioAsset;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TranscribeContextAudioJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_prepared_audio_is_diarized_once_and_segments_are_persisted_in_order(): void
    {
        Storage::fake('local');
        Queue::fake();
        config(['ai.audio_price_micros_per_minute' => 6000]);
        AiSetting::create([
            'id' => 1, 'mode' => 'openai', 'credential_source' => 'settings', 'api_key' => 'secret',
            'policy_approved' => true, 'monthly_micros' => 10_000_000, 'processing_micros' => 2_000_000,
            'input_price' => 200_000, 'output_price' => 1_200_000, 'audio_enabled' => true, 'audio_price_micros_per_minute' => 6000,
        ]);
        Http::fake(['api.openai.com/v1/audio/transcriptions' => Http::response([
            'text' => 'Olá. Vamos fazer o evento em novembro.',
            'segments' => [
                ['id' => 'seg-1', 'speaker' => 'A', 'start' => 0.0, 'end' => 1.2, 'text' => 'Olá.'],
                ['id' => 'seg-2', 'speaker' => 'B', 'start' => 1.3, 'end' => 4.5, 'text' => 'Vamos fazer o evento em novembro.'],
            ],
        ], 200, ['x-request-id' => 'req_audio_123'])]);
        $opportunity = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente', 'stage' => 'briefing']);
        $entry = CaseContextEntry::create([
            'opportunity_id' => $opportunity->id, 'user_id' => User::factory()->create()->id,
            'kind' => 'audio', 'phase' => 'briefing', 'status' => 'prepared', 'path' => 'context-audio/original/audio.wav',
            'digest' => str_repeat('c', 64),
        ]);
        Storage::disk('local')->put($entry->path, 'audio-bytes');
        $asset = ContextAudioAsset::create([
            'case_context_entry_id' => $entry->id, 'original_path' => $entry->path, 'prepared_path' => $entry->path,
            'original_mime' => 'audio/wav', 'prepared_mime' => 'audio/wav', 'original_bytes' => 11,
            'prepared_bytes' => 11, 'duration_ms' => 5000, 'digest' => str_repeat('c', 64), 'status' => 'prepared',
        ]);

        $job = new TranscribeContextAudio($asset->id);
        $job->handle(app(AudioTranscriber::class));
        $job->handle(app(AudioTranscriber::class));

        $this->assertSame('transcribed', $asset->fresh()->status);
        $this->assertSame('transcribed', $entry->fresh()->status);
        $this->assertDatabaseHas('context_audio_assets', ['id' => $asset->id, 'provider_request_id' => 'req_audio_123']);
        $this->assertDatabaseHas('case_context_segments', ['case_context_entry_id' => $entry->id, 'sequence' => 0, 'speaker_key' => 'A', 'text' => 'Olá.']);
        $this->assertDatabaseHas('case_context_segments', ['case_context_entry_id' => $entry->id, 'sequence' => 1, 'speaker_key' => 'B']);
        $this->assertDatabaseCount('case_context_segments', 2);
        $this->assertDatabaseHas('ai_cost_entries', ['case_context_entry_id' => $entry->id, 'operation' => 'transcription', 'estimated_amount_micros' => 6000]);
        $this->assertDatabaseCount('ai_cost_entries', 1);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/audio/transcriptions' && $request->hasHeader('Authorization', 'Bearer secret'));
        Queue::assertPushed(ExtractContextIntelligence::class, fn ($queued) => $queued->entryId === $entry->id);
    }

    public function test_cost_limit_preserves_audio_and_returns_it_to_a_retryable_waiting_state(): void
    {
        Storage::fake('local');
        config(['ai.audio_price_micros_per_minute' => 6000]);
        AiSetting::create([
            'id' => 1, 'mode' => 'openai', 'credential_source' => 'settings', 'api_key' => 'secret',
            'policy_approved' => true, 'monthly_micros' => 100, 'processing_micros' => 100,
            'input_price' => 200_000, 'output_price' => 1_200_000, 'audio_enabled' => true, 'audio_price_micros_per_minute' => 6000,
        ]);
        $opportunity = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente', 'stage' => 'briefing']);
        $entry = CaseContextEntry::create([
            'opportunity_id' => $opportunity->id, 'user_id' => User::factory()->create()->id,
            'kind' => 'audio', 'phase' => 'briefing', 'status' => 'prepared', 'path' => 'context-audio/original/limit.wav',
            'digest' => str_repeat('e', 64),
        ]);
        Storage::disk('local')->put($entry->path, 'audio-bytes');
        $asset = ContextAudioAsset::create([
            'case_context_entry_id' => $entry->id, 'original_path' => $entry->path, 'prepared_path' => $entry->path,
            'original_mime' => 'audio/wav', 'prepared_mime' => 'audio/wav', 'original_bytes' => 11,
            'prepared_bytes' => 11, 'duration_ms' => 5000, 'digest' => str_repeat('e', 64), 'status' => 'prepared',
        ]);

        (new TranscribeContextAudio($asset->id))->handle(app(AudioTranscriber::class));

        $this->assertSame('waiting', $asset->fresh()->status);
        $this->assertSame('cost_limit', $asset->fresh()->error_code);
        $this->assertSame('waiting', $entry->fresh()->status);
        $this->assertDatabaseCount('ai_cost_entries', 0);
        Http::assertNothingSent();
    }
}
