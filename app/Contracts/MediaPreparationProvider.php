<?php

namespace App\Contracts;

use App\Data\PreparedMedia;
use App\Models\ContextAudioAsset;

interface MediaPreparationProvider
{
    public function prepare(ContextAudioAsset $asset): PreparedMedia;
}
