<?php

namespace Tests\Feature;

use App\Jobs\PrepareContextAudio;
use App\Models\AiSetting;
use App\Models\ContextAudioAsset;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContextAudioUploadTest extends TestCase
{
    use RefreshDatabase;

    private function wav(): string
    {
        $pcm = str_repeat("\0", 16000);

        return 'RIFF'.pack('V', 36 + strlen($pcm)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16).'data'.pack('V', strlen($pcm)).$pcm;
    }

    public function test_authenticated_user_can_resume_and_complete_a_private_audio_upload_once(): void
    {
        Storage::fake('local');
        Queue::fake();
        config(['ai.audio_validated' => true, 'ai.audio_price_micros_per_minute' => 6000]);
        AiSetting::create(['id' => 1, 'mode' => 'openai', 'credential_source' => 'settings', 'api_key' => 'secret', 'policy_approved' => true, 'monthly_micros' => 1_000_000, 'processing_micros' => 100_000, 'input_price' => 200_000, 'output_price' => 1_200_000]);
        $user = User::factory()->create();
        $opportunity = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente', 'stage' => 'briefing']);
        $wav = $this->wav();
        $chunks = [substr($wav, 0, 9000), substr($wav, 9000)];

        $start = $this->actingAs($user)->postJson("/opportunities/{$opportunity->id}/context/audio/uploads", [
            'name' => 'reuniao.wav',
            'mime' => 'audio/wav',
            'bytes' => strlen($wav),
            'chunks' => 2,
            'sha256' => hash('sha256', $wav),
        ])->assertCreated();

        $uuid = $start->json('upload.uuid');
        foreach ($chunks as $index => $content) {
            $this->call('PUT', "/opportunities/{$opportunity->id}/context/audio/uploads/{$uuid}/chunks/{$index}", [], [], [
                'chunk' => UploadedFile::fake()->createWithContent("chunk-{$index}.part", $content),
            ])->assertOk();
        }

        $completed = $this->postJson("/opportunities/{$opportunity->id}/context/audio/uploads/{$uuid}/complete")
            ->assertCreated()
            ->assertJsonPath('entry.status', 'queued');

        $entryId = $completed->json('entry.id');
        $this->assertDatabaseHas('context_audio_assets', [
            'case_context_entry_id' => $entryId,
            'digest' => hash('sha256', $wav),
            'status' => 'queued',
        ]);

        $this->getJson("/opportunities/{$opportunity->id}/context/{$entryId}")
            ->assertOk()
            ->assertJsonPath('entry.audio.status', 'queued')
            ->assertJsonMissingPath('entry.audio.original_path');
        Storage::disk('local')->assertExists(ContextAudioAsset::firstOrFail()->original_path);

        $this->postJson("/opportunities/{$opportunity->id}/context/audio/uploads/{$uuid}/complete")
            ->assertOk()
            ->assertJsonPath('entry.id', $entryId);
        $this->assertDatabaseCount('context_audio_assets', 1);
        Queue::assertPushed(PrepareContextAudio::class, fn ($job) => $job->assetId === ContextAudioAsset::firstOrFail()->id);
    }

    public function test_completion_rejects_a_digest_mismatch_without_creating_context(): void
    {
        Storage::fake('local');
        $opportunity = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente', 'stage' => 'briefing']);
        $wav = $this->wav();
        $start = $this->actingAs(User::factory()->create())->postJson("/opportunities/{$opportunity->id}/context/audio/uploads", [
            'name' => 'reuniao.wav', 'mime' => 'audio/wav', 'bytes' => strlen($wav), 'chunks' => 1,
            'sha256' => str_repeat('a', 64),
        ]);
        $uuid = $start->json('upload.uuid');
        $this->call('PUT', "/opportunities/{$opportunity->id}/context/audio/uploads/{$uuid}/chunks/0", [], [], [
            'chunk' => UploadedFile::fake()->createWithContent('chunk.part', $wav),
        ])->assertOk();

        $this->postJson("/opportunities/{$opportunity->id}/context/audio/uploads/{$uuid}/complete")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('audio');
        $this->assertDatabaseCount('case_context_entries', 0);
    }

    public function test_audio_is_preserved_without_dispatching_paid_work_when_ai_is_not_ready(): void
    {
        Storage::fake('local');
        Queue::fake();
        $opportunity = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente', 'stage' => 'briefing']);
        $wav = $this->wav();
        $start = $this->actingAs(User::factory()->create())->postJson("/opportunities/{$opportunity->id}/context/audio/uploads", [
            'name' => 'reuniao.wav', 'mime' => 'audio/wav', 'bytes' => strlen($wav), 'chunks' => 1,
        ]);
        $uuid = $start->json('upload.uuid');
        $this->call('PUT', "/opportunities/{$opportunity->id}/context/audio/uploads/{$uuid}/chunks/0", [], [], [
            'chunk' => UploadedFile::fake()->createWithContent('chunk.part', $wav),
        ])->assertOk();

        $this->postJson("/opportunities/{$opportunity->id}/context/audio/uploads/{$uuid}/complete")
            ->assertCreated()->assertJsonPath('entry.status', 'waiting');
        $this->assertDatabaseHas('context_audio_assets', ['status' => 'waiting']);
        Queue::assertNotPushed(PrepareContextAudio::class);
    }
}
