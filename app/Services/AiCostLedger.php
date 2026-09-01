<?php

namespace App\Services;

use App\AI\AiConfiguration;
use App\Models\AiCostEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AiCostLedger
{
    public function __construct(private AiConfiguration $configuration) {}

    public function reserve(array $attributes): AiCostEntry
    {
        return DB::transaction(function () use ($attributes): AiCostEntry {
            DB::table('ai_settings')->where('id', 1)->update(['updated_at' => now()]);
            $existing = AiCostEntry::where('idempotency_key', $attributes['idempotency_key'])->first();
            if ($existing) {
                return $existing;
            }
            $setting = $this->configuration->setting();
            $estimate = (int) $attributes['estimated_amount_micros'];
            $used = (int) AiCostEntry::query()
                ->where('created_at', '>=', now()->startOfMonth())
                ->where('status', '!=', 'released')
                ->sum(DB::raw('COALESCE(reconciled_amount_micros, reported_amount_micros, estimated_amount_micros)'));
            if ($estimate <= 0 || ! $setting->processing_micros || ! $setting->monthly_micros || $estimate > $setting->processing_micros || $used + $estimate > $setting->monthly_micros) {
                throw ValidationException::withMessages(['ai' => 'Processamento bloqueado pelo limite de custo configurado.']);
            }

            return AiCostEntry::create(array_merge([
                'currency' => 'USD',
                'status' => 'reserved',
                'audio_seconds' => 0,
                'input_tokens' => 0,
                'cached_input_tokens' => 0,
                'output_tokens' => 0,
            ], $attributes));
        }, 3);
    }

    public function report(AiCostEntry $entry, array $usage, ?int $reportedAmountMicros, ?string $providerRequestId = null): AiCostEntry
    {
        $entry->update([
            'provider_request_id' => $providerRequestId ?: $entry->provider_request_id,
            'audio_seconds' => (int) ($usage['audio_seconds'] ?? $entry->audio_seconds),
            'input_tokens' => (int) ($usage['input_tokens'] ?? $entry->input_tokens),
            'cached_input_tokens' => (int) ($usage['cached_input_tokens'] ?? $entry->cached_input_tokens),
            'output_tokens' => (int) ($usage['output_tokens'] ?? $entry->output_tokens),
            'reported_amount_micros' => $reportedAmountMicros,
            'status' => 'reported',
        ]);

        return $entry->fresh();
    }

    public function uncertain(AiCostEntry $entry, array $metadata = []): void
    {
        $entry->update(['status' => 'uncertain', 'metadata' => array_merge($entry->metadata ?? [], $metadata)]);
    }

    public function release(AiCostEntry $entry): void
    {
        $entry->update(['status' => 'released']);
    }
}
