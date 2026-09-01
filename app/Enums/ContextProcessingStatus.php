<?php

namespace App\Enums;

enum ContextProcessingStatus: string
{
    case Uploading = 'uploading';
    case Inspecting = 'inspecting';
    case Preparing = 'preparing';
    case Queued = 'queued';
    case Transcribing = 'transcribing';
    case Extracting = 'extracting';
    case ReviewReady = 'review_ready';
    case Applying = 'applying';
    case Completed = 'completed';
    case Failed = 'failed';
    case Uncertain = 'uncertain';
}
