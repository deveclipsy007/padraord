import { Mic2, FileAudio, ArrowRight } from 'lucide-react';
import { router, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
export type TranscriptSegment = { speaker: string; start: number; end: number; text: string };
export type AudioEntry = {
    id: number;
    status: string;
    seconds: number;
    segments: TranscriptSegment[];
    names: Record<string, string>;
    revision: number;
    forwarded: boolean;
    error: string | null;
    expired: boolean;
};
export type AudioContextData = { enabled: boolean; maxKb: number; priceMicros: number; files: AudioEntry[] };
export function AudioContext({ base, audio }: { base: string; audio: AudioContextData }) {
    const form = useForm<{ audio: File | null }>({ audio: null });
    const input = useRef<HTMLInputElement>(null);
    useEffect(() => {
        if (!audio.files.some((a) => ['queued', 'transcribing'].includes(a.status))) return;
        const timer = window.setInterval(() => router.reload({ only: ['audio'] }), 4000);
        return () => window.clearInterval(timer);
    }, [audio.files.map((a) => a.status).join(',')]);
    return (
        <section
            className={`audio-studio${form.processing || audio.files.some((file) => ['queued', 'transcribing'].includes(file.status)) ? ' is-processing' : ''}`}
            aria-label="Áudio do briefing"
        >
            <header className="audio-studio__intro">
                <span className="audio-studio__symbol">
                    <Mic2 size={25} />
                </span>
                <div>
                    <span className="eyebrow">COMECE PELA CONVERSA</span>
                    <h3>
                        Sua voz.<em> Um briefing mais claro.</em>
                    </h3>
                    <p>Envie a gravação. Confira o entendimento. Decida o que entra no projeto.</p>
                </div>
            </header>
            <div className="audio-wave" aria-hidden="true">
                {[9, 17, 24, 13, 31, 21, 37, 16, 28, 36, 18, 26, 12, 22, 9].map((height, index) => (
                    <i key={index} style={{ height }} />
                ))}
            </div>
            <p className="audio-studio__formats">
                MP3, WAV ou M4A · até {(audio.maxKb / 1024).toFixed(1)} MB e 60 minutos por arquivo. Sem perfil permanente de voz.
            </p>
            {!audio.enabled && <p>A transcrição está desativada. Você pode guardar a gravação aqui ou colar o texto para continuar.</p>}
            <form
                className="form-grid"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(`${base}/audio`, {
                        forceFormData: true,
                        preserveScroll: true,
                        onSuccess: () => {
                            form.reset();
                            if (input.current) input.current.value = '';
                        },
                    });
                }}
            >
                <label className="audio-file-choice">
                    <FileAudio size={23} />
                    <span>
                        <strong>{form.data.audio?.name || 'Escolher áudio da reunião'}</strong>
                        <small>
                            {form.data.audio
                                ? `${(form.data.audio.size / 1024 / 1024).toFixed(1)} MB · pronto para enviar`
                                : 'Selecione uma gravação do seu dispositivo'}
                        </small>
                    </span>
                    <span className="sr-only">Arquivo de áudio</span>
                    <input
                        ref={input}
                        aria-label="Arquivo de áudio"
                        disabled={form.processing}
                        type="file"
                        accept=".mp3,.wav,.m4a"
                        onChange={(e) => form.setData('audio', e.target.files?.[0] ?? null)}
                    />
                </label>
                {form.progress && (
                    <div role="status">
                        <span>Enviando {form.progress.percentage ?? 0}%</span>
                        <progress aria-label="Progresso do envio" max={100} value={form.progress.percentage} />
                    </div>
                )}
                <p role="alert">{form.errors.audio}</p>
                <button className="button button-primary" disabled={form.processing || !form.data.audio}>
                    {form.processing ? 'Enviando áudio…' : 'Preservar áudio privado'} <ArrowRight size={16} />
                </button>
            </form>
            {audio.files.map((file) => (
                <AudioRecord
                    key={`${file.id}-${file.revision}`}
                    file={file}
                    base={base}
                    enabled={audio.enabled}
                    price={audio.priceMicros}
                />
            ))}
        </section>
    );
}
function AudioRecord({ file, base, enabled, price }: { file: AudioEntry; base: string; enabled: boolean; price: number }) {
    const player = useRef<HTMLAudioElement>(null);
    const form = useForm({ revision: file.revision, speaker_names: file.names, segments: file.segments.map((s) => ({ text: s.text })) });
    const [errors, setErrors] = useState<string[]>([]);
    const [busy, setBusy] = useState(false);
    function action(type: string) {
        setBusy(true);
        router.post(
            `${base}/audio/${file.id}/${type}`,
            {},
            { preserveScroll: true, onError: (e) => setErrors(Object.values(e)), onFinish: () => setBusy(false) },
        );
    }
    const speakers = [...new Set(file.segments.map((s) => s.speaker))];
    return (
        <article className="assistance-record">
            <strong>
                Reunião #{file.id} · {Math.ceil(file.seconds / 60)} min ·{' '}
                {
                    (
                        {
                            waiting: 'Aguardando',
                            queued: 'Na fila',
                            transcribing: 'Transcrevendo',
                            transcribed: 'Transcrição disponível',
                            uncertain: 'Tentativa a conferir',
                        } as Record<string, string>
                    )[file.status]
                }
            </strong>
            {file.expired ? (
                <p>Retenção do áudio expirada. Texto preservado.</p>
            ) : (
                <audio ref={player} controls preload="none" style={{ width: '100%', marginTop: 12 }} src={`${base}/audio/${file.id}`} />
            )}
            {file.error && <p role="status">{file.error}</p>}
            {errors.map((e, i) => (
                <p role="alert" key={i}>
                    {e}
                </p>
            ))}
            {file.status === 'waiting' && (
                <>
                    <p>
                        Reserva estimada: US$ {(((Math.ceil(file.seconds / 60) + 1) * price) / 1e6).toFixed(4)}. Organização do texto é uma
                        etapa separada.
                    </p>
                    <button
                        className="button button-subtle"
                        disabled={!enabled || busy || file.expired}
                        onClick={() => action('transcribe')}
                    >
                        Transcrever com participantes
                    </button>
                </>
            )}
            {file.segments.length > 0 && (
                <>
                    <form
                        className="form-grid"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.patch(`${base}/audio/${file.id}`, { preserveScroll: true });
                        }}
                    >
                        <h3>Quem participou?</h3>
                        {speakers.map((speaker, i) => (
                            <label key={speaker}>
                                Pessoa {i + 1}
                                <input
                                    value={form.data.speaker_names[speaker] ?? ''}
                                    placeholder="Nome nesta reunião, por exemplo Rômulo"
                                    onChange={(e) =>
                                        form.setData('speaker_names', { ...form.data.speaker_names, [speaker]: e.target.value })
                                    }
                                />
                            </label>
                        ))}
                        <details>
                            <summary>Conferir e corrigir trechos</summary>
                            {file.segments.map((segment, i) => (
                                <label key={i}>
                                    <button
                                        className="button button-subtle"
                                        type="button"
                                        disabled={file.expired}
                                        onClick={() => {
                                            if (player.current) {
                                                player.current.currentTime = segment.start;
                                                void player.current.play();
                                            }
                                        }}
                                    >
                                        {Math.floor(segment.start / 60)}:{String(Math.floor(segment.start % 60)).padStart(2, '0')} ·{' '}
                                        {form.data.speaker_names[segment.speaker] || `Pessoa ${speakers.indexOf(segment.speaker) + 1}`}
                                    </button>
                                    <textarea
                                        rows={2}
                                        value={form.data.segments[i].text}
                                        onChange={(e) =>
                                            form.setData(
                                                'segments',
                                                form.data.segments.map((s, n) => (n === i ? { text: e.target.value } : s)),
                                            )
                                        }
                                    />
                                </label>
                            ))}
                        </details>
                        {Object.values(form.errors).map((e, i) => (
                            <p role="alert" key={i}>
                                {e}
                            </p>
                        ))}
                        <button className="button button-subtle" disabled={form.processing || !form.isDirty}>
                            Salvar correções
                        </button>
                    </form>
                    <button
                        className="button button-primary"
                        disabled={file.forwarded || form.isDirty || busy}
                        onClick={() => action('forward')}
                    >
                        {file.forwarded ? 'Já adicionado ao contexto' : 'Organizar esta transcrição'}
                    </button>
                </>
            )}
        </article>
    );
}
