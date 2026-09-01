<?php

namespace App\Contracts;

use App\Data\TranscriptionResult;
use App\Models\ContextAudioAsset;

interface AudioTranscriber
{
    public function transcribe(ContextAudioAsset $asset): TranscriptionResult;
}
