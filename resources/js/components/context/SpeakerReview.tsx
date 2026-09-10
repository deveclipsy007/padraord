import { Play, Save } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';

export type ReviewSegment = {
    id: number;
    speaker_key: string;
    speaker_name: string | null;
    start_ms: number;
    end_ms: number;
    text: string;
};

const csrf = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
const time = (milliseconds: number) =>
    `${Math.floor(milliseconds / 60000)}:${String(Math.floor((milliseconds % 60000) / 1000)).padStart(2, '0')}`;

export function SpeakerReview({
    caseId,
    entryId,
    revision: initialRevision,
    segments,
}: {
    caseId: number;
    entryId: number;
    revision: number;
    segments: ReviewSegment[];
}) {
    const player = useRef<HTMLAudioElement>(null);
    const speakers = useMemo(() => [...new Set(segments.map((segment) => segment.speaker_key))], [segments]);
    const [names, setNames] = useState<Record<string, string>>(() =>
        Object.fromEntries(speakers.map((key) => [key, segments.find((segment) => segment.speaker_key === key)?.speaker_name ?? ''])),
    );
    const [drafts, setDrafts] = useState(() => segments.map((segment) => ({ id: segment.id, text: segment.text })));
    const [revision, setRevision] = useState(initialRevision);
    const [state, setState] = useState<'idle' | 'saving' | 'saved' | 'failed'>('idle');
    const [error, setError] = useState('');

    async function save() {
        setState('saving');
        setError('');
        const response = await fetch(`/opportunities/${caseId}/context/${entryId}/speakers`, {
            method: 'PATCH',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: JSON.stringify({ revision, speakers: names, segments: drafts }),
        });
        const payload = (await response.json().catch(() => ({}))) as {
            entry?: { revision: number };
            errors?: Record<string, string[]>;
            message?: string;
        };
        if (!response.ok) {
            setState('failed');
            setError(Object.values(payload.errors ?? {}).flat()[0] ?? payload.message ?? 'Não foi possível salvar a revisão.');
            return;
        }
        setRevision(payload.entry?.revision ?? revision + 1);
        setState('saved');
    }

    return (
        <section className="speaker-review">
            <div>
                <span className="eyebrow">REVISÃO DA TRANSCRIÇÃO</span>
                <h4>Quem participou?</h4>
                <p>Os nomes valem somente para esta reunião e podem ser corrigidos.</p>
            </div>
            <audio ref={player} controls preload="none" src={`/opportunities/${caseId}/context/${entryId}/audio`} />
            <div className="speaker-review__people">
                {speakers.map((speaker, index) => (
                    <label key={speaker}>
                        Pessoa {index + 1}
                        <input
                            value={names[speaker] ?? ''}
                            placeholder="Nome, por exemplo Rômulo"
                            onChange={(event) => setNames((current) => ({ ...current, [speaker]: event.target.value }))}
                        />
                    </label>
                ))}
            </div>
            <details>
                <summary>Conferir trechos e fontes</summary>
                <div className="speaker-review__segments">
                    {segments.map((segment, index) => (
                        <label key={segment.id}>
                            <button
                                type="button"
                                className="button button-subtle"
                                onClick={() => {
                                    if (player.current) {
                                        player.current.currentTime = segment.start_ms / 1000;
                                        void player.current.play();
                                    }
                                }}
                            >
                                <Play size={13} /> {time(segment.start_ms)} ·{' '}
                                {names[segment.speaker_key] || `Pessoa ${speakers.indexOf(segment.speaker_key) + 1}`}
                            </button>
                            <textarea
                                rows={2}
                                value={drafts[index]?.text ?? ''}
                                onChange={(event) =>
                                    setDrafts((current) =>
                                        current.map((draft, draftIndex) =>
                                            draftIndex === index ? { ...draft, text: event.target.value } : draft,
                                        ),
                                    )
                                }
                            />
                        </label>
                    ))}
                </div>
            </details>
            {error && (
                <p role="alert" className="form-error">
                    {error}
                </p>
            )}
            <button type="button" className="button button-subtle" disabled={state === 'saving'} onClick={save}>
                <Save size={15} /> {state === 'saving' ? 'Salvando…' : state === 'saved' ? 'Revisão salva' : 'Salvar nomes e correções'}
            </button>
        </section>
    );
}
