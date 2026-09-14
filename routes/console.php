<?php

use App\Models\AudioUploadSession;
use App\Models\AuditLog;
use App\Models\BriefingAudio;
use App\Models\CaseContextEntry;
use App\Models\Opportunity;
use App\Services\BriefSourceService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('queue:work database --queue=media,ai --stop-when-empty --max-jobs=1 --max-time=720 --tries=1 --timeout=690')
    ->everyMinute()
    ->withoutOverlapping(12);

Schedule::command('queue:work database --queue=integrations,documents,emails,default --stop-when-empty --max-jobs=5 --max-time=240 --tries=3 --timeout=180')
    ->everyMinute()
    ->withoutOverlapping(5);

Artisan::command('ai:purge-expired-audio', function () {
    $disk = Storage::disk('local');
    $count = 0;
    foreach (BriefingAudio::where('expires_at', '<', now())->whereNotIn('status', ['queued', 'transcribing'])->cursor() as $audio) {
        if (! preg_match('/^briefing-audio\/[0-9a-f-]{36}\.(mp3|wav|m4a)$/D', $audio->path)) {
            throw new RuntimeException('Unexpected audio path; purge stopped.');
        }
        $path = $disk->path($audio->path);
        if (is_link($path) || is_link(dirname($path))) {
            throw new RuntimeException('Linked audio path; purge stopped.');
        }
        if (! $disk->exists($audio->path)) {
            continue;
        }
        if (! is_file($path) || ! $disk->delete($audio->path)) {
            throw new RuntimeException('Audio deletion failed; purge stopped.');
        }
        AuditLog::create(['action' => 'briefing.audio.expired', 'subject_type' => Opportunity::class, 'subject_id' => $audio->opportunity_id, 'metadata' => ['audio_id' => $audio->id, 'transcript_preserved' => true]]);
        $count++;
    }
    $this->info($count.' expired audio files removed; transcripts preserved.');
})->purpose('Remove only managed expired audio, preserving transcript and audit');

Schedule::command('ai:purge-expired-audio')->daily()->withoutOverlapping();

Artisan::command('context:purge-expired-audio', function () {
    $disk = Storage::disk('local');
    $removed = 0;
    foreach (AudioUploadSession::where('status', 'uploading')->where('expires_at', '<', now())->cursor() as $session) {
        if (! preg_match('/^[0-9a-f-]{36}$/D', $session->uuid)) {
            throw new RuntimeException('Unexpected upload identifier; purge stopped.');
        }
        $directory = "context-audio/uploads/{$session->uuid}";
        $absolute = $disk->path($directory);
        if (is_link($absolute) || is_link(dirname($absolute))) {
            throw new RuntimeException('Linked upload path; purge stopped.');
        }
        if ($disk->exists($directory) && ! $disk->deleteDirectory($directory)) {
            throw new RuntimeException('Upload cleanup failed; purge stopped.');
        }
        $session->delete();
        $removed++;
    }
    foreach (CaseContextEntry::with('audioAsset')->where('kind', 'audio')->where('expires_at', '<', now())->whereNotIn('status', ['queued', 'preparing', 'transcribing', 'extracting'])->cursor() as $entry) {
        if (app(BriefSourceService::class)->retained($entry)) {
            $reason = $entry->retain_forever ? 'preferencia_de_retencao' : 'fonte_de_briefing_aprovado';
            if ($entry->retention_reason !== $reason) {
                $entry->update(['retention_reason' => $reason]);
                AuditLog::create(['action' => 'context.audio.retained', 'subject_type' => Opportunity::class, 'subject_id' => $entry->opportunity_id, 'metadata' => ['entry_id' => $entry->id, 'reason' => $reason]]);
            }

            continue;
        }
        $asset = $entry->audioAsset;
        if (! $asset || $asset->status === 'expired') {
            continue;
        }
        $paths = array_values(array_unique(array_filter([$asset->original_path, $asset->prepared_path])));
        foreach ($paths as $path) {
            if (! preg_match('#^context-audio/(original|prepared)/[0-9a-f-]{36}\.(mp3|wav|m4a)$#D', $path)) {
                throw new RuntimeException('Unexpected context audio path; purge stopped.');
            }
            $absolute = $disk->path($path);
            if (is_link($absolute) || is_link(dirname($absolute))) {
                throw new RuntimeException('Linked context audio path; purge stopped.');
            }
            if ($disk->exists($path) && (! is_file($absolute) || ! $disk->delete($path))) {
                throw new RuntimeException('Context audio deletion failed; purge stopped.');
            }
        }
        $asset->update(['status' => 'expired']);
        AuditLog::create(['action' => 'context.audio.expired', 'subject_type' => Opportunity::class, 'subject_id' => $entry->opportunity_id, 'metadata' => ['entry_id' => $entry->id, 'transcript_preserved' => true]]);
        $removed++;
    }
    $this->info($removed.' expired context audio resources removed; transcripts preserved.');
})->purpose('Remove only managed context audio files and expired upload parts');

Schedule::command('context:purge-expired-audio')->daily()->withoutOverlapping();
