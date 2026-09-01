<?php

namespace App\Services\ContextAudio;

use App\AI\AiConfiguration;
use App\AI\AudioInspector;
use App\Models\AudioUploadSession;
use App\Models\CaseContextEntry;
use App\Models\ContextAudioAsset;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResumableAudioUpload
{
    public function start(Opportunity $opportunity, User $user, array $data): AudioUploadSession
    {
        return AudioUploadSession::create([
            'uuid' => (string) Str::uuid(),
            'opportunity_id' => $opportunity->id,
            'user_id' => $user->id,
            'original_name' => basename($data['name']),
            'declared_mime' => $data['mime'],
            'expected_bytes' => $data['bytes'],
            'expected_chunks' => $data['chunks'],
            'expected_digest' => $data['sha256'] ?? null,
            'temporary_path' => 'context-audio/uploads',
            'status' => 'uploading',
            'expires_at' => now()->addHours(24),
        ]);
    }

    public function storeChunk(AudioUploadSession $session, User $user, int $index, UploadedFile $chunk): AudioUploadSession
    {
        $this->authorize($session, $user);
        if ($session->status !== 'uploading' || $session->expires_at->isPast() || $index < 0 || $index >= $session->expected_chunks) {
            abort(409, 'Sessão de upload inválida ou expirada.');
        }

        $path = $this->chunkPath($session, $index);
        $disk = Storage::disk('local');
        if ($disk->exists($path)) {
            if (hash_file('sha256', $disk->path($path)) !== hash_file('sha256', $chunk->getRealPath())) {
                abort(409, 'Este fragmento já existe com outro conteúdo.');
            }
        } else {
            $stored = $disk->putFileAs(dirname($path), $chunk, basename($path));
            abort_unless($stored, 500, 'Não foi possível preservar o fragmento.');
        }

        $received = $this->received($session);
        $session->update(['received_chunks' => $received['chunks'], 'received_bytes' => $received['bytes']]);

        return $session->fresh();
    }

    public function complete(AudioUploadSession $session, User $user, AudioInspector $inspector, AiConfiguration $configuration): CaseContextEntry
    {
        $this->authorize($session, $user);
        if ($session->case_context_entry_id) {
            return CaseContextEntry::with('audioAsset')->findOrFail($session->case_context_entry_id);
        }
        if ($session->status !== 'uploading' || $session->expires_at->isPast()) {
            abort(409, 'Sessão de upload inválida ou expirada.');
        }

        $received = $this->received($session);
        if ($received['chunks'] !== $session->expected_chunks || $received['bytes'] !== $session->expected_bytes) {
            throw ValidationException::withMessages(['audio' => 'O upload ainda está incompleto.']);
        }

        $disk = Storage::disk('local');
        $assembled = "context-audio/uploads/{$session->uuid}/assembled.upload";
        $target = fopen($disk->path($assembled), 'wb');
        if ($target === false) {
            abort(500, 'Não foi possível montar o áudio.');
        }
        try {
            for ($index = 0; $index < $session->expected_chunks; $index++) {
                $source = fopen($disk->path($this->chunkPath($session, $index)), 'rb');
                if ($source === false) {
                    throw ValidationException::withMessages(['audio' => 'Um fragmento do upload não foi encontrado.']);
                }
                stream_copy_to_stream($source, $target);
                fclose($source);
            }
        } finally {
            fclose($target);
        }

        $digest = hash_file('sha256', $disk->path($assembled));
        if ($session->expected_digest && ! hash_equals($session->expected_digest, $digest)) {
            $disk->delete($assembled);
            throw ValidationException::withMessages(['audio' => 'A verificação do arquivo falhou. Retome ou reenvie o áudio.']);
        }
        $info = $inspector->inspectPath($disk->path($assembled));

        return DB::transaction(function () use ($session, $user, $configuration, $disk, $assembled, $digest, $info): CaseContextEntry {
            $locked = AudioUploadSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            if ($locked->case_context_entry_id) {
                return CaseContextEntry::with('audioAsset')->findOrFail($locked->case_context_entry_id);
            }
            $existing = CaseContextEntry::where('opportunity_id', $locked->opportunity_id)->where('digest', $digest)->first();
            if ($existing) {
                $locked->update(['case_context_entry_id' => $existing->id, 'calculated_digest' => $digest, 'status' => 'completed']);
                $disk->delete($assembled);

                return $existing->load('audioAsset');
            }

            $finalPath = 'context-audio/original/'.Str::uuid().'.'.$info['extension'];
            abort_unless($disk->move($assembled, $finalPath), 500, 'Não foi possível preservar o áudio final.');
            $entry = CaseContextEntry::create([
                'opportunity_id' => $locked->opportunity_id,
                'user_id' => $user->id,
                'kind' => 'audio',
                'phase' => 'briefing',
                'status' => 'queued',
                'path' => $finalPath,
                'digest' => $digest,
                'metadata' => ['source' => 'context_audio', 'original_name' => $locked->original_name],
                'expires_at' => now()->addDays($configuration->setting()->retention_days),
            ]);
            ContextAudioAsset::create([
                'case_context_entry_id' => $entry->id,
                'original_path' => $finalPath,
                'original_mime' => $info['mime'],
                'original_bytes' => $locked->expected_bytes,
                'duration_ms' => $info['seconds'] * 1000,
                'digest' => $digest,
                'status' => 'queued',
            ]);
            $locked->update(['case_context_entry_id' => $entry->id, 'calculated_digest' => $digest, 'status' => 'completed']);
            $disk->deleteDirectory("context-audio/uploads/{$locked->uuid}/chunks");

            return $entry->load('audioAsset');
        }, 3);
    }

    private function authorize(AudioUploadSession $session, User $user): void
    {
        abort_unless($session->user_id === $user->id, 404);
    }

    private function chunkPath(AudioUploadSession $session, int $index): string
    {
        return "context-audio/uploads/{$session->uuid}/chunks/{$index}.part";
    }

    private function received(AudioUploadSession $session): array
    {
        $disk = Storage::disk('local');
        $chunks = 0;
        $bytes = 0;
        for ($index = 0; $index < $session->expected_chunks; $index++) {
            $path = $this->chunkPath($session, $index);
            if ($disk->exists($path)) {
                $chunks++;
                $bytes += $disk->size($path);
            }
        }

        return compact('chunks', 'bytes');
    }
}
