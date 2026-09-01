import { router } from '@inertiajs/react';
import { FileAudio, LoaderCircle, Upload, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { buildAudioUploadPlan, processingLabel } from './upload';
import { ReviewSegment, SpeakerReview } from './SpeakerReview';

export type ContextAudioConfig = {
    enabled: boolean;
    directMaxBytes: number;
    uploadMaxBytes: number;
    chunkBytes: number;
    maxMinutes: number;
    priceMicrosPerMinute: number;
    latestEntry?: { id: number; status: string; audio?: { status: string; durationMs: number; error?: string | null } | null } | null;
};

type UploadState = { entryId?: number; status: string; progress: number; error?: string; revision?: number; segments?: ReviewSegment[] };

const csrf = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

async function json(response: Response) {
    const payload = await response.json().catch(() => ({})) as { message?: string; errors?: Record<string, string[]> };
    if (!response.ok) throw new Error(Object.values(payload.errors ?? {}).flat()[0] ?? payload.message ?? 'Não foi possível continuar o upload.');
    return payload as Record<string, any>;
}

export function ContextUploadComposer({ caseId, config, onClose }: { caseId: number; config: ContextAudioConfig; onClose: () => void }) {
    const input = useRef<HTMLInputElement>(null);
    const [file, setFile] = useState<File | null>(null);
    const [state, setState] = useState<UploadState>(() => ({ status: config.latestEntry?.audio?.status ?? config.latestEntry?.status ?? 'idle', progress: 0 }));
    const plan = useMemo(() => file ? buildAudioUploadPlan(file.size, config.directMaxBytes, config.uploadMaxBytes, config.chunkBytes) : null, [file, config]);

    async function poll(entryId: number) {
        for (let attempt = 0; attempt < 150; attempt++) {
            const payload = await json(await fetch(`/opportunities/${caseId}/context/${entryId}`, { headers: { Accept: 'application/json' } }));
            const status = payload.entry.audio?.status ?? payload.entry.status;
            setState(current => ({ ...current, status, revision: payload.entry.revision, segments: payload.entry.segments, error: payload.entry.audio?.error_message }));
            if (['review_ready', 'failed', 'uncertain', 'applied'].includes(status)) {
                router.reload({ only: ['contextPreview', 'contextAudio'] });
                return;
            }
            await new Promise(resolve => window.setTimeout(resolve, 4000));
        }
        setState(current => ({ ...current, status: 'uncertain', error: 'O processamento continua em segundo plano. Volte a esta página em alguns minutos.' }));
    }

    useEffect(() => {
        let active = true;
        void (async () => {
            try {
                const payload = await json(await fetch(`/opportunities/${caseId}/context/audio/latest`, { headers: { Accept: 'application/json' } }));
                if (!active || !payload.entry) return;
                const status = payload.entry.audio?.status ?? payload.entry.status;
                setState({ entryId: payload.entry.id, status, progress: 100, revision: payload.entry.revision, segments: payload.entry.segments, error: payload.entry.audio?.error_message });
                if (['queued', 'preparing', 'prepared', 'transcribing', 'transcribed', 'extracting'].includes(status)) void poll(payload.entry.id);
            } catch { /* The upload path remains available even if status lookup fails. */ }
        })();
        return () => { active = false; };
    }, [caseId]);

    async function startProcessing() {
        if (!state.entryId) return;
        setState(current => ({ ...current, status: 'queued', error: undefined }));
        try {
            await json(await fetch(`/opportunities/${caseId}/context/${state.entryId}/process`, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() } }));
            void poll(state.entryId);
        } catch (error) {
            setState(current => ({ ...current, status: 'failed', error: error instanceof Error ? error.message : 'Não foi possível iniciar o processamento.' }));
        }
    }

    async function submit() {
        if (!file || !plan || plan.strategy === 'reject') return;
        setState({ status: 'uploading', progress: 0 });
        try {
            const start = await json(await fetch(`/opportunities/${caseId}/context/audio/uploads`, {
                method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify({ name: file.name, mime: file.type || 'audio/mpeg', bytes: file.size, chunks: plan.chunks }),
            }));
            const uuid = start.upload.uuid as string;
            for (let index = 0; index < plan.chunks; index++) {
                const body = new FormData();
                body.append('chunk', file.slice(index * plan.chunkBytes, Math.min(file.size, (index + 1) * plan.chunkBytes)), `chunk-${index}.part`);
                await json(await fetch(`/opportunities/${caseId}/context/audio/uploads/${uuid}/chunks/${index}`, {
                    method: 'PUT', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() }, body,
                }));
                setState({ status: 'uploading', progress: Math.round(((index + 1) / plan.chunks) * 100) });
            }
            const completed = await json(await fetch(`/opportunities/${caseId}/context/audio/uploads/${uuid}/complete`, {
                method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
            }));
            const entryId = completed.entry.id as number;
            setState({ entryId, status: completed.entry.audio?.status ?? completed.entry.status, progress: 100 });
            void poll(entryId);
        } catch (error) {
            setState(current => ({ ...current, status: 'failed', error: error instanceof Error ? error.message : 'Falha inesperada no upload.' }));
        }
    }

    return <div className="context-audio-composer">
        <div className="context-audio-composer__head"><div><span className="eyebrow">ÁUDIO DA REUNIÃO</span><h3>Enviar e organizar</h3></div><button type="button" className="icon-button" aria-label="Fechar envio de áudio" onClick={onClose}><X size={17}/></button></div>
        <p>Envie até {config.maxMinutes} minutos. O sistema separa participantes, encontra fatos e prepara rascunhos; você confirma antes de alterar o caso.</p>
        {!config.enabled && <div className="context-audio-notice">A chave, a política e os limites da IA precisam estar prontos para processar. O arquivo continuará privado e preservado.</div>}
        <label className="context-audio-drop"><FileAudio size={22}/><span><strong>{file?.name ?? 'Escolher MP3, WAV ou M4A'}</strong><small>{file ? `${(file.size / 1024 / 1024).toFixed(1)} MB` : 'Áudio comprimido de uma hora normalmente fica abaixo de 24 MB.'}</small></span><input ref={input} type="file" accept=".mp3,.wav,.m4a,.mp4" onChange={event => setFile(event.target.files?.[0] ?? null)}/></label>
        {plan?.strategy === 'prepare' && <p className="context-audio-hint">Este arquivo precisa ser compactado antes da OpenAI. O upload será retomável em {plan.chunks} partes.</p>}
        {plan?.strategy === 'reject' && <p role="alert" className="form-error">O arquivo ultrapassa o limite de upload deste ambiente.</p>}
        {state.status !== 'idle' && <div className="context-processing" role="status"><span>{['uploading', 'preparing', 'queued', 'prepared', 'transcribing', 'transcribed', 'extracting'].includes(state.status) ? <LoaderCircle className="is-spinning" size={16}/> : <FileAudio size={16}/>}</span><div><strong>{processingLabel(state.status)}</strong>{state.status === 'uploading' && <progress max={100} value={state.progress}/>} {state.error && <small>{state.error}</small>}</div></div>}
        {state.entryId && state.segments && state.segments.length > 0 && <SpeakerReview caseId={caseId} entryId={state.entryId} revision={state.revision ?? 0} segments={state.segments}/>} 
        {state.status === 'waiting' && config.enabled && <button type="button" className="button button-primary" onClick={startProcessing}><Upload size={16}/> Iniciar processamento</button>}
        <button type="button" className="button button-primary" disabled={!file || !plan || plan.strategy === 'reject' || ['uploading', 'preparing', 'queued', 'transcribing', 'extracting'].includes(state.status)} onClick={submit}><Upload size={16}/> Enviar e organizar</button>
    </div>;
}
