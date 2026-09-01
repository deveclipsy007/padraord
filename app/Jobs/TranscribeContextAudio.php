<?php

namespace App\Jobs;

use App\Contracts\AudioTranscriber;
use App\Models\CaseContextSegment;
use App\Models\ContextAudioAsset;
use App\Services\AiCostLedger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class TranscribeContextAudio implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 660;

    public function __construct(public int $assetId)
    {
        $this->onQueue('ai');
    }

    public function handle(AudioTranscriber $transcriber): void
    {
        $asset = ContextAudioAsset::with('entry')->find($this->assetId);
        if (! $asset || ! in_array($asset->status, ['prepared', 'transcribing'], true)) {
            return;
        }
        $asset->update(['status' => 'transcribing', 'error_code' => null, 'error_message' => null]);
        $asset->entry->update(['status' => 'transcribing']);
        $price = (int) config('ai.audio_price_micros_per_minute');
        $estimate = max(1, (int) ceil($asset->duration_ms / 60000)) * $price;
        try {
            $cost = app(AiCostLedger::class)->reserve([
                'opportunity_id' => $asset->entry->opportunity_id,
                'case_context_entry_id' => $asset->case_context_entry_id,
                'operation' => 'transcription',
                'provider' => 'openai',
                'model' => (string) config('ai.audio_transcription_model'),
                'audio_seconds' => (int) ceil($asset->duration_ms / 1000),
                'estimated_amount_micros' => $estimate,
                'pricing_version' => (string) config('ai.audio_pricing_version', 'manual-v1'),
                'idempotency_key' => hash('sha256', implode('|', [$asset->digest, config('ai.audio_transcription_model'), 'transcription-v1'])),
            ]);
        } catch (ValidationException $exception) {
            $message = $exception->errors()['ai'][0] ?? 'Processamento bloqueado pelo limite de custo configurado.';
            $asset->update(['status' => 'waiting', 'error_code' => 'cost_limit', 'error_message' => $message]);
            $asset->entry->update(['status' => 'waiting']);

            return;
        }

        try {
            $result = $transcriber->transcribe($asset);
            DB::transaction(function () use ($asset, $result): void {
                $locked = ContextAudioAsset::whereKey($asset->id)->lockForUpdate()->firstOrFail();
                if ($locked->status !== 'transcribing') {
                    return;
                }
                foreach ($result->segments as $sequence => $segment) {
                    CaseContextSegment::create([
                        'case_context_entry_id' => $locked->case_context_entry_id,
                        'sequence' => $sequence,
                        'source_chunk' => 0,
                        'provider_segment_id' => $segment->providerId,
                        'speaker_key' => $segment->speaker,
                        'start_ms' => $segment->startMs,
                        'end_ms' => $segment->endMs,
                        'text' => $segment->text,
                    ]);
                }
                $locked->update([
                    'status' => 'transcribed',
                    'provider_request_id' => $result->requestId,
                    'transcription_model' => $result->model,
                    'transcript_revision' => $locked->transcript_revision + 1,
                    'metadata' => array_merge($locked->metadata ?? [], ['transcript_text' => $result->text, 'usage' => $result->usage]),
                ]);
                $locked->entry()->update(['status' => 'transcribed']);
                ExtractContextIntelligence::dispatch($locked->case_context_entry_id);
            }, 3);
            app(AiCostLedger::class)->report($cost, ['audio_seconds' => (int) ceil($asset->duration_ms / 1000)], null, $result->requestId);
        } catch (Throwable $exception) {
            app(AiCostLedger::class)->uncertain($cost, ['error' => class_basename($exception)]);
            $asset->update(['status' => 'uncertain', 'error_code' => 'transcription_failed', 'error_message' => $exception->getMessage()]);
            $asset->entry->update(['status' => 'uncertain']);
            throw $exception;
        }
    }
}
