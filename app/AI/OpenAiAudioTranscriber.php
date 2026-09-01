<?php

namespace App\AI;

use App\Contracts\AudioTranscriber;
use App\Data\TranscriptionResult;
use App\Data\TranscriptionSegment;
use App\Models\ContextAudioAsset;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class OpenAiAudioTranscriber implements AudioTranscriber
{
    public function __construct(private AiConfiguration $configuration) {}

    public function transcribe(ContextAudioAsset $asset): TranscriptionResult
    {
        $setting = $this->configuration->setting();
        $key = $this->configuration->key($setting);
        if ($key === '' || $this->configuration->publicState()['status'] !== 'ready') {
            throw new RuntimeException('A transcrição está bloqueada pela configuração da IA.');
        }
        $path = $asset->prepared_path ?: $asset->original_path;
        $disk = Storage::disk('local');
        if (! $disk->exists($path)) {
            throw new RuntimeException('O áudio privado preparado não foi encontrado.');
        }
        $stream = fopen($disk->path($path), 'rb');
        if ($stream === false) {
            throw new RuntimeException('Não foi possível abrir o áudio privado.');
        }

        try {
            $response = Http::withToken($key)
                ->timeout((int) config('ai.audio_http_timeout', 600))
                ->attach('file', $stream, basename($path))
                ->post('https://api.openai.com/v1/audio/transcriptions', [
                    'model' => config('ai.audio_transcription_model', 'gpt-4o-transcribe-diarize'),
                    'response_format' => 'diarized_json',
                    'chunking_strategy' => 'auto',
                ]);
        } finally {
            fclose($stream);
        }
        if (! $response->successful()) {
            throw new RuntimeException('A OpenAI não concluiu a transcrição (HTTP '.$response->status().').');
        }

        $payload = $response->json();
        $segments = [];
        foreach ($payload['segments'] ?? [] as $index => $segment) {
            $speaker = $segment['speaker'] ?? null;
            $text = trim((string) ($segment['text'] ?? ''));
            $start = $segment['start'] ?? null;
            $end = $segment['end'] ?? null;
            if (! is_string($speaker) || ! preg_match('/^[a-zA-Z0-9_-]{1,40}$/', $speaker) || $text === '' || mb_strlen($text) > 4000 || ! is_numeric($start) || ! is_numeric($end) || $start < 0 || $end < $start || $end * 1000 > $asset->duration_ms + 2000) {
                throw new RuntimeException('A transcrição retornou um segmento inválido.');
            }
            $segments[] = new TranscriptionSegment(
                (string) ($segment['id'] ?? $index),
                $speaker,
                (int) round($start * 1000),
                (int) round($end * 1000),
                $text,
            );
        }
        if ($segments === []) {
            throw new RuntimeException('A transcrição não retornou segmentos utilizáveis.');
        }

        return new TranscriptionResult(
            trim((string) ($payload['text'] ?? collect($segments)->pluck('text')->implode(' '))),
            $segments,
            (string) config('ai.audio_transcription_model', 'gpt-4o-transcribe-diarize'),
            $response->header('x-request-id'),
            is_array($payload['usage'] ?? null) ? $payload['usage'] : [],
        );
    }
}
