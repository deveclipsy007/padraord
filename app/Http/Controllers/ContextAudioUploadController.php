<?php

namespace App\Http\Controllers;

use App\AI\AiConfiguration;
use App\AI\AudioInspector;
use App\Jobs\PrepareContextAudio;
use App\Models\AudioUploadSession;
use App\Models\Opportunity;
use App\Services\ContextAudio\ResumableAudioUpload;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContextAudioUploadController extends Controller
{
    public function show(Request $request, Opportunity $opportunity, AudioUploadSession $session, ResumableAudioUpload $uploads)
    {
        abort_unless($session->opportunity_id === $opportunity->id, 404);

        return response()->json(['upload' => $uploads->progress($session, $request->user())])->header('Cache-Control', 'private, no-store');
    }

    public function start(Request $request, Opportunity $opportunity, ResumableAudioUpload $uploads)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'regex:/\.(mp3|wav|m4a|mp4)$/i'],
            'mime' => ['required', Rule::in(['audio/mpeg', 'audio/wav', 'audio/x-wav', 'audio/mp4', 'video/mp4'])],
            'bytes' => ['required', 'integer', 'min:44', 'max:'.config('ai.audio_upload_max_bytes')],
            'chunks' => ['required', 'integer', 'min:1', 'max:1000'],
            'sha256' => ['nullable', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'prepared_for' => ['nullable', 'integer'],
        ]);
        $session = $uploads->start($opportunity, $request->user(), $data);

        return response()->json(['upload' => ['uuid' => $session->uuid, 'status' => $session->status, 'expires_at' => $session->expires_at]], 201);
    }

    public function chunk(Request $request, Opportunity $opportunity, AudioUploadSession $session, int $index, ResumableAudioUpload $uploads)
    {
        abort_unless($session->opportunity_id === $opportunity->id, 404);
        $request->validate(['chunk' => ['required', 'file', 'max:'.config('ai.audio_chunk_max_kb')]]);
        $session = $uploads->storeChunk($session, $request->user(), $index, $request->file('chunk'));

        return response()->json(['upload' => ['uuid' => $session->uuid, 'received_chunks' => $session->received_chunks, 'received_bytes' => $session->received_bytes]]);
    }

    public function complete(Request $request, Opportunity $opportunity, AudioUploadSession $session, ResumableAudioUpload $uploads, AudioInspector $inspector, AiConfiguration $configuration)
    {
        abort_unless($session->opportunity_id === $opportunity->id, 404);
        $alreadyCompleted = (bool) $session->case_context_entry_id;
        $entry = $uploads->complete($session, $request->user(), $inspector, $configuration);
        $fitsProvider = $entry->audioAsset && ($entry->audioAsset->prepared_path || $entry->audioAsset->original_bytes <= config('ai.audio_direct_max_bytes'));
        $canProcess = $fitsProvider && config('ai.audio_validated') && config('ai.audio_price_micros_per_minute') > 0 && $configuration->publicState()['status'] === 'ready';
        if (! $alreadyCompleted && $entry->audioAsset && $canProcess) {
            PrepareContextAudio::dispatch($entry->audioAsset->id);
        } elseif (! $alreadyCompleted && $entry->audioAsset) {
            $entry->update(['status' => 'waiting']);
            $entry->audioAsset->update(['status' => 'waiting']);
        }
        $entry->refresh()->load('audioAsset');

        return response()->json(['entry' => ['id' => $entry->id, 'status' => $entry->status, 'audio' => ['status' => $entry->audioAsset?->status]]], $alreadyCompleted ? 200 : 201);
    }
}
