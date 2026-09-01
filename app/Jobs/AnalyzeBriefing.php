<?php

namespace App\Jobs;

use App\AI\AiCompletion;
use App\AI\AiProvider;
use App\AI\BriefingContext;
use App\Models\AiRun;
use App\Models\BriefingMessage;
use App\Models\Opportunity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class AnalyzeBriefing implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(public readonly int $messageId)
    {
        $this->onQueue('ai');
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('briefing-message-'.$this->messageId))->expireAfter(240)->dontRelease()];
    }

    public function handle(AiProvider $provider): void
    {
        $message = BriefingMessage::with('opportunity')->find($this->messageId);

        if (! $message) {
            return;
        }

        $context = app(BriefingContext::class)->build($message->opportunity);
        $inputHash = hash('sha256', json_encode([$message->body, $context]));
        $run = AiRun::firstOrNew([
            'briefing_message_id' => $message->id,
            'action' => 'briefing_analysis',
        ]);

        if ($run->exists && $run->status === AiCompletion::SUCCESS) {
            return;
        }

        $startedAt = microtime(true);
        $completion = $provider->analyzeBriefing($message->body, $context);

        $run->fill([
            'context_revision' => $context['revision'],
            'opportunity_id' => $message->opportunity_id,
            'briefing_message_id' => $message->id,
            'action' => 'briefing_analysis',
            'provider' => $completion->provider,
            'model' => $completion->model,
            'prompt_version' => $completion->promptVersion,
            'status' => $completion->status,
            'input_hash' => $inputHash,
            'input_text' => $message->body,
            'output_payload' => $completion->payload,
            'input_tokens' => $completion->inputTokens,
            'output_tokens' => $completion->outputTokens,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'cost_micros' => $completion->costMicros,
            'error' => $completion->error,
        ])->save();

        if ($completion->succeeded()) {
            Opportunity::whereKey($message->opportunity_id)->where('briefing_revision', $context['revision'])->where('briefing_status', 'processing')->update(['briefing_status' => 'awaiting_review']);
            $message->replies()->firstOrCreate(
                ['source' => 'ai'],
                [
                    'opportunity_id' => $message->opportunity_id,
                    'role' => 'assistant',
                    'body' => json_encode($completion->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'metadata' => ['type' => 'briefing_diff', 'ai_run_id' => $run->id],
                ],
            );
        } else {
            Opportunity::whereKey($message->opportunity_id)->where('briefing_revision', $context['revision'])->where('briefing_status', 'processing')->update(['briefing_status' => 'manual']);
        }
    }
}
