<?php

namespace Tests\Feature;

use App\Jobs\ExtractContextIntelligence;
use App\Jobs\PrepareContextAudio;
use App\Models\AiSetting;
use App\Models\CaseContextEntry;
use App\Models\CaseContextSegment;
use App\Models\ContextAudioAsset;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContextAudioReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_participants_and_transcript_can_be_corrected_with_optimistic_locking(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $opportunity = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente', 'stage' => 'briefing']);
        $entry = CaseContextEntry::create([
            'opportunity_id' => $opportunity->id, 'user_id' => $user->id, 'kind' => 'audio', 'phase' => 'briefing',
            'status' => 'review_ready', 'path' => 'context-audio/original/audio.wav', 'digest' => str_repeat('f', 64), 'revision' => 0,
        ]);
        Storage::disk('local')->put($entry->path, 'private-audio');
        ContextAudioAsset::create([
            'case_context_entry_id' => $entry->id, 'original_path' => $entry->path, 'original_mime' => 'audio/wav',
            'original_bytes' => 13, 'duration_ms' => 3000, 'digest' => str_repeat('f', 64), 'status' => 'review_ready',
        ]);
        $segment = CaseContextSegment::create([
            'case_context_entry_id' => $entry->id, 'sequence' => 0, 'speaker_key' => 'A',
            'start_ms' => 0, 'end_ms' => 3000, 'text' => 'Texto original.',
        ]);

        $this->actingAs($user)->get("/opportunities/{$opportunity->id}/context/{$entry->id}/audio")
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->patchJson("/opportunities/{$opportunity->id}/context/{$entry->id}/speakers", [
            'revision' => 0,
            'speakers' => ['A' => 'Rômulo'],
            'segments' => [['id' => $segment->id, 'text' => 'Texto corrigido.']],
        ])->assertOk()->assertJsonPath('entry.revision', 1);

        $this->assertDatabaseHas('case_context_segments', ['id' => $segment->id, 'speaker_name' => 'Rômulo', 'text' => 'Texto corrigido.']);
        $this->getJson("/opportunities/{$opportunity->id}/context/{$entry->id}")
            ->assertOk()
            ->assertJsonPath('entry.segments.0.speaker_name', 'Rômulo')
            ->assertJsonPath('entry.segments.0.text', 'Texto corrigido.')
            ->assertJsonMissingPath('entry.audio.original_path');
        $this->patchJson("/opportunities/{$opportunity->id}/context/{$entry->id}/speakers", [
            'revision' => 0, 'speakers' => ['A' => 'Outro'], 'segments' => [['id' => $segment->id, 'text' => 'Conflito']],
        ])->assertUnprocessable()->assertJsonValidationErrors('audio');
    }

    public function test_preserved_audio_can_be_started_after_ai_configuration_becomes_ready(): void
    {
        Storage::fake('local');
        Queue::fake();
        config(['ai.audio_validated' => true, 'ai.audio_price_micros_per_minute' => 6000]);
        AiSetting::create(['id' => 1, 'mode' => 'openai', 'credential_source' => 'settings', 'api_key' => 'secret', 'policy_approved' => true, 'monthly_micros' => 1_000_000, 'processing_micros' => 100_000, 'input_price' => 200_000, 'output_price' => 1_200_000]);
        $opportunity = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente', 'stage' => 'briefing']);
        $entry = CaseContextEntry::create(['opportunity_id' => $opportunity->id, 'user_id' => User::factory()->create()->id, 'kind' => 'audio', 'phase' => 'briefing', 'status' => 'waiting', 'path' => 'context-audio/original/audio.wav', 'digest' => str_repeat('1', 64)]);
        $asset = ContextAudioAsset::create(['case_context_entry_id' => $entry->id, 'original_path' => $entry->path, 'original_mime' => 'audio/wav', 'original_bytes' => 10, 'duration_ms' => 1000, 'digest' => str_repeat('1', 64), 'status' => 'waiting']);

        $this->actingAs(User::findOrFail($entry->user_id))->getJson("/opportunities/{$opportunity->id}/context/audio/latest")
            ->assertOk()->assertJsonPath('entry.id', $entry->id)->assertJsonPath('entry.status', 'waiting');
        $this->postJson("/opportunities/{$opportunity->id}/context/{$entry->id}/process")
            ->assertAccepted()->assertJsonPath('entry.status', 'queued');
        Queue::assertPushed(PrepareContextAudio::class, fn ($job) => $job->assetId === $asset->id);
    }

    public function test_transcribed_audio_waiting_on_cost_limit_retries_only_extraction(): void
    {
        Queue::fake();
        config(['ai.audio_validated' => true, 'ai.audio_price_micros_per_minute' => 6000]);
        AiSetting::create(['id' => 1, 'mode' => 'openai', 'credential_source' => 'settings', 'api_key' => 'secret', 'policy_approved' => true, 'monthly_micros' => 1_000_000, 'processing_micros' => 100_000, 'input_price' => 200_000, 'output_price' => 1_200_000]);
        $user = User::factory()->create();
        $opportunity = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente', 'stage' => 'briefing']);
        $entry = CaseContextEntry::create(['opportunity_id' => $opportunity->id, 'user_id' => $user->id, 'kind' => 'audio', 'phase' => 'briefing', 'status' => 'waiting', 'path' => 'context-audio/original/audio.wav', 'digest' => str_repeat('2', 64)]);
        $asset = ContextAudioAsset::create(['case_context_entry_id' => $entry->id, 'original_path' => $entry->path, 'prepared_path' => $entry->path, 'original_mime' => 'audio/wav', 'prepared_mime' => 'audio/wav', 'original_bytes' => 10, 'prepared_bytes' => 10, 'duration_ms' => 1000, 'digest' => str_repeat('2', 64), 'status' => 'transcribed', 'error_code' => 'cost_limit']);

        $this->actingAs($user)->postJson("/opportunities/{$opportunity->id}/context/{$entry->id}/process")
            ->assertAccepted()->assertJsonPath('entry.status', 'transcribed');

        $this->assertSame('transcribed', $entry->fresh()->status);
        $this->assertNull($asset->fresh()->error_code);
        Queue::assertPushed(ExtractContextIntelligence::class, fn ($job) => $job->entryId === $entry->id);
        Queue::assertNotPushed(PrepareContextAudio::class);
    }
}
