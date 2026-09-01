<?php

namespace App\Jobs;

use App\Contracts\MediaPreparationProvider;
use App\Exceptions\MediaPreparationRequired;
use App\Models\ContextAudioAsset;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PrepareContextAudio implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 240;

    public function __construct(public int $assetId)
    {
        $this->onQueue('media');
    }

    public function handle(MediaPreparationProvider $provider): void
    {
        $asset = ContextAudioAsset::with('entry')->find($this->assetId);
        if (! $asset || ! in_array($asset->status, ['queued', 'preparing'], true)) {
            return;
        }
        $asset->update(['status' => 'preparing', 'error_code' => null, 'error_message' => null]);
        $asset->entry->update(['status' => 'preparing']);

        try {
            $prepared = $provider->prepare($asset);
            $asset->update([
                'prepared_path' => $prepared->path,
                'prepared_mime' => $prepared->mime,
                'prepared_bytes' => $prepared->bytes,
                'metadata' => array_merge($asset->metadata ?? [], $prepared->metadata),
                'status' => 'prepared',
            ]);
            $asset->entry->update(['status' => 'prepared']);
            TranscribeContextAudio::dispatch($asset->id);
        } catch (MediaPreparationRequired $exception) {
            $asset->update(['status' => 'failed', 'error_code' => 'media_preparation_required', 'error_message' => $exception->getMessage()]);
            $asset->entry->update(['status' => 'failed']);
        }
    }
}
