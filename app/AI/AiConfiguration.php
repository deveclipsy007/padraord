<?php

namespace App\AI;

use App\Models\AiSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AiConfiguration
{
    public function setting(): AiSetting
    {
        return (Schema::hasTable('ai_settings') ? AiSetting::find(1) : null) ?? new AiSetting([
            'id' => 1, 'mode' => config('ai.mode', 'manual'), 'credential_source' => 'environment',
            'policy_approved' => config('ai.data_policy_approved', false), 'monthly_micros' => 0,
            'processing_micros' => 0, 'input_price' => 0, 'output_price' => 0, 'retention_days' => 30,
            'audio_enabled' => config('ai.audio_validated', false),
            'audio_price_micros_per_minute' => max(0, (int) config('ai.audio_price_micros_per_minute', 0)),
        ]);
    }

    /**
     * Transcrição é uma chamada paga por minuto. Só libera quando a equipe
     * autorizou o motor de áudio e registrou a tarifa, além do estado geral
     * já estar pronto. Autoridade única para todos os pontos de entrada.
     */
    public function audioReady(): bool
    {
        return $this->publicState()['audio_ready'];
    }

    public function audioPriceMicrosPerMinute(): int
    {
        return (int) $this->setting()->audio_price_micros_per_minute;
    }

    /**
     * @return list<array{key: string, label: string, model: string, note: string}>
     */
    public function engines(): array
    {
        return [
            ['key' => 'chat', 'label' => 'Assistente de conversa', 'model' => AssistantChat::MODEL, 'note' => 'Usa a tarifa de referência própria exibida acima.'],
            ['key' => 'context', 'label' => 'Organização de contexto e briefing', 'model' => (string) config('ai.context_model'), 'note' => 'Usa as tarifas de entrada e saída configuradas abaixo.'],
            ['key' => 'transcription', 'label' => 'Transcrição de reunião', 'model' => (string) config('ai.audio_transcription_model'), 'note' => 'Usa a tarifa por minuto configurada abaixo.'],
            ['key' => 'briefing_legacy', 'label' => 'Briefing assistido (fluxo anterior)', 'model' => (string) config('services.openai.model'), 'note' => 'Mantido para os casos que já usavam o fluxo anterior.'],
        ];
    }

    public function key(AiSetting $setting): string
    {
        return $setting->credential_source === 'environment' ? (string) config('services.openai.api_key', '') : (string) $setting->api_key;
    }

    public function publicState(): array
    {
        $s = $this->setting();
        $used = Schema::hasTable('ai_consumptions') ? (int) DB::table('ai_consumptions')->where('month', now()->format('Y-m'))->sum(DB::raw('COALESCE(charged_micros, reserved_micros)')) : 0;
        $status = match (true) {
            $s->mode === 'manual' => 'manual',
            $s->mode === 'demo' => 'demo',
            $this->key($s) === '' => 'missing_key',
            ! $s->policy_approved => 'policy_pending',
            ! $s->monthly_micros || ! $s->processing_micros || ! $s->input_price || ! $s->output_price => 'limits_pending',
            $used >= $s->monthly_micros => 'limit_reached',
            default => 'ready',
        };

        return [
            'mode' => $s->mode, 'status' => $status, 'has_key' => $this->key($s) !== '',
            'credential_source' => $s->credential_source, 'policy_approved' => (bool) $s->policy_approved,
            'monthly_usd' => $s->monthly_micros / 1_000_000, 'processing_usd' => $s->processing_micros / 1_000_000,
            'input_price' => $s->input_price / 1_000_000, 'output_price' => $s->output_price / 1_000_000,
            'retention_days' => $s->retention_days, 'used_usd' => $used / 1_000_000,
            'model' => (string) config('ai.context_model'),
            'engines' => $this->engines(),
            'audio_enabled' => (bool) $s->audio_enabled,
            'audio_price_per_minute_usd' => $s->audio_price_micros_per_minute / 1_000_000,
            'audio_ready' => (bool) $s->audio_enabled && (int) $s->audio_price_micros_per_minute > 0 && $status === 'ready',
            'chat_model' => AssistantChat::MODEL,
            'chat_input_price' => AssistantChat::INPUT_PRICE / 1_000_000,
            'chat_output_price' => AssistantChat::OUTPUT_PRICE / 1_000_000,
            'calculated_usd' => Schema::hasTable('ai_consumptions') ? DB::table('ai_consumptions')->where('month', now()->format('Y-m'))->sum('charged_micros') / 1_000_000 : 0,
            'reserved_usd' => Schema::hasTable('ai_consumptions') ? DB::table('ai_consumptions')->where('month', now()->format('Y-m'))->whereNull('charged_micros')->sum('reserved_micros') / 1_000_000 : 0,
        ];
    }
}
