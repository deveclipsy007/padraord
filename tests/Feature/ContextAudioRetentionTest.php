<?php

namespace Tests\Feature;

use App\Models\AudioUploadSession;
use App\Models\CaseContextEntry;
use App\Models\CaseContextSegment;
use App\Models\ContextAudioAsset;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContextAudioRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_managed_audio_and_upload_parts_are_removed_while_transcript_is_preserved(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $opportunity = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente', 'stage' => 'briefing']);
        $entry = CaseContextEntry::create(['opportunity_id' => $opportunity->id, 'user_id' => $user->id, 'kind' => 'audio', 'phase' => 'briefing', 'status' => 'review_ready', 'path' => 'context-audio/original/11111111-1111-4111-8111-111111111111.wav', 'digest' => str_repeat('2', 64), 'expires_at' => now()->subMinute()]);
        Storage::disk('local')->put($entry->path, 'audio');
        $asset = ContextAudioAsset::create(['case_context_entry_id' => $entry->id, 'original_path' => $entry->path, 'original_mime' => 'audio/wav', 'original_bytes' => 5, 'duration_ms' => 1000, 'digest' => str_repeat('2', 64), 'status' => 'review_ready']);
        CaseContextSegment::create(['case_context_entry_id' => $entry->id, 'sequence' => 0, 'speaker_key' => 'A', 'start_ms' => 0, 'end_ms' => 1000, 'text' => 'Preservado']);
        $uuid = '22222222-2222-4222-8222-222222222222';
        AudioUploadSession::create(['uuid' => $uuid, 'opportunity_id' => $opportunity->id, 'user_id' => $user->id, 'original_name' => 'x.wav', 'declared_mime' => 'audio/wav', 'expected_bytes' => 5, 'expected_chunks' => 1, 'temporary_path' => 'context-audio/uploads', 'status' => 'uploading', 'expires_at' => now()->subMinute()]);
        Storage::disk('local')->put("context-audio/uploads/{$uuid}/chunks/0.part", 'part');

        $this->artisan('context:purge-expired-audio')->assertSuccessful();

        Storage::disk('local')->assertMissing($entry->path);
        Storage::disk('local')->assertMissing("context-audio/uploads/{$uuid}/chunks/0.part");
        $this->assertSame('expired', $asset->fresh()->status);
        $this->assertDatabaseHas('case_context_segments', ['case_context_entry_id' => $entry->id, 'text' => 'Preservado']);
        $this->assertDatabaseMissing('audio_upload_sessions', ['uuid' => $uuid]);
    }
}
