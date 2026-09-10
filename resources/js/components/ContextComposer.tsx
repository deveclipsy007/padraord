import { useForm, usePage } from '@inertiajs/react';
import { Check, Mic2, Send, Sparkles } from 'lucide-react';
import { useEffect, useState } from 'react';
import { ContextAudioConfig, ContextUploadComposer } from './context/ContextUploadComposer';

type Change = {
    module: string;
    field: string;
    current?: string | null;
    suggested: string;
    reason: string;
    kind: string;
    evidence?: string;
    evidence_segment_ids?: number[];
    impacts?: string[];
};
type Preview = { id: number; entryId: number; status: string; actions: Change[] };

const moduleLabels: Record<string, string> = {
    case: 'Dados do caso',
    briefing: 'Briefing',
    viability: 'Viabilidade',
    budget: 'Orçamento',
    documents: 'Documentos',
    production: 'Produção',
    post_event: 'Pós-evento',
};
const defaultAudio: ContextAudioConfig = {
    enabled: false,
    directMaxBytes: 24 * 1024 * 1024,
    uploadMaxBytes: 250 * 1024 * 1024,
    chunkBytes: 5 * 1024 * 1024,
    maxMinutes: 60,
    priceMicrosPerMinute: 0,
};

export function ContextComposer({ caseId, preview, audio }: { caseId: number; preview?: Preview | null; audio?: ContextAudioConfig }) {
    const ai = usePage<{ ai: { status: string } }>().props.ai;
    const audioConfig = audio ?? { ...defaultAudio, enabled: ai.status === 'ready' };
    const entry = useForm({ kind: 'text', phase: 'briefing', body: '' });
    const modules = [...new Set((preview?.actions ?? []).map((change) => change.module))];
    const [selected, setSelected] = useState<number[]>(() => (preview?.actions ?? []).map((_, index) => index));
    const confirm = useForm({ modules, changes: selected });
    const [audioOpen, setAudioOpen] = useState(false);
    useEffect(() => setSelected((preview?.actions ?? []).map((_, index) => index)), [preview?.id]);
    return (
        <section className="context-composer ui-surface ui-surface--elevated ui-surface--pad-lg">
            <div className="context-composer__heading">
                <span>
                    <Sparkles size={16} /> Adicionar contexto
                </span>
                <small>O sistema organiza; você confirma.</small>
            </div>
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    entry.post(`/opportunities/${caseId}/context`, { preserveScroll: true, onSuccess: () => entry.reset('body') });
                }}
            >
                <div className="context-composer__controls">
                    <select
                        aria-label="Tipo de contexto"
                        value={entry.data.phase}
                        onChange={(event) => entry.setData('phase', event.target.value)}
                    >
                        <option value="briefing">Reunião de briefing</option>
                        <option value="commercial">Atualização comercial</option>
                        <option value="technical">Reunião técnica</option>
                        <option value="production">Produção</option>
                        <option value="post_event">Pós-evento</option>
                    </select>
                    <button type="button" className="button button-subtle" onClick={() => setAudioOpen((current) => !current)}>
                        <Mic2 size={17} /> Enviar áudio
                    </button>
                </div>
                <textarea
                    rows={4}
                    value={entry.data.body}
                    onChange={(event) => entry.setData('body', event.target.value)}
                    placeholder="Cole a transcrição, uma conversa ou uma atualização. Ex.: Objetivo: integrar a liderança…"
                />
                {entry.errors.body && (
                    <p role="alert" className="form-error">
                        {entry.errors.body}
                    </p>
                )}
                <button className="button button-primary" disabled={entry.processing || entry.data.body.trim().length < 3}>
                    <Send size={15} /> {entry.processing ? 'Organizando…' : 'Preparar revisão'}
                </button>
            </form>
            {audioOpen && <ContextUploadComposer caseId={caseId} config={audioConfig} onClose={() => setAudioOpen(false)} />}
            {preview?.status === 'preview' && (
                <div className="consolidated-review">
                    <div className="consolidated-review__heading">
                        <div>
                            <span className="eyebrow">REVISÃO CONSOLIDADA</span>
                            <h3>{preview.actions.length} alterações preparadas</h3>
                        </div>
                        <span className="status-badge status-badge--warning">Não aplicadas</span>
                    </div>
                    {modules.map((module) => (
                        <details key={module} open>
                            <summary>
                                {moduleLabels[module] ?? module} · {preview.actions.filter((change) => change.module === module).length}
                            </summary>
                            {preview.actions
                                .map((change, changeIndex) => ({ change, changeIndex }))
                                .filter((item) => item.change.module === module)
                                .map(({ change, changeIndex }) => (
                                    <article
                                        className={`smart-change${selected.includes(changeIndex) ? ' is-selected' : ''}`}
                                        key={`${change.field}-${changeIndex}`}
                                    >
                                        <div>
                                            <label className="smart-change__select">
                                                <input
                                                    type="checkbox"
                                                    checked={selected.includes(changeIndex)}
                                                    onChange={(event) =>
                                                        setSelected((current) =>
                                                            event.target.checked
                                                                ? [...current, changeIndex]
                                                                : current.filter((index) => index !== changeIndex),
                                                        )
                                                    }
                                                />
                                                <span>{selected.includes(changeIndex) && <Check size={11} />}</span>
                                                <strong>{change.field}</strong>
                                            </label>
                                            <span
                                                className={`status-badge status-badge--${change.kind === 'fact' ? 'success' : 'warning'}`}
                                            >
                                                {change.kind === 'fact' ? 'Fato explícito' : change.kind}
                                            </span>
                                        </div>
                                        <del>{change.current || 'Não informado'}</del>
                                        <ins>{change.suggested}</ins>
                                        <p>{change.reason}</p>
                                        <small>
                                            Fonte:{' '}
                                            {change.evidence
                                                ? `“${change.evidence}”`
                                                : change.evidence_segment_ids?.length
                                                  ? `trecho${change.evidence_segment_ids.length > 1 ? 's' : ''} #${change.evidence_segment_ids.join(', #')}`
                                                  : 'a conferir'}
                                        </small>
                                    </article>
                                ))}
                        </details>
                    ))}
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            const selectedModules = [...new Set(selected.map((index) => preview.actions[index]?.module).filter(Boolean))];
                            confirm.transform(() => ({ modules: selectedModules, changes: selected }));
                            confirm.post(`/opportunities/${caseId}/context/${preview.entryId}/preview/${preview.id}/confirm`, {
                                preserveScroll: true,
                            });
                        }}
                    >
                        <button className="button button-primary" disabled={confirm.processing || selected.length === 0}>
                            Confirmar {selected.length} rascunhos
                        </button>
                        <p>Aprovações, preços e fornecedores continuam pendentes de decisão humana.</p>
                    </form>
                </div>
            )}
        </section>
    );
}
