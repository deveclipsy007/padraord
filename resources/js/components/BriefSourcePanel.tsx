import { useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Link2, Play } from 'lucide-react';
import { Drawer, Field, FormErrors } from './FormControls';
import { localDate, type EventBriefData } from './EventBriefEditor';

type Segment = { id: number; start_ms: number; end_ms: number; text: string; speaker_key: string; speaker_name: string | null };
export type SourceEntry = {
    id: number;
    title: string | null;
    revision: number;
    meeting_date: string | null;
    meeting_kind: string | null;
    retain_forever: boolean;
    participants: string[] | null;
    kind: string;
    segments: Segment[];
};
export type BriefSource = {
    id: number;
    field_path: string;
    case_context_entry_id: number;
    source_snapshot: Segment[];
    value_changed: boolean;
    audio_url: string;
    confirmed_at: string;
    approved_at: string | null;
};
const time = (ms: number) => `${Math.floor(ms / 60000)}:${String(Math.floor(ms / 1000) % 60).padStart(2, '0')}`;

function MeetingMetadata({ caseId, entry, timezone }: { caseId: number; entry: SourceEntry; timezone: string }) {
    const form = useForm({
        revision: entry.revision,
        title: entry.title ?? '',
        meeting_date: localDate(entry.meeting_date, timezone),
        meeting_kind: entry.meeting_kind ?? 'briefing',
        retain_forever: entry.retain_forever,
        participants: entry.participants ?? [],
    });
    return (
        <details className="event-brief__meeting">
            <summary>{entry.title || `Reunião #${entry.id}`} · dados e retenção</summary>
            <form
                className="form-grid"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.patch(`/opportunities/${caseId}/event-brief/context/${entry.id}`, { preserveScroll: true });
                }}
            >
                <Field label="Título da reunião">
                    <input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                </Field>
                <Field label="Data da reunião">
                    <input
                        type="datetime-local"
                        value={form.data.meeting_date}
                        onChange={(e) => form.setData('meeting_date', e.target.value)}
                    />
                </Field>
                <Field label="Tipo de reunião">
                    <select value={form.data.meeting_kind} onChange={(e) => form.setData('meeting_kind', e.target.value)}>
                        {[
                            ['briefing', 'Briefing'],
                            ['alinhamento', 'Alinhamento'],
                            ['tecnica', 'Técnica'],
                            ['comercial', 'Comercial'],
                            ['retrospectiva', 'Retrospectiva'],
                            ['outra', 'Outra'],
                        ].map(([v, l]) => (
                            <option key={v} value={v}>
                                {l}
                            </option>
                        ))}
                    </select>
                </Field>
                <Field label="Participantes — um por linha">
                    <textarea
                        value={form.data.participants.join('\n')}
                        onChange={(e) => form.setData('participants', e.target.value.split('\n'))}
                    />
                </Field>
                <label>
                    <input
                        type="checkbox"
                        checked={form.data.retain_forever}
                        onChange={(e) => form.setData('retain_forever', e.target.checked)}
                    />
                    Preservar esta gravação sem prazo de expiração
                </label>
                <small>Fontes de uma versão aprovada são preservadas mesmo quando esta opção está desligada.</small>
                <FormErrors errors={form.errors} />
                <button className="button button-subtle" disabled={form.processing}>
                    Salvar dados da reunião
                </button>
            </form>
        </details>
    );
}
export function BriefSourcePanel({
    caseId,
    brief,
    sources,
    entries,
    field,
    onClose,
    labels,
}: {
    caseId: number;
    brief: EventBriefData;
    sources: BriefSource[];
    entries: SourceEntry[];
    field: string | null;
    onClose: () => void;
    labels: Record<string, string>;
}) {
    const [viewField, setViewField] = useState<string | null>(null);
    const currentField = field ?? viewField;
    const [audio, setAudio] = useState<{ url: string; start: number } | null>(null);
    const [audioError, setAudioError] = useState(false);
    const player = useRef<HTMLAudioElement>(null);
    const form = useForm({
        revision: brief.revision,
        field_path: field ?? '',
        case_context_entry_id: entries[0]?.id ?? 0,
        segment_ids: [] as number[],
    });
    useEffect(() => {
        form.setData({
            revision: brief.revision,
            field_path: currentField ?? '',
            case_context_entry_id: entries[0]?.id ?? 0,
            segment_ids: [],
        });
        setAudio(null);
        setAudioError(false);
    }, [currentField]);
    const entry = entries.find((e) => e.id === form.data.case_context_entry_id);
    const play = (url: string, ms: number) => {
        setAudioError(false);
        if (audio?.url === url && player.current) {
            player.current.currentTime = ms / 1000;
            void player.current.play().catch(() => setAudioError(true));
        } else setAudio({ url, start: ms / 1000 });
    };
    const close = () => {
        setViewField(null);
        onClose();
        setAudio(null);
    };
    return (
        <section className="event-brief__section event-brief__structure">
            <header>
                <div>
                    <span className="eyebrow">RASTREABILIDADE</span>
                    <h3>A origem de cada decisão</h3>
                    <p>Os trechos confirmados preservam falante, texto e instante da gravação.</p>
                </div>
            </header>
            {!sources.length && (
                <p className="event-brief__empty">Use “Vincular trecho da reunião” ao lado de um campo para registrar sua origem.</p>
            )}
            <div className="event-brief__cards">
                {sources.map((s) => (
                    <button
                        key={s.id}
                        type="button"
                        className="event-brief__card event-brief__origin-card"
                        onClick={() => setViewField(s.field_path)}
                    >
                        <Link2 size={16} />
                        <strong>{labels[s.field_path] ?? s.field_path}</strong>
                        <span>
                            {s.value_changed ? 'Origem de uma versão anterior' : 'Origem do valor atual'} · {s.source_snapshot.length}{' '}
                            trecho(s)
                        </span>
                        <small>{s.approved_at ? 'Preservada na aprovação' : 'Confirmação registrada'}</small>
                    </button>
                ))}
            </div>
            {entries.map((e) => (
                <MeetingMetadata key={`${e.id}-${e.revision}`} caseId={caseId} entry={e} timezone={brief.timezone} />
            ))}
            <Drawer title={`Origem · ${labels[currentField ?? ''] ?? 'campo'}`} open={!!currentField} onClose={close}>
                {audio && (
                    <>
                        <audio
                            key={audio.url}
                            ref={player}
                            controls
                            preload="metadata"
                            src={audio.url}
                            onLoadedMetadata={() => {
                                if (player.current) {
                                    player.current.currentTime = audio.start;
                                    void player.current.play().catch(() => setAudioError(true));
                                }
                            }}
                            onError={() => setAudioError(true)}
                            style={{ width: '100%' }}
                        />
                        {audioError && (
                            <p role="status">
                                Não foi possível reproduzir esta gravação. Os trechos transcritos continuam disponíveis abaixo.
                            </p>
                        )}
                    </>
                )}
                {sources
                    .filter((s) => s.field_path === currentField)
                    .map((s) => (
                        <article key={s.id} className="event-brief__source-record">
                            <strong>{s.value_changed ? 'O campo mudou desde esta confirmação' : 'Origem confirmada do campo'}</strong>
                            {s.source_snapshot.map((segment) => (
                                <div key={segment.id}>
                                    <p>
                                        {segment.speaker_name || segment.speaker_key}: {segment.text}
                                    </p>
                                    <button className="button button-subtle" onClick={() => play(s.audio_url, segment.start_ms)}>
                                        <Play size={14} /> Ouvir em {time(segment.start_ms)}
                                    </button>
                                </div>
                            ))}
                        </article>
                    ))}
                {brief.status !== 'approved' && (
                    <form
                        className="form-grid"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(`/opportunities/${caseId}/event-brief/sources`, { preserveScroll: true, onSuccess: close });
                        }}
                    >
                        <h3>Vincular uma origem</h3>
                        <Field label="Reunião de origem">
                            <select
                                value={form.data.case_context_entry_id}
                                onChange={(e) =>
                                    form.setData({ ...form.data, case_context_entry_id: Number(e.target.value), segment_ids: [] })
                                }
                            >
                                {entries.map((e) => (
                                    <option key={e.id} value={e.id}>
                                        {e.title || `Reunião #${e.id}`}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        {!entry?.segments.length && <p>Esta reunião ainda não tem trechos transcritos disponíveis.</p>}
                        {entry?.segments.map((s) => (
                            <div key={s.id} className="event-brief__source-record">
                                <label>
                                    <input
                                        type="checkbox"
                                        checked={form.data.segment_ids.includes(s.id)}
                                        onChange={(e) =>
                                            form.setData(
                                                'segment_ids',
                                                e.target.checked
                                                    ? [...form.data.segment_ids, s.id]
                                                    : form.data.segment_ids.filter((id) => id !== s.id),
                                            )
                                        }
                                    />
                                    {time(s.start_ms)} · {s.speaker_name || s.speaker_key}: {s.text}
                                </label>
                                {entry.kind === 'audio' && (
                                    <button
                                        type="button"
                                        className="button button-subtle"
                                        onClick={() => play(`/opportunities/${caseId}/context/${entry.id}/audio`, s.start_ms)}
                                    >
                                        <Play size={14} /> Ouvir trecho
                                    </button>
                                )}
                            </div>
                        ))}
                        <FormErrors errors={form.errors} />
                        <button className="button button-primary" disabled={form.processing || !form.data.segment_ids.length}>
                            Confirmar origem deste campo
                        </button>
                    </form>
                )}
            </Drawer>
        </section>
    );
}
