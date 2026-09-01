<?php

namespace App\AI;

use Illuminate\Support\Facades\DB;

final class MeteredAiProvider implements AiProvider
{
    public function __construct(private AiConfiguration $config) {}

    public function analyzeBriefing(string $transcript, array $context = []): AiCompletion
    {
        // Bound the complete encoded request, not just the visible text.
        $contextBytes = strlen(json_encode($context, JSON_UNESCAPED_UNICODE));
        if (strlen($transcript) > 90000 || $contextBytes > 40000) {
            return AiCompletion::unavailable('Contexto muito extenso. Divida o texto em partes menores; o conteúdo original permanece salvo.');
        }
        $chunks = [];
        $remaining = $transcript;
        do {
            $chunk = mb_strcut($remaining, 0, 30000, 'UTF-8');
            $chunks[] = $chunk;
            $remaining = substr($remaining, strlen($chunk));
        } while ($remaining !== '');
        // Reserve all chunks together, including bounded rolling-summary overhead.
        $bytes = strlen(json_encode($transcript, JSON_UNESCAPED_UNICODE)) + count($chunks) * ($contextBytes + 5000) + max(0, count($chunks) - 1) * 24000;
        $key = hash('sha256', json_encode(['briefing-v3-chunked', 'gpt-4o-mini', $transcript, $context]));
        $reservation = DB::transaction(function () use ($key, $bytes, $context, $chunks) {
            // A write first serializes both SQLite and MariaDB reservations on the singleton.
            DB::table('ai_settings')->where('id', 1)->update(['updated_at' => now()]);
            $s = $this->config->setting();
            if ($this->config->publicState()['status'] !== 'ready') {
                return null;
            }
            $existing = DB::table('ai_consumptions')->where('request_key', $key)->first();
            if ($existing) {
                return ['existing' => $existing];
            }
            // UTF-8 byte count is a conservative token bound; allow system/message overhead.
            $estimate = self::cost($bytes, 2048 * count($chunks), (int) $s->input_price, (int) $s->output_price);
            $used = (int) DB::table('ai_consumptions')->where('month', now()->format('Y-m'))->sum(DB::raw('COALESCE(charged_micros, reserved_micros)'));
            if ($estimate > $s->processing_micros || $used + $estimate > $s->monthly_micros) {
                return null;
            }
            $id = DB::table('ai_consumptions')->insertGetId(['request_key' => $key, 'month' => now()->format('Y-m'), 'action' => ($context['action'] ?? 'briefing'), 'status' => 'reserved', 'reserved_micros' => $estimate, 'input_price' => $s->input_price, 'output_price' => $s->output_price, 'created_at' => now(), 'updated_at' => now()]);

            return ['id' => $id, 'setting' => $s];
        }, 3);
        if (! $reservation) {
            return AiCompletion::unavailable('Processamento bloqueado: confira configuração e limites disponíveis. Edição manual preservada.');
        }
        if (isset($reservation['existing'])) {
            $old = $reservation['existing'];
            if ($old->status === 'success' && $old->result) {
                $r = json_decode($old->result, true);

                return AiCompletion::success($r['payload'], 'openai', 'gpt-4o-mini', 'briefing-v2', $r['input'], $r['output'], (int) $old->charged_micros);
            }

            return AiCompletion::unavailable('Esta solicitação já está em processamento ou aguarda conferência de uma tentativa anterior. Não houve nova cobrança automática.');
        }
        $s = $reservation['setting'];
        $provider = new OpenAiProvider($this->config->key($s), 'gpt-4o-mini');
        $merged = ['summary' => '', 'facts' => [], 'missing_questions' => [], 'risks' => [], 'suggested_changes' => []];
        $inputTokens = 0;
        $outputTokens = 0;
        $changes = [];
        $conflicts = [];
        foreach ($chunks as $index => $chunk) {
            $partContext = $context;
            if (count($chunks) > 1) {
                $partContext['input_part'] = ['index' => $index + 1, 'total' => count($chunks), 'previous_summary' => mb_substr($merged['summary'], 0, 4000)];
            }
            $part = $provider->analyzeBriefing($chunk, $partContext);
            if (! $part->succeeded()) {
                DB::table('ai_consumptions')->where('id', $reservation['id'])->update(['status' => 'uncertain', 'result' => json_encode(['partial_payload' => $merged, 'completed_chunks' => $index]), 'updated_at' => now()]);

                return $part;
            }
            $inputTokens += $part->inputTokens;
            $outputTokens += $part->outputTokens;
            $merged['summary'] = $part->payload['summary'];
            foreach (['facts', 'missing_questions', 'risks'] as $field) {
                $merged[$field] = array_slice(array_merge($merged[$field], $part->payload[$field]), 0, 100);
            }
            foreach ($part->payload['suggested_changes'] as $change) {
                $field = $change['field'];
                if (isset($changes[$field]) && $changes[$field]['suggested'] !== $change['suggested']) {
                    $conflicts[$field] = true;
                    $merged['risks'][] = 'Trechos diferentes sugerem valores distintos para '.(BriefingContext::LABELS[$field] ?? $field).'. Compare a fonte e edite manualmente.';
                }
                $changes[$field] = $change;
            }
            DB::table('ai_consumptions')->where('id', $reservation['id'])->update(['result' => json_encode(['partial_payload' => $merged, 'completed_chunks' => $index + 1]), 'updated_at' => now()]);
        }
        $merged['suggested_changes'] = array_values(array_diff_key($changes, $conflicts));
        $merged['risks'] = array_slice(array_values(array_unique($merged['risks'])), 0, 100);
        $merged['missing_questions'] = array_slice(array_values(array_unique($merged['missing_questions'])), 0, 3);
        $completion = AiCompletion::success($merged, 'openai', 'gpt-4o-mini', 'briefing-v3-chunked', $inputTokens, $outputTokens);
        $cost = $completion->succeeded() && $completion->inputTokens > 0 ? self::cost($completion->inputTokens, $completion->outputTokens, (int) $s->input_price, (int) $s->output_price) : null;
        DB::table('ai_consumptions')->where('id', $reservation['id'])->update([
            'status' => $completion->succeeded() ? 'success' : 'uncertain', 'charged_micros' => $cost,
            'result' => $completion->succeeded() ? json_encode(['payload' => $completion->payload, 'input' => $completion->inputTokens, 'output' => $completion->outputTokens], JSON_UNESCAPED_UNICODE) : null, 'updated_at' => now(),
        ]);

        return $completion->succeeded() ? AiCompletion::success($completion->payload, 'openai', 'gpt-4o-mini', 'briefing-v2', $completion->inputTokens, $completion->outputTokens, $cost ?? 0) : $completion;
    }

    public static function cost(int $input, int $output, int $inputPrice, int $outputPrice): int
    {
        return intdiv($input * $inputPrice + $output * $outputPrice + 999999, 1000000);
    }
}
