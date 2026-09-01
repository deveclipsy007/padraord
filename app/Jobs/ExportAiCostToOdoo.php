<?php

namespace App\Jobs;

use App\Contracts\OdooCostExporter;
use App\Models\OdooCostOutbox;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ExportAiCostToOdoo implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public int $timeout = 60;

    public function __construct(public int $outboxId)
    {
        $this->onQueue('integrations');
    }

    public function handle(OdooCostExporter $exporter): void
    {
        $outbox = OdooCostOutbox::find($this->outboxId);
        if (! $outbox || $outbox->status === 'exported') {
            return;
        }
        $outbox->increment('attempts');
        $outbox->update(['status' => 'processing', 'last_error' => null]);
        try {
            $externalId = $exporter->export($outbox->payload, $outbox->idempotency_key);
            $outbox->update(['status' => 'exported', 'external_id' => $externalId, 'exported_at' => now(), 'next_attempt_at' => null]);
        } catch (Throwable $exception) {
            $outbox->update(['status' => 'pending', 'last_error' => $exception->getMessage(), 'next_attempt_at' => now()->addMinutes(min(60, 2 ** min($outbox->attempts, 5)))]);
            throw $exception;
        }
    }
}
