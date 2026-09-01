<?php

namespace App\Http\Controllers;

use App\AI\AiConfiguration;
use App\AI\AudioInspector;
use App\Jobs\AnalyzeBriefing;
use App\Jobs\TranscribeBriefing;
use App\Models\AuditLog;
use App\Models\BriefingAudio;
use App\Models\Opportunity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BriefingAudioController extends Controller
{
    public function store(Request $request, Opportunity $opportunity, AudioInspector $inspector, AiConfiguration $config)
    {
        $request->validate(['audio' => ['required', 'file', 'max:'.AudioInspector::maxKilobytes(), 'mimes:mp3,wav,m4a,mp4']]);
        $file = $request->file('audio');
        $info = $inspector->inspect($file);
        $digest = hash_file('sha256', $file->getRealPath());
        if (BriefingAudio::where('opportunity_id', $opportunity->id)->where('digest', $digest)->exists()) {
            return back()->with('success', 'Este áudio já está preservado neste caso. Nenhum processamento duplicado.');
        }
        $path = $file->storeAs('briefing-audio', Str::uuid().'.'.$info['extension'], 'local');
        abort_unless($path, 500, 'Não foi possível preservar o arquivo. Tente novamente.');
        $audio = BriefingAudio::create(['opportunity_id' => $opportunity->id, 'user_id' => $request->user()->id, 'path' => $path, 'mime' => $info['mime'], 'seconds' => $info['seconds'], 'digest' => $digest, 'expires_at' => now()->addDays($config->setting()->retention_days)]);
        AuditLog::create(['user_id' => $request->user()->id, 'action' => 'briefing.audio.uploaded', 'subject_type' => Opportunity::class, 'subject_id' => $opportunity->id, 'metadata' => ['audio_id' => $audio->id, 'seconds' => $audio->seconds]]);

        return back()->with('success', 'Áudio privado preservado. Confira a duração e solicite a transcrição quando o processamento estiver habilitado.');
    }

    public function show(Opportunity $opportunity, BriefingAudio $audio)
    {
        abort_unless($audio->opportunity_id === $opportunity->id, 404);
        abort_if($audio->expires_at->isPast(), 410, 'A retenção deste áudio expirou. A transcrição permanece preservada.');
        abort_unless(Storage::disk('local')->exists($audio->path), 404);

        return response()->file(Storage::disk('local')->path($audio->path), ['Content-Type' => $audio->mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function transcribe(Request $request, Opportunity $opportunity, BriefingAudio $audio, AiConfiguration $config)
    {
        abort_unless($audio->opportunity_id === $opportunity->id, 404);
        if (! config('ai.audio_validated') || ! config('ai.audio_price_micros_per_minute') || $config->publicState()['status'] !== 'ready') {
            throw ValidationException::withMessages(['audio' => 'Transcrição real bloqueada: valide a hospedagem, tarifa de áudio, política e limites. Você pode colar a transcrição e continuar.']);
        }
        abort_if($audio->expires_at->isPast(), 410);
        if (BriefingAudio::whereKey($audio->id)->where('status', 'waiting')->update(['status' => 'queued'])) {
            TranscribeBriefing::dispatch($audio->id);
        }

        return back()->with('success', 'Solicitação registrada. Atualize a página para acompanhar.');
    }

    public function update(Request $request, Opportunity $opportunity, BriefingAudio $audio)
    {
        abort_unless($audio->opportunity_id === $opportunity->id, 404);
        $v = $request->validate(['revision' => ['required', 'integer'], 'speaker_names' => ['sometimes', 'array', 'max:100'], 'speaker_names.*' => ['nullable', 'string', 'max:100'], 'segments' => ['sometimes', 'array', 'max:5000'], 'segments.*.text' => ['required', 'string', 'max:4000']]);
        DB::transaction(function () use ($v, $audio, $request, $opportunity) {
            if (! BriefingAudio::whereKey($audio->id)->where('revision', $v['revision'])->increment('revision')) {
                throw ValidationException::withMessages(['audio' => 'A transcrição mudou. Atualize e compare antes de salvar.']);
            }
            $a = $audio->fresh();
            $segments = $a->segments ?? [];
            if (isset($v['segments'])) {
                if (count($v['segments']) !== count($segments)) {
                    throw ValidationException::withMessages(['audio' => 'Não altere a quantidade de segmentos.']);
                }foreach ($segments as $i => &$segment) {
                    $segment['text'] = $v['segments'][$i]['text'];
                }unset($segment);
            }
            $allowed = array_unique(array_column($segments, 'speaker'));
            $names = array_intersect_key($v['speaker_names'] ?? [], array_flip($allowed));
            $a->update(['segments' => $segments, 'speaker_names' => array_map(fn ($n) => $n ?? '', $names)]);
            AuditLog::create(['user_id' => $request->user()->id, 'action' => 'briefing.transcript.corrected', 'subject_type' => Opportunity::class, 'subject_id' => $opportunity->id, 'metadata' => ['audio_id' => $a->id, 'before' => $audio->segments, 'after' => $segments, 'speaker_names' => $names, 'revision' => $a->revision]]);
        }, 3);

        return back()->with('success', 'Participantes e trechos atualizados nesta reunião.');
    }

    public function forward(Request $request, Opportunity $opportunity, BriefingAudio $audio)
    {
        abort_unless($audio->opportunity_id === $opportunity->id, 404);
        $message = DB::transaction(function () use ($audio, $opportunity) {
            DB::table('briefing_audio')->where('id', $audio->id)->update(['updated_at' => now()]);
            $a = $audio->fresh();
            if ($a->status !== 'transcribed' || $a->forwarded_revision === $a->revision) {
                return null;
            }
            $body = collect($a->segments)->map(fn ($s) => '['.$s['start'].'s · '.($a->speaker_names[$s['speaker']] ?? $s['speaker']).'] '.$s['text'])->implode("\n");
            $message = $opportunity->briefingMessages()->create(['role' => 'user', 'source' => 'audio', 'body' => $body, 'metadata' => ['audio_id' => $a->id, 'transcript_revision' => $a->revision]]);
            $a->update(['forwarded_revision' => $a->revision]);

            return $message;
        }, 3);
        if ($message) {
            $canProcess = in_array(app(AiConfiguration::class)->publicState()['status'], ['ready', 'demo']);
            $opportunity->update(['briefing_status' => $canProcess ? 'processing' : 'manual']);
            if ($canProcess) {
                AnalyzeBriefing::dispatch($message->id);
            }
        }

        return back()->with('success', 'Transcrição preservada no contexto. A organização usa o modo de IA configurado.');
    }
}
