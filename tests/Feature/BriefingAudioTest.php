<?php

namespace Tests\Feature;

use App\Jobs\TranscribeBriefing;
use App\Models\AiSetting;
use App\Models\BriefingAudio;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BriefingAudioTest extends TestCase
{
    use RefreshDatabase;

    private function audio(): UploadedFile
    {
        $pcm = str_repeat("\0", 16000);
        $wav = 'RIFF'.pack('V', 36 + strlen($pcm)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16).'data'.pack('V', strlen($pcm)).$pcm;

        return UploadedFile::fake()->createWithContent('reuniao.wav', $wav);
    }

    public function test_upload_is_private_persists_without_ai_and_cannot_be_read_under_another_case(): void
    {
        Storage::fake('local');
        Http::fake();
        $o = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente', 'stage' => 'briefing']);
        $other = Opportunity::create(['title' => 'Outro', 'client_name' => 'Cliente', 'stage' => 'briefing']);
        $this->actingAs(User::factory()->create())->post("/opportunities/$o->id/briefing/audio", ['audio' => $this->audio()])->assertRedirect();
        $audio = DB::table('briefing_audio')->first();
        $this->assertNotNull($audio);
        Storage::disk('local')->assertExists($audio->path);
        $this->assertSame('waiting', $audio->status);
        $this->get("/opportunities/$other->id/briefing/audio/$audio->id")->assertNotFound();
        $this->get("/opportunities/$o->id/briefing/audio/$audio->id")->assertOk();
        $this->post('/logout');
        $this->get("/opportunities/$o->id/briefing/audio/$audio->id")->assertRedirect('/login');
        Http::assertNothingSent();
    }

    public function test_invalid_audio_is_rejected_without_storing_or_sending(): void
    {
        Storage::fake('local');
        Http::fake();
        $o = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente', 'stage' => 'briefing']);
        $this->actingAs(User::factory()->create())->post("/opportunities/$o->id/briefing/audio", ['audio' => UploadedFile::fake()->createWithContent('fake.wav', 'not audio')])->assertSessionHasErrors('audio');
        $this->assertDatabaseCount('briefing_audio', 0);
        Http::assertNothingSent();
    }

    public function test_expired_audio_is_removed_without_removing_transcript(): void
    {
        Storage::fake('local');
        $o = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente', 'stage' => 'briefing']);
        $this->actingAs(User::factory()->create())->post("/opportunities/$o->id/briefing/audio", ['audio' => $this->audio()]);
        $a = BriefingAudio::first();
        $a->update(['expires_at' => now()->subDay(), 'segments' => [['speaker' => 'A', 'start' => 0, 'end' => 1, 'text' => 'Preservado']]]);
        $this->artisan('ai:purge-expired-audio')->assertSuccessful();
        Storage::disk('local')->assertMissing($a->path);
        $this->assertSame('Preservado', $a->fresh()->segments[0]['text']);
    }

    public function test_transcription_runs_once_and_speaker_corrections_are_versioned(): void
    {
        Storage::fake('local');
        config(['ai.audio_validated' => true, 'ai.audio_price_micros_per_minute' => 6000]);
        AiSetting::create(['id' => 1, 'mode' => 'openai', 'credential_source' => 'settings', 'api_key' => 'test-key', 'policy_approved' => true, 'monthly_micros' => 1000000, 'processing_micros' => 100000, 'input_price' => 150000, 'output_price' => 600000]);
        Http::fake(['api.openai.com/*' => Http::response(['segments' => [['speaker' => 'A', 'start' => 0, 'end' => 1, 'text' => 'Objetivo: reunir a equipe.']]])]);
        $o = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente', 'stage' => 'briefing']);
        $this->actingAs(User::factory()->create())->post("/opportunities/$o->id/briefing/audio", ['audio' => $this->audio()])->assertRedirect();
        $audio = BriefingAudio::first();
        $audio->update(['status' => 'queued']);
        (new TranscribeBriefing($audio->id))->handle();
        (new TranscribeBriefing($audio->id))->handle();
        $this->assertSame('transcribed', $audio->fresh()->status);
        Http::assertSentCount(1);
        $this->patch("/opportunities/$o->id/briefing/audio/$audio->id", ['revision' => 0, 'speaker_names' => ['A' => 'Rômulo']])->assertRedirect();
        $this->assertSame('Rômulo', $audio->fresh()->speaker_names['A']);
        $this->patch("/opportunities/$o->id/briefing/audio/$audio->id", ['revision' => 0, 'speaker_names' => ['A' => 'Outro']])->assertSessionHasErrors('audio');
        Queue::fake();
        $this->post("/opportunities/$o->id/briefing/audio/$audio->id/forward")->assertRedirect();
        $this->post("/opportunities/$o->id/briefing/audio/$audio->id/forward")->assertRedirect();
        $this->assertDatabaseCount('briefing_messages', 1);
    }
}
