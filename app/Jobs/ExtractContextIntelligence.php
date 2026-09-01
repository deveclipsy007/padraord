<?php

namespace App\Jobs;

use App\AI\AiConfiguration;
use App\AI\ContextIntelligenceSchema;
use App\AI\MeteredAiProvider;
use App\Contracts\ContextIntelligenceExtractor;
use App\Models\AiRun;
use App\Models\AssistantPreview;
use App\Models\CaseContextEntry;
use App\Models\ViabilityProject;
use App\Services\AiCostLedger;
use App\Services\CaseContextService;
use App\Services\OdooCostOutboxService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Validation\ValidationException;
use Throwable;

class ExtractContextIntelligence implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 240;

    public function __construct(public int $entryId)
    {
        $this->onQueue('ai');
    }

    public function handle(ContextIntelligenceExtractor $extractor): void
    {
        $entry = CaseContextEntry::with(['segments', 'audioAsset'])->find($this->entryId);
        if (! $entry || ! in_array($entry->status, ['transcribed', 'extracting'], true)) {
            return;
        }
        $inputHash = hash('sha256', json_encode([$entry->digest, $entry->revision, $entry->segments->pluck('id', 'text'), 'context-intelligence-v1']));
        if (AiRun::where('action', 'context_intelligence')->where('input_hash', $inputHash)->where('status', 'success')->exists()) {
            return;
        }
        $entry->update(['status' => 'extracting', 'schema_version' => 'context-intelligence-v1']);
        $entry->audioAsset?->update(['status' => 'extracting']);
        $opportunity = $entry->opportunity()->firstOrFail();
        $context = [
            'opportunity' => $opportunity->only(['id', 'title', 'stage', 'event_date', 'location', 'objective']),
            'briefing' => $opportunity->briefing_data ?? [],
            'briefing_revision' => $opportunity->briefing_revision,
            'viability' => ViabilityProject::where('opportunity_id', $opportunity->id)->first()?->toArray(),
        ];
        $setting = app(AiConfiguration::class)->setting();
        $inputEstimate = max(1, (int) ceil(strlen($entry->segments->pluck('text')->implode(' ')) / 4));
        $estimatedCost = max(1, MeteredAiProvider::cost($inputEstimate, 6000, (int) $setting->input_price, (int) $setting->output_price));
        try {
            $cost = app(AiCostLedger::class)->reserve([
                'opportunity_id' => $opportunity->id,
                'case_context_entry_id' => $entry->id,
                'operation' => 'extraction',
                'provider' => 'openai',
                'model' => (string) config('ai.context_model'),
                'estimated_amount_micros' => $estimatedCost,
                'pricing_version' => (string) config('ai.context_pricing_version', 'settings-v1'),
                'idempotency_key' => hash('sha256', implode('|', [$entry->digest, $entry->revision, config('ai.context_model'), 'context-intelligence-v1'])),
            ]);
        } catch (ValidationException $exception) {
            $message = $exception->errors()['ai'][0] ?? 'Processamento bloqueado pelo limite de custo configurado.';
            $entry->update(['status' => 'waiting']);
            $entry->audioAsset?->update(['status' => 'transcribed', 'error_code' => 'cost_limit', 'error_message' => $message]);

            return;
        }
        $started = microtime(true);
        try {
            $result = $extractor->extract($entry, $context);
            $actions = [];
            foreach (ContextIntelligenceSchema::MODULES as $module) {
                foreach ($result->payload['module_changes'][$module] as $change) {
                    $actions[] = [
                        'module' => $module,
                        'field' => $change['field'],
                        'current' => $this->current($module, $change['field'], $opportunity, $context),
                        'suggested' => $change['suggested'],
                        'reason' => $change['reason'],
                        'kind' => $change['classification'],
                        'evidence_segment_ids' => $change['evidence_segment_ids'],
                        'impacts' => [$module],
                    ];
                }
            }
            $preview = AssistantPreview::create([
                'user_id' => $entry->user_id,
                'mode' => 'review',
                'message' => $result->payload['summary'],
                'context' => [
                    'entry_id' => $entry->id,
                    'opportunity_id' => $opportunity->id,
                    'case_hash' => app(CaseContextService::class)->caseFingerprint($opportunity),
                    'briefing_revision' => $opportunity->briefing_revision,
                    'viability_revision' => ViabilityProject::where('opportunity_id', $opportunity->id)->value('revision') ?? 0,
                    'schema_version' => $result->promptVersion,
                ],
                'actions' => $actions,
                'status' => 'preview',
            ]);
            AiRun::create([
                'context_revision' => $entry->revision,
                'opportunity_id' => $opportunity->id,
                'action' => 'context_intelligence',
                'provider' => $result->provider,
                'model' => $result->model,
                'prompt_version' => $result->promptVersion,
                'status' => 'success',
                'input_hash' => $inputHash,
                'input_text' => json_encode(['entry_id' => $entry->id, 'segment_ids' => $entry->segments->pluck('id')->all()]),
                'output_payload' => $result->payload,
                'input_tokens' => $result->inputTokens,
                'output_tokens' => $result->outputTokens,
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            ]);
            $entry->update(['status' => 'review_ready', 'metadata' => array_merge($entry->metadata ?? [], ['preview_id' => $preview->id, 'summary' => $result->payload['summary']])]);
            $entry->audioAsset?->update(['status' => 'review_ready']);
            $reported = MeteredAiProvider::cost($result->inputTokens, $result->outputTokens, (int) $setting->input_price, (int) $setting->output_price);
            app(AiCostLedger::class)->report($cost, ['input_tokens' => $result->inputTokens, 'output_tokens' => $result->outputTokens], $reported, $result->requestId);
            $outbox = app(OdooCostOutboxService::class)->enqueue($entry->fresh());
            if (config('odoo.mode') === 'json2') {
                ExportAiCostToOdoo::dispatch($outbox->id);
            }
        } catch (Throwable $exception) {
            app(AiCostLedger::class)->uncertain($cost, ['error' => class_basename($exception)]);
            $entry->update(['status' => 'uncertain']);
            $entry->audioAsset?->update(['status' => 'uncertain', 'error_code' => 'extraction_failed', 'error_message' => $exception->getMessage()]);
            throw $exception;
        }
    }

    private function current(string $module, string $field, $opportunity, array $context): mixed
    {
        return match ($module) {
            'case' => $opportunity->{$field} ?? null,
            'briefing' => data_get($opportunity->briefing_data, $field),
            'viability' => data_get($context['viability'], $field),
            default => null,
        };
    }
}
