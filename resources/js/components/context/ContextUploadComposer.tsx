import { router } from '@inertiajs/react';
import { FileAudio, LoaderCircle, Upload, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { buildAudioUploadPlan, prepareAudioFile, processingLabel, type AudioUploadPlan } from './upload';
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
    const payload = (await response.json().catch(() => ({}))) as { message?: string; errors?: Record<string, string[]> };
    if (!response.ok)
        throw new Error(Object.values(payload.errors ?? {}).flat()[0] ?? payload.message ?? 'Não foi possível continuar o upload.');
    return payload as Record<string, any>;
}

export function ContextUploadComposer({ caseId, config, onClose }: { caseId: number; config: ContextAudioConfig; onClose: () => void }) {
    const input = useRef<HTMLInputElement>(null);
    useEffect(() => {
        input.current?.focus();
    }, []);
    const mounted = useRef(true);
    const polling = useRef(0);
    const [file, setFile] = useState<File | null>(null);
    const [state, setState] = useState<UploadState>(() => ({
        status: config.latestEntry?.audio?.status ?? config.latestEntry?.status ?? 'idle',
        progress: 0,
    }));
    const plan = useMemo(
        () => (file ? buildAudioUploadPlan(file.size, config.directMaxBytes, config.uploadMaxBytes, config.chunkBytes) : null),
        [file, config],
    );

    async function poll(entryId: number) {
        const generation = ++polling.current;
        for (let attempt = 0; attempt < 150; attempt++) {
            if (!mounted.current || polling.current !== generation) return;
            const payload = await json(
                await fetch(`/opportunities/${caseId}/context/${entryId}`, { headers: { Accept: 'application/json' } }),
            );
            if (!mounted.current || polling.current !== generation) return;
            const status = payload.entry.audio?.status ?? payload.entry.status;
            setState((current) => ({
                ...current,
                status,
                revision: payload.entry.revision,
                segments: payload.entry.segments,
                error: payload.entry.audio?.error_message,
            }));
            if (['review_ready', 'failed', 'uncertain', 'applied', 'waiting'].includes(status)) {
                router.reload({ only: ['contextPreview', 'contextAudio'] });
                return;
            }
            await new Promise((resolve) => window.setTimeout(resolve, 4000));
        }
        setState((current) => ({
            ...current,
            status: 'uncertain',
            error: 'O processamento continua em segundo plano. Volte a esta página em alguns minutos.',
        }));
    }

    useEffect(() => {
        mounted.current = true;
        let active = true;
        void (async () => {
            try {
                const payload = await json(
                    await fetch(`/opportunities/${caseId}/context/audio/latest`, { headers: { Accept: 'application/json' } }),
                );
                if (!active || !payload.entry) return;
                const status = payload.entry.audio?.status ?? payload.entry.status;
                setState({
                    entryId: payload.entry.id,
                    status,
                    progress: 100,
                    revision: payload.entry.revision,
                    segments: payload.entry.segments,
                    error: payload.entry.audio?.error_message,
                });
                if (['queued', 'preparing', 'prepared', 'transcribing', 'transcribed', 'extracting'].includes(status))
                    void poll(payload.entry.id);
            } catch {
                /* The upload path remains available even if status lookup fails. */
            }
        })();
        return () => {
            active = false;
            mounted.current = false;
            polling.current++;
        };
    }, [caseId]);

    async function startProcessing() {
        if (!state.entryId) return;
        setState((current) => ({ ...current, status: 'queued', error: undefined }));
        try {
            await json(
                await fetch(`/opportunities/${caseId}/context/${state.entryId}/process`, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
                }),
            );
            void poll(state.entryId);
        } catch (error) {
            setState((current) => ({
                ...current,
                status: 'failed',
                error: error instanceof Error ? error.message : 'Não foi possível iniciar o processamento.',
            }));
        }
    }

    async function submit() {
        if (!file || !plan || plan.strategy === 'reject') return;
        setState({ status: 'uploading', progress: 0 });
        try {
            const digestOf = async (target: File) =>
                Array.from(new Uint8Array(await crypto.subtle.digest('SHA-256', await target.arrayBuffer())), (byte) =>
                    byte.toString(16).padStart(2, '0'),
                ).join('');
            const uploadOne = async (
                target: File,
                targetPlan: AudioUploadPlan,
                preparedFor?: number,
            ): Promise<{ entryId: number; status: string }> => {
                const digest = await digestOf(target);
                const cacheKey = `rd-audio-upload:${caseId}:${preparedFor ? `prepared:${preparedFor}:` : ''}${digest}`;
                let uuid = localStorage.getItem(cacheKey);
                let received: number[] = [];
                if (uuid) {
                    const response = await fetch(`/opportunities/${caseId}/context/audio/uploads/${uuid}`, {
                        headers: { Accept: 'application/json' },
                    });
                    if ([404, 410].includes(response.status)) {
                        localStorage.removeItem(cacheKey);
                        uuid = null;
                    } else {
                        const progress = await json(response);
                        if (progress.upload.entry_id) return { entryId: progress.upload.entry_id as number, status: 'queued' };
                        if (progress.upload.sha256 !== digest || progress.upload.expected_chunks !== targetPlan.chunks)
                            throw new Error('A configuração deste envio mudou. Selecione novamente o arquivo original.');
                        received = progress.upload.received_indices;
                    }
                }
                if (!uuid) {
                    const start = await json(
                        await fetch(`/opportunities/${caseId}/context/audio/uploads`, {
                            method: 'POST',
                            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                            body: JSON.stringify({
                                name: target.name,
                                mime: target.type || 'audio/mpeg',
                                bytes: target.size,
                                chunks: targetPlan.chunks,
                                sha256: digest,
                                ...(preparedFor ? { prepared_for: preparedFor } : {}),
                            }),
                        }),
                    );
                    uuid = start.upload.uuid as string;
                    localStorage.setItem(cacheKey, uuid);
                }
                for (let index = 0; index < targetPlan.chunks; index++) {
                    if (!mounted.current) throw new Error('Envio pausado porque a tela foi fechada.');
                    if (received.includes(index)) continue;
                    const body = new FormData();
                    body.append(
                        'chunk',
                        target.slice(index * targetPlan.chunkBytes, Math.min(target.size, (index + 1) * targetPlan.chunkBytes)),
                        `chunk-${index}.part`,
                    );
                    await json(
                        await fetch(`/opportunities/${caseId}/context/audio/uploads/${uuid}/chunks/${index}`, {
                            method: 'PUT',
                            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
                            body,
                        }),
                    );
                    setState((current) => ({
                        ...current,
                        status: 'uploading',
                        progress: Math.round(((index + 1) / targetPlan.chunks) * 100),
                    }));
                }
                const completed = await json(
                    await fetch(`/opportunities/${caseId}/context/audio/uploads/${uuid}/complete`, {
                        method: 'POST',
                        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
                    }),
                );
                return { entryId: completed.entry.id as number, status: completed.entry.audio?.status ?? completed.entry.status };
            };

            const original = await uploadOne(file, plan);
            if (plan.strategy === 'prepare') {
                setState({ entryId: original.entryId, status: 'preparing', progress: 0 });
                const prepared = await prepareAudioFile(file, (progress) =>
                    setState((current) => ({ ...current, status: 'preparing', progress })),
                );
                const preparedPlan = buildAudioUploadPlan(prepared.size, config.directMaxBytes, config.uploadMaxBytes, config.chunkBytes);
                if (preparedPlan.strategy !== 'direct')
                    throw new Error(
                        'O áudio compactado ainda excede o limite da API. Exporte um MP3 com bitrate menor ou cole a transcrição.',
                    );
                const ready = await uploadOne(prepared, preparedPlan, original.entryId);
                setState({ entryId: ready.entryId, status: ready.status, progress: 100 });
                void poll(ready.entryId);
                return;
            }
            setState({ entryId: original.entryId, status: original.status, progress: 100 });
            void poll(original.entryId);
        } catch (error) {
            setState((current) => ({
                ...current,
                status: 'failed',
                error: error instanceof Error ? error.message : 'Falha inesperada no upload.',
            }));
        }
    }

    return (
        <div
            className={`context-audio-composer audio-studio${['uploading', 'preparing', 'queued', 'prepared', 'transcribing', 'transcribed', 'extracting'].includes(state.status) ? ' is-processing' : ''}`}
        >
            <div className="context-audio-composer__head">
                <div>
                    <span className="eyebrow">ÁUDIO DA REUNIÃO</span>
                    <h3>
                        Da voz ao <em>próximo passo.</em>
                    </h3>
                </div>
                <button type="button" className="icon-button" aria-label="Fechar envio de áudio" onClick={onClose}>
                    <X size={17} />
                </button>
            </div>
            <p>
                Adicione a gravação da reunião, de até {config.maxMinutes} minutos. Você não precisa preencher o briefing novamente: revise
                o entendimento antes de confirmar qualquer alteração.
            </p>
            <ol className="context-audio-steps" aria-label="Como funciona">
                <li>1. Envie a gravação</li>
                <li>2. Aguarde a organização</li>
                <li>3. Revise e confirme</li>
            </ol>
            <p className="context-audio-next">
                {state.status === 'review_ready'
                    ? 'Próximo passo: confira os participantes e abra a revisão para decidir o que será incluído no caso.'
                    : state.status === 'waiting'
                      ? 'Próximo passo: o arquivo foi recebido. Inicie a organização quando a IA estiver disponível.'
                      : ['uploading', 'preparing'].includes(state.status)
                        ? 'Mantenha esta tela aberta enquanto preparamos o envio. Se houver interrupção, selecione o mesmo arquivo para retomar.'
                        : ['queued', 'prepared', 'transcribing', 'transcribed', 'extracting'].includes(state.status)
                          ? 'Estamos organizando sua reunião. Nenhum dado do caso será alterado sem sua confirmação.'
                          : 'Próximo passo: escolha o áudio da reunião abaixo. Você poderá corrigir o entendimento depois.'}
            </p>
            {!config.enabled && (
                <div className="context-audio-notice">
                    A transcrição ainda não está disponível. Sua gravação fica privada; você pode continuar pelo texto.
                </div>
            )}
            <label className="context-audio-drop">
                <FileAudio size={22} />
                <span>
                    <strong>{file?.name ?? 'Escolher MP3, WAV ou M4A'}</strong>
                    <small>
                        {file
                            ? `${(file.size / 1024 / 1024).toFixed(1)} MB`
                            : 'Para retomar um envio interrompido, selecione o mesmo arquivo.'}
                    </small>
                </span>
                <input
                    ref={input}
                    aria-label="Gravação da reunião"
                    disabled={['uploading', 'preparing'].includes(state.status)}
                    type="file"
                    accept=".mp3,.wav,.m4a,.mp4"
                    onChange={(event) => setFile(event.target.files?.[0] ?? null)}
                />
            </label>
            {plan?.strategy === 'prepare' && (
                <p className="context-audio-hint">
                    Vamos preparar este arquivo grande automaticamente. Mantenha a página aberta; o envio pode ser retomado se a conexão
                    cair.
                </p>
            )}
            {plan?.strategy === 'reject' && (
                <p role="alert" className="form-error">
                    O arquivo ultrapassa o limite de upload deste ambiente.
                </p>
            )}
            {state.status !== 'idle' && (
                <div className="context-processing" role="status">
                    <span>
                        {['uploading', 'preparing', 'queued', 'prepared', 'transcribing', 'transcribed', 'extracting'].includes(
                            state.status,
                        ) ? (
                            <LoaderCircle className="is-spinning" size={16} />
                        ) : (
                            <FileAudio size={16} />
                        )}
                    </span>
                    <div>
                        <strong>{processingLabel(state.status)}</strong>
                        {state.status === 'uploading' && <progress max={100} value={state.progress} />}{' '}
                        {state.error && <small>{state.error}</small>}
                    </div>
                </div>
            )}
            {state.entryId && state.segments && state.segments.length > 0 && (
                <SpeakerReview caseId={caseId} entryId={state.entryId} revision={state.revision ?? 0} segments={state.segments} />
            )}
            {state.status === 'waiting' && config.enabled && (
                <button type="button" className="button button-primary" onClick={startProcessing}>
                    <Upload size={16} /> Iniciar processamento
                </button>
            )}
            {state.status === 'review_ready' && (
                <button type="button" className="button button-primary" onClick={onClose}>
                    Voltar ao caso e revisar sugestões
                </button>
            )}
            {state.status !== 'review_ready' && state.status !== 'waiting' && (
                <button
                    type="button"
                    className="button button-primary"
                    disabled={
                        !file ||
                        !plan ||
                        plan.strategy === 'reject' ||
                        ['uploading', 'preparing', 'queued', 'prepared', 'transcribing', 'transcribed', 'extracting'].includes(state.status)
                    }
                    onClick={submit}
                >
                    <Upload size={16} /> {state.status === 'failed' ? 'Retomar envio do arquivo' : 'Enviar gravação'}
                </button>
            )}
        </div>
    );
}
