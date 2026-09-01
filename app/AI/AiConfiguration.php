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
        ]);
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
            'retention_days' => $s->retention_days, 'used_usd' => $used / 1_000_000, 'model' => 'gpt-4o-mini',
        ];
    }
}
