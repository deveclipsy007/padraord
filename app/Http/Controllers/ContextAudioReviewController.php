<?php

namespace App\Http\Controllers;

use App\AI\AiConfiguration;
use App\Jobs\ExtractContextIntelligence;
use App\Jobs\PrepareContextAudio;
use App\Models\AuditLog;
use App\Models\CaseContextEntry;
use App\Models\Opportunity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ContextAudioReviewController extends Controller
{
    public function latest(Opportunity $opportunity)
    {
        $entry = CaseContextEntry::with(['audioAsset', 'segments'])
            ->where('opportunity_id', $opportunity->id)
            ->where('kind', 'audio')
            ->latest('id')
            ->first();
        if (! $entry) {
            return response()->json(['entry' => null]);
        }

        return response()->json(['entry' => [
            ...$entry->only(['id', 'status', 'revision']),
            'audio' => $entry->audioAsset?->only(['status', 'duration_ms', 'error_code', 'error_message']),
            'segments' => $entry->segments->map(fn ($segment) => $segment->only(['id', 'speaker_key', 'speaker_name', 'start_ms', 'end_ms', 'text']))->values(),
        ]]);
    }

    public function process(Opportunity $opportunity, CaseContextEntry $entry, AiConfiguration $configuration)
    {
        abort_unless($entry->opportunity_id === $opportunity->id && $entry->kind === 'audio', 404);
        if (! $configuration->audioReady()) {
            throw ValidationException::withMessages(['audio' => 'Configure chave, política, limites e a tarifa por minuto em Administração → Inteligência artificial antes de iniciar uma chamada paga.']);
        }
        $asset = $entry->audioAsset()->firstOrFail();
        if ($asset->status === 'transcribed' && $entry->status === 'waiting') {
            $entry->update(['status' => 'transcribed']);
            $asset->update(['error_code' => null, 'error_message' => null]);
            ExtractContextIntelligence::dispatch($entry->id);

            return response()->json(['entry' => ['id' => $entry->id, 'status' => 'transcribed']], 202);
        }
        if (in_array($asset->status, ['queued', 'preparing', 'prepared', 'transcribing', 'transcribed', 'extracting', 'review_ready'], true)) {
            return response()->json(['entry' => ['id' => $entry->id, 'status' => $entry->status]], 202);
        }
        if (! in_array($asset->status, ['waiting', 'failed'], true)) {
            throw ValidationException::withMessages(['audio' => 'Esta tentativa precisa de conferência antes de ser repetida.']);
        }
        $entry->update(['status' => 'queued']);
        $asset->update(['status' => 'queued', 'error_code' => null, 'error_message' => null]);
        PrepareContextAudio::dispatch($asset->id);

        return response()->json(['entry' => ['id' => $entry->id, 'status' => 'queued']], 202);
    }

    public function audio(Opportunity $opportunity, CaseContextEntry $entry)
    {
        abort_unless($entry->opportunity_id === $opportunity->id && $entry->kind === 'audio', 404);
        abort_if($entry->expires_at?->isPast(), 410, 'A retenção deste áudio expirou. A transcrição permanece preservada.');
        $asset = $entry->audioAsset()->firstOrFail();
        abort_unless(Storage::disk('local')->exists($asset->original_path), 404);

        $response = response()->file(Storage::disk('local')->path($asset->original_path), [
            'Content-Type' => $asset->original_mime,
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    public function update(Request $request, Opportunity $opportunity, CaseContextEntry $entry)
    {
        abort_unless($entry->opportunity_id === $opportunity->id && $entry->kind === 'audio', 404);
        $data = $request->validate([
            'revision' => ['required', 'integer', 'min:0'],
            'speakers' => ['required', 'array', 'max:100'],
            'speakers.*' => ['nullable', 'string', 'max:100'],
            'segments' => ['required', 'array', 'max:5000'],
            'segments.*.id' => ['required', 'integer', 'distinct'],
            'segments.*.text' => ['required', 'string', 'max:4000'],
        ]);
        $fresh = DB::transaction(function () use ($entry, $data, $request, $opportunity): CaseContextEntry {
            $locked = CaseContextEntry::whereKey($entry->id)->lockForUpdate()->firstOrFail();
            if ($locked->revision !== $data['revision']) {
                throw ValidationException::withMessages(['audio' => 'A transcrição mudou. Atualize e compare antes de salvar.']);
            }
            $segments = $locked->segments()->get();
            if ($segments->count() !== count($data['segments']) || $segments->pluck('id')->sort()->values()->all() !== collect($data['segments'])->pluck('id')->sort()->values()->all()) {
                throw ValidationException::withMessages(['audio' => 'Não altere a quantidade ou a identidade dos trechos.']);
            }
            $allowedSpeakers = $segments->pluck('speaker_key')->unique()->all();
            if (array_diff(array_keys($data['speakers']), $allowedSpeakers)) {
                throw ValidationException::withMessages(['audio' => 'Um participante não pertence a esta transcrição.']);
            }
            $texts = collect($data['segments'])->keyBy('id');
            foreach ($segments as $segment) {
                $segment->update([
                    'speaker_name' => trim((string) ($data['speakers'][$segment->speaker_key] ?? '')) ?: null,
                    'text' => $texts[$segment->id]['text'],
                ]);
            }
            $locked->update(['revision' => $locked->revision + 1]);
            $locked->audioAsset()->increment('transcript_revision');
            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'context.transcript_corrected',
                'subject_type' => Opportunity::class,
                'subject_id' => $opportunity->id,
                'metadata' => ['entry_id' => $locked->id, 'revision' => $locked->revision],
            ]);

            return $locked->fresh();
        }, 3);

        return response()->json(['entry' => ['id' => $fresh->id, 'revision' => $fresh->revision]]);
    }
}
