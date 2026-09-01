<?php

namespace Tests\Feature;

use App\Jobs\PrepareContextAudio;
use App\Models\CaseContextEntry;
use App\Models\ContextAudioAsset;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Queue;
use App\Jobs\TranscribeContextAudio;
use Tests\TestCase;

class PrepareContextAudioJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_compatible_audio_under_provider_limit_is_prepared_without_transcoding(): void
    {
        Storage::fake('local');
        Queue::fake();
        config(['ai.audio_direct_max_bytes' => 1024]);
        $opportunity = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente', 'stage' => 'briefing']);
        $entry = CaseContextEntry::create([
            'opportunity_id' => $opportunity->id, 'user_id' => User::factory()->create()->id,
            'kind' => 'audio', 'phase' => 'briefing', 'status' => 'queued', 'path' => 'context-audio/original/audio.wav',
            'digest' => str_repeat('a', 64),
        ]);
        Storage::disk('local')->put($entry->path, str_repeat('x', 100));
        $asset = ContextAudioAsset::create([
            'case_context_entry_id' => $entry->id, 'original_path' => $entry->path,
            'original_mime' => 'audio/wav', 'original_bytes' => 100, 'duration_ms' => 3_600_000,
            'digest' => str_repeat('a', 64), 'status' => 'queued',
        ]);

        (new PrepareContextAudio($asset->id))->handle(app(\App\Contracts\MediaPreparationProvider::class));

        $asset->refresh();
        $this->assertSame('prepared', $asset->status);
        $this->assertSame($asset->original_path, $asset->prepared_path);
        $this->assertSame(100, $asset->prepared_bytes);
        Queue::assertPushed(TranscribeContextAudio::class, fn ($job) => $job->assetId === $asset->id);
    }

    public function test_oversized_audio_is_recoverable_when_no_media_preparer_is_configured(): void
    {
        Storage::fake('local');
        config(['ai.audio_direct_max_bytes' => 50, 'ai.media_preparation_driver' => 'passthrough']);
        $opportunity = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente', 'stage' => 'briefing']);
        $entry = CaseContextEntry::create([
            'opportunity_id' => $opportunity->id, 'user_id' => User::factory()->create()->id,
            'kind' => 'audio', 'phase' => 'briefing', 'status' => 'queued', 'path' => 'context-audio/original/large.wav',
            'digest' => str_repeat('b', 64),
        ]);
        Storage::disk('local')->put($entry->path, str_repeat('x', 100));
        $asset = ContextAudioAsset::create([
            'case_context_entry_id' => $entry->id, 'original_path' => $entry->path,
            'original_mime' => 'audio/wav', 'original_bytes' => 100, 'duration_ms' => 3_600_000,
            'digest' => str_repeat('b', 64), 'status' => 'queued',
        ]);

        (new PrepareContextAudio($asset->id))->handle(app(\App\Contracts\MediaPreparationProvider::class));

        $asset->refresh();
        $this->assertSame('failed', $asset->status);
        $this->assertSame('media_preparation_required', $asset->error_code);
        $this->assertSame('failed', $entry->fresh()->status);
    }
}
