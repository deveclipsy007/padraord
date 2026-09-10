<?php

namespace App\Media;

use App\Contracts\MediaPreparationProvider;
use App\Data\PreparedMedia;
use App\Exceptions\MediaPreparationRequired;
use App\Models\ContextAudioAsset;
use Illuminate\Support\Facades\Storage;

class PassThroughMediaPreparationProvider implements MediaPreparationProvider
{
    public function prepare(ContextAudioAsset $asset): PreparedMedia
    {
        if ($asset->prepared_path && $asset->prepared_bytes <= config('ai.audio_direct_max_bytes') && Storage::disk('local')->exists($asset->prepared_path)) {
            return new PreparedMedia($asset->prepared_path, $asset->prepared_mime, $asset->prepared_bytes, ['strategy' => 'browser_prepared']);
        }
        if ($asset->original_bytes > config('ai.audio_direct_max_bytes')) {
            throw new MediaPreparationRequired('Este áudio ultrapassa o limite direto e precisa ser compactado antes da transcrição.');
        }
        if (! Storage::disk('local')->exists($asset->original_path)) {
            throw new MediaPreparationRequired('O arquivo privado original não está disponível.');
        }

        return new PreparedMedia($asset->original_path, $asset->original_mime, $asset->original_bytes, ['strategy' => 'passthrough']);
    }
}
