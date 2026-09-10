<?php

namespace Tests\Feature;

use App\AI\AiConfiguration;
use App\Jobs\PrepareContextAudio;
use App\Models\AiSetting;
use App\Models\CaseContextEntry;
use App\Models\ContextAudioAsset;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AudioEngineSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function readyWithoutAudio(): void
    {
        AiSetting::create([
            'id' => 1, 'mode' => 'openai', 'credential_source' => 'settings', 'api_key' => 'secret',
            'policy_approved' => true, 'monthly_micros' => 1_000_000, 'processing_micros' => 100_000,
            'input_price' => 200_000, 'output_price' => 1_200_000,
            'audio_enabled' => false, 'audio_price_micros_per_minute' => 0,
        ]);
    }

    private function waitingAudio(User $user): array
    {
        $opportunity = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente', 'stage' => 'briefing']);
        $entry = CaseContextEntry::create(['opportunity_id' => $opportunity->id, 'user_id' => $user->id, 'kind' => 'audio', 'phase' => 'briefing', 'status' => 'waiting', 'path' => 'context-audio/original/audio.wav', 'digest' => str_repeat('7', 64)]);
        $asset = ContextAudioAsset::create(['case_context_entry_id' => $entry->id, 'original_path' => $entry->path, 'original_mime' => 'audio/wav', 'original_bytes' => 10, 'duration_ms' => 60_000, 'digest' => str_repeat('7', 64), 'status' => 'waiting']);

        return [$opportunity, $entry, $asset];
    }

    public function test_transcription_stays_blocked_while_the_audio_engine_is_not_authorised(): void
    {
        Queue::fake();
        $this->readyWithoutAudio();
        $user = User::factory()->create();
        [$opportunity, $entry] = $this->waitingAudio($user);

        $this->assertFalse(app(AiConfiguration::class)->audioReady());
        $this->actingAs($user)->postJson("/opportunities/{$opportunity->id}/context/{$entry->id}/process")
            ->assertUnprocessable()->assertJsonValidationErrors('audio');
        Queue::assertNothingPushed();
        $this->assertSame('waiting', $entry->fresh()->status);
    }

    public function test_administration_can_authorise_the_audio_engine_and_unblock_transcription(): void
    {
        Queue::fake();
        $this->readyWithoutAudio();
        $admin = User::factory()->create(['role' => 'admin', 'password' => bcrypt('segredo-do-admin')]);
        [$opportunity, $entry, $asset] = $this->waitingAudio($admin);

        $this->actingAs($admin)->post('/settings/ai', [
            'password' => 'segredo-do-admin', 'mode' => 'openai', 'credential_source' => 'settings',
            'monthly_usd' => 1, 'processing_usd' => 0.1, 'input_price' => 0.2, 'output_price' => 1.2,
            'policy_approved' => true, 'retention_days' => 30,
            'audio_enabled' => true, 'audio_price_per_minute_usd' => 0.006,
        ])->assertRedirect();

        $this->assertDatabaseHas('ai_settings', ['id' => 1, 'audio_enabled' => true, 'audio_price_micros_per_minute' => 6000]);
        $this->assertTrue(app(AiConfiguration::class)->audioReady());
        $this->assertSame(6000, app(AiConfiguration::class)->audioPriceMicrosPerMinute());

        $this->postJson("/opportunities/{$opportunity->id}/context/{$entry->id}/process")
            ->assertAccepted()->assertJsonPath('entry.status', 'queued');
        Queue::assertPushed(PrepareContextAudio::class, fn ($job) => $job->assetId === $asset->id);
    }

    public function test_authorising_audio_without_a_tariff_is_refused(): void
    {
        $this->readyWithoutAudio();
        $admin = User::factory()->create(['role' => 'admin', 'password' => bcrypt('segredo-do-admin')]);

        $this->actingAs($admin)->post('/settings/ai', [
            'password' => 'segredo-do-admin', 'mode' => 'openai', 'credential_source' => 'settings',
            'monthly_usd' => 1, 'processing_usd' => 0.1, 'input_price' => 0.2, 'output_price' => 1.2,
            'policy_approved' => true, 'retention_days' => 30,
            'audio_enabled' => true, 'audio_price_per_minute_usd' => 0,
        ])->assertSessionHasErrors('audio_price_per_minute_usd');

        $this->assertDatabaseHas('ai_settings', ['id' => 1, 'audio_enabled' => false]);
        $this->assertFalse(app(AiConfiguration::class)->audioReady());
    }

    public function test_the_settings_screen_reports_the_model_of_every_engine(): void
    {
        $this->readyWithoutAudio();
        $state = app(AiConfiguration::class)->publicState();

        $models = collect($state['engines'])->pluck('model', 'key');
        $this->assertSame(config('ai.context_model'), $models['context']);
        $this->assertSame(config('ai.audio_transcription_model'), $models['transcription']);
        $this->assertSame(config('services.openai.model'), $models['briefing_legacy']);
        // O painel não pode anunciar um modelo que nenhum motor usa.
        $this->assertSame(config('ai.context_model'), $state['model']);
    }
}
