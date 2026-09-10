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

    public function test_resume_reports_only_received_parts_and_is_private_to_the_owner(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $case = Opportunity::create(['title' => 'Retomada', 'client_name' => 'Cliente', 'stage' => 'briefing']);
        $uuid = $this->actingAs($owner)->postJson("/opportunities/{$case->id}/context/audio/uploads", [
            'name' => 'reuniao.wav', 'mime' => 'audio/wav', 'bytes' => 16044, 'chunks' => 2,
        ])->assertCreated()->json('upload.uuid');
        $path = "/opportunities/{$case->id}/context/audio/uploads/{$uuid}";
        $this->call('PUT', "$path/chunks/0", [], [], ['chunk' => UploadedFile::fake()->createWithContent('part', str_repeat('a', 8000))])->assertOk();
        $this->getJson($path)->assertOk()->assertJsonPath('upload.received_indices', [0])->assertJsonMissingPath('upload.temporary_path');
        $this->actingAs(User::factory()->create())->getJson($path)->assertNotFound();
    }

    private function wav(): string
    {
        $pcm = str_repeat("\0", 16000);

        return 'RIFF'.pack('V', 36 + strlen($pcm)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16).'data'.pack('V', strlen($pcm)).$pcm;
    }

    public function test_prepared_audio_attaches_to_the_original_context_without_replacing_its_source(): void
    {
        Storage::fake('local');
        Queue::fake();
        $actor = User::factory()->create();
        $case = Opportunity::create(['title' => 'Preparação', 'client_name' => 'Cliente', 'stage' => 'briefing']);
        $this->actingAs($actor);
        $wav = $this->wav();
        $upload = function (array $extra = []) use ($case, $wav) {
            $uuid = $this->postJson("/opportunities/{$case->id}/context/audio/uploads", $extra + [
                'name' => 'reuniao.wav', 'mime' => 'audio/wav', 'bytes' => strlen($wav), 'chunks' => 1,
            ])->assertCreated()->json('upload.uuid');
            $this->call('PUT', "/opportunities/{$case->id}/context/audio/uploads/{$uuid}/chunks/0", [], [], [
                'chunk' => UploadedFile::fake()->createWithContent('part', $wav),
            ])->assertOk();

            return $this->postJson("/opportunities/{$case->id}/context/audio/uploads/{$uuid}/complete")->assertCreated()->json('entry.id');
        };
        $entryId = $upload();
        $original = ContextAudioAsset::firstOrFail()->original_path;
        $this->assertSame($entryId, $upload(['prepared_for' => $entryId]));
        $asset = ContextAudioAsset::firstOrFail();
        $this->assertNotNull($asset->prepared_path);
        $this->assertSame($original, $asset->original_path);
        $this->assertDatabaseCount('case_context_entries', 1);
        Storage::disk('local')->assertExists($original);
        Storage::disk('local')->assertExists($asset->prepared_path);
    }

    public function test_authenticated_user_can_resume_and_complete_a_private_audio_upload_once(): void
    {
        Storage::fake('local');
        Queue::fake();
        config(['ai.audio_validated' => true, 'ai.audio_price_micros_per_minute' => 6000]);
        AiSetting::create(['id' => 1, 'mode' => 'openai', 'credential_source' => 'settings', 'api_key' => 'secret', 'policy_approved' => true, 'monthly_micros' => 1_000_000, 'processing_micros' => 100_000, 'input_price' => 200_000, 'output_price' => 1_200_000, 'audio_enabled' => true, 'audio_price_micros_per_minute' => 6000]);
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
