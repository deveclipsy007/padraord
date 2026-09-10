<?php

namespace App\Jobs;

use App\AI\AiConfiguration;
use App\Models\BriefingAudio;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class TranscribeBriefing implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(public int $audioId)
    {
        $this->onQueue('ai');
    }

    public function handle(): void
    {
        $config = app(AiConfiguration::class);
        if (! BriefingAudio::whereKey($this->audioId)->where('status', 'queued')->update(['status' => 'transcribing'])) {
            return;
        }
        $audio = BriefingAudio::findOrFail($this->audioId);
        $reservation = DB::transaction(function () use ($config, $audio) {
            DB::table('ai_settings')->where('id', 1)->update(['updated_at' => now()]);
            if (! $config->audioReady() || $audio->expires_at->isPast()) {
                return null;
            }
            $price = $config->audioPriceMicrosPerMinute();
            $s = $config->setting();
            // Round up complete minutes with a safety minute. Do not pretend this is a provider invoice.
            $estimate = ((int) ceil($audio->seconds / 60) + 1) * $price;
            $used = (int) DB::table('ai_consumptions')->where('month', now()->format('Y-m'))->sum(DB::raw('COALESCE(charged_micros, reserved_micros)'));
            if (! $price || $estimate > $s->processing_micros || $used + $estimate > $s->monthly_micros) {
                return null;
            }
            $key = hash('sha256', 'audio-diarize-v1:'.$audio->id.':'.$audio->digest);
            if (DB::table('ai_consumptions')->where('request_key', $key)->exists()) {
                return null;
            }
            $id = DB::table('ai_consumptions')->insertGetId(['request_key' => $key, 'month' => now()->format('Y-m'), 'action' => 'audio_transcription', 'status' => 'reserved', 'reserved_micros' => $estimate, 'input_price' => $price, 'output_price' => 0, 'created_at' => now(), 'updated_at' => now()]);

            return ['id' => $id, 'key' => $config->key($s)];
        }, 3);
        if (! $reservation) {
            $audio->update(['status' => 'waiting', 'error' => 'Transcrição bloqueada: confira validação da hospedagem, tarifa, limites e retenção.']);

            return;
        }
        try {
            $stream = Storage::disk('local')->readStream($audio->path);
            if (! $stream) {
                throw new \RuntimeException('missing');
            }
            try {
                $response = Http::withToken($reservation['key'])->timeout(150)->attach('file', $stream, basename($audio->path))->post('https://api.openai.com/v1/audio/transcriptions', ['model' => 'gpt-4o-transcribe-diarize', 'response_format' => 'diarized_json', 'chunking_strategy' => 'auto']);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
            $segments = $response->json('segments');
            if (! $response->successful() || ! is_array($segments) || ! array_is_list($segments) || ! count($segments) || count($segments) > 5000) {
                throw new \RuntimeException('invalid');
            }
            $safe = [];
            foreach ($segments as $s) {
                if (! is_array($s) || ! is_string($s['text'] ?? null) || mb_strlen($s['text']) > 4000 || ! is_string($s['speaker'] ?? null) || ! preg_match('/^[a-zA-Z0-9_-]{1,40}$/', $s['speaker']) || ! is_numeric($s['start'] ?? null) || ! is_numeric($s['end'] ?? null) || $s['start'] < 0 || $s['end'] < $s['start'] || $s['end'] > $audio->seconds + 2) {
                    throw new \RuntimeException('invalid_segment');
                }
                $safe[] = ['speaker' => $s['speaker'], 'start' => (float) $s['start'], 'end' => (float) $s['end'], 'text' => $s['text']];
            }
            DB::transaction(function () use ($audio, $safe, $reservation) {
                $audio->update(['status' => 'transcribed', 'segments' => $safe, 'error' => null]);
                DB::table('ai_consumptions')->where('id', $reservation['id'])->update(['status' => 'estimated', 'updated_at' => now()]);
            });
        } catch (\Throwable) {
            $audio->update(['status' => 'uncertain', 'error' => 'Não foi possível confirmar a transcrição. Áudio preservado; não repetiremos uma chamada que pode ter sido cobrada. Cole a transcrição para continuar.']);
            DB::table('ai_consumptions')->where('id', $reservation['id'])->update(['status' => 'uncertain', 'updated_at' => now()]);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        BriefingAudio::whereKey($this->audioId)->where('status', 'transcribing')->update(['status' => 'uncertain', 'error' => 'O processamento foi interrompido. Confira a tentativa antes de uma nova cobrança.']);
    }
}
