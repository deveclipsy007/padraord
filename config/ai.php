<?php

return [
    'audio_validated' => (bool) env('AI_AUDIO_VALIDATED', false),
    'audio_price_micros_per_minute' => (int) env('AI_AUDIO_PRICE_MICROS_PER_MINUTE', 0),
    'audio_pricing_version' => env('AI_AUDIO_PRICING_VERSION', 'manual-v1'),
    'audio_max_seconds' => (int) env('AI_AUDIO_MAX_SECONDS', 3600),
    'audio_direct_max_bytes' => (int) env('AI_AUDIO_DIRECT_MAX_BYTES', 24 * 1024 * 1024),
    'audio_upload_max_bytes' => (int) env('AI_AUDIO_UPLOAD_MAX_BYTES', 250 * 1024 * 1024),
    'audio_chunk_max_kb' => (int) env('AI_AUDIO_CHUNK_MAX_KB', 5120),
    'media_preparation_driver' => env('AI_MEDIA_PREPARATION_DRIVER', 'passthrough'),
    'audio_transcription_model' => env('AI_AUDIO_TRANSCRIPTION_MODEL', 'gpt-4o-transcribe-diarize'),
    'audio_http_timeout' => (int) env('AI_AUDIO_HTTP_TIMEOUT', 600),
    'context_model' => env('AI_CONTEXT_MODEL', 'gpt-5.6-luna'),
    'context_http_timeout' => (int) env('AI_CONTEXT_HTTP_TIMEOUT', 120),
    'context_pricing_version' => env('AI_CONTEXT_PRICING_VERSION', 'settings-v1'),
    'mode' => env('AI_MODE', 'manual'),
    'data_policy_approved' => (bool) env('AI_DATA_POLICY_APPROVED', false),
];
