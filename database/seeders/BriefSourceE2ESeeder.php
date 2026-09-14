<?php

namespace Database\Seeders;

use App\Models\CaseContextEntry;
use App\Models\CaseContextSegment;
use App\Models\ContextAudioAsset;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class BriefSourceE2ESeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing') || ! str_ends_with(config('database.connections.sqlite.database'), '/e2e.sqlite')) {
            throw new \RuntimeException('Esta fixture exige o banco descartável do Playwright.');
        }
        $case = Opportunity::firstOrFail();
        $user = User::firstOrFail();
        $path = 'context-audio/original/eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee.wav';
        $pcm = str_repeat("\0", 16000 * 2 * 20);
        $wav = 'RIFF'.pack('V', 36 + strlen($pcm)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 16000, 32000, 2, 16).'data'.pack('V', strlen($pcm)).$pcm;
        Storage::disk('local')->put($path, $wav);
        $entry = CaseContextEntry::create(['opportunity_id' => $case->id, 'user_id' => $user->id, 'kind' => 'audio', 'phase' => 'briefing', 'status' => 'review_ready', 'title' => 'Reunião de teste — origem', 'path' => $path, 'digest' => hash('sha256', $wav), 'expires_at' => now()->addDays(30)]);
        ContextAudioAsset::create(['case_context_entry_id' => $entry->id, 'original_path' => $path, 'original_mime' => 'audio/wav', 'original_bytes' => strlen($wav), 'duration_ms' => 20000, 'digest' => hash('sha256', $wav), 'status' => 'review_ready']);
        CaseContextSegment::create(['case_context_entry_id' => $entry->id, 'sequence' => 0, 'speaker_key' => 'A', 'speaker_name' => 'Cliente de teste', 'start_ms' => 8500, 'end_ms' => 12000, 'text' => 'Queremos reunir os parceiros no congresso.']);
    }
}
