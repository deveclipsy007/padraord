<?php

namespace App\Http\Controllers;

use App\AI\AiConfiguration;
use App\AI\AiProvider;
use App\Enums\Ability;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AiSettingsController extends Controller
{
    public function show(Request $request, AiConfiguration $config)
    {
        Gate::authorize(Ability::ManageAi->value);

        return Inertia::render('AiSettings', ['settings' => $config->publicState(),
            'attempts' => DB::table('ai_consumptions')->latest('id')->limit(20)->get(['id', 'action', 'status', 'reserved_micros', 'charged_micros', 'created_at'])]);
    }

    public function store(Request $request, AiConfiguration $config)
    {
        Gate::authorize(Ability::ManageAi->value);
        $v = $request->validate([
            'password' => ['required', 'current_password'], 'mode' => ['required', Rule::in(['manual', 'demo', 'openai'])],
            'credential_source' => ['required', Rule::in(['environment', 'settings'])],
            'api_key' => ['nullable', 'string', 'max:500'], 'remove_key' => ['sometimes', 'boolean'],
            'monthly_usd' => ['required', 'numeric', 'min:0', 'max:10000'],
            'processing_usd' => ['required', 'numeric', 'min:0', 'max:100'],
            'input_price' => ['required', 'numeric', 'min:0', 'max:1000'],
            'output_price' => ['required', 'numeric', 'min:0', 'max:1000'],
            'policy_approved' => ['required', 'boolean'], 'retention_days' => ['required', 'integer', 'min:1', 'max:365'],
            // Ausentes numa atualização parcial: preservar o que já está gravado,
            // nunca revogar ou reautorizar a transcrição por omissão.
            'audio_enabled' => ['sometimes', 'boolean'],
            'audio_price_per_minute_usd' => ['sometimes', 'numeric', 'min:0', 'max:100'],
        ]);
        $current = $config->setting();
        $audioEnabled = (bool) ($v['audio_enabled'] ?? $current->audio_enabled);
        $audioPriceMicros = array_key_exists('audio_price_per_minute_usd', $v)
            ? (int) round((float) $v['audio_price_per_minute_usd'] * 1_000_000)
            : (int) $current->audio_price_micros_per_minute;
        if ($audioEnabled && $audioPriceMicros <= 0) {
            throw ValidationException::withMessages(['audio_price_per_minute_usd' => 'Registre a tarifa por minuto cobrada pelo provedor antes de autorizar a transcrição.']);
        }
        DB::transaction(function () use ($config, $v, $request, $audioEnabled, $audioPriceMicros) {
            $s = $config->setting();
            $s->fill(collect($v)->only(['mode', 'credential_source', 'policy_approved', 'retention_days'])->all());
            $s->audio_enabled = $audioEnabled;
            $s->audio_price_micros_per_minute = $audioPriceMicros;
            foreach (['monthly_usd' => 'monthly_micros', 'processing_usd' => 'processing_micros', 'input_price' => 'input_price', 'output_price' => 'output_price'] as $from => $to) {
                $s->$to = (int) round((float) $v[$from] * 1_000_000);
            }
            if ($v['remove_key'] ?? false) {
                $s->api_key = null;
                $s->credential_source = 'settings';
            } elseif (! empty($v['api_key'])) {
                $s->api_key = trim($v['api_key']);
            }
            $s->save();
            AuditLog::create(['user_id' => $request->user()->id, 'action' => 'ai.settings.updated', 'subject_type' => 'ai_settings', 'subject_id' => 1, 'metadata' => ['mode' => $s->mode, 'credential_source' => $s->credential_source, 'key_changed' => ! empty($v['api_key']) || ($v['remove_key'] ?? false), 'audio_enabled' => (bool) $s->audio_enabled]]);
        });

        return back()->with('success', 'Configurações salvas. A chave permanece somente no servidor.');
    }

    public function test(Request $request, AiConfiguration $config)
    {
        Gate::authorize(Ability::ManageAi->value);
        $request->validate(['password' => ['required', 'current_password']]);
        if ($config->publicState()['status'] !== 'ready') {
            throw ValidationException::withMessages(['ai' => 'Configure chave, política, tarifas e limites antes do teste pago.']);
        }
        $result = app(AiProvider::class)->analyzeBriefing('Cenário fictício: reunião de equipe para 10 participantes.', ['action' => 'connection_test']);

        return back()->with($result->succeeded() ? 'success' : 'error', $result->succeeded() ? 'Conexão validada com conteúdo fictício. Consumo registrado.' : $result->error);
    }
}
