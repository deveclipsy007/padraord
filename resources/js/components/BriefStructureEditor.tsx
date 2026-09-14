import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { ArrowDown, ArrowUp, CalendarDays, Pencil, Plus } from 'lucide-react';
import { Drawer, Field, FormErrors } from './FormControls';
import { ConfirmButton } from './ConfirmButton';
import { localDate, type EventBriefData } from './EventBriefEditor';
import type { SourceEntry } from './BriefSourcePanel';

export type Requirement = {
    id: number;
    area: string;
    requirement: string;
    quantity: string;
    unit: string;
    priority: string;
    classification: string;
    status: string;
    source: string;
    evidence_segment_ids: number[];
    supplier_need_id: number | null;
};
export type ProgramBlock = {
    id: number;
    sequence: number;
    title: string;
    description: string | null;
    starts_at: string;
    ends_at: string;
    location_note: string | null;
    responsible_area: string | null;
    attendees_estimate: number | null;
    source: string;
    evidence_segment_ids: number[];
};
type Preview = { id: number; brief_revision: number; items: Requirement[] };
export type BriefStructureProps = {
    caseId: number;
    eventBrief: EventBriefData;
    briefRequirements: Requirement[];
    programBlocks: ProgramBlock[];
    programWarnings: { ids: number[]; message: string }[];
    needsPreview: Preview | null;
    briefOptions: { areas: string[] };
    sourceEntries: SourceEntry[];
};
const labels: Record<string, string> = {
    fact: 'Fato informado',
    hypothesis: 'Hipótese',
    conflict: 'Informação conflitante',
    unknown: 'Ainda desconhecido',
    obrigatorio: 'Obrigatório',
    desejavel: 'Desejável',
    opcional: 'Opcional',
    draft: 'Rascunho',
    confirmed: 'Confirmado',
    cancelled: 'Cancelado',
    iluminacao: 'Iluminação',
    cenografia: 'Cenografia',
    climatizacao: 'Climatização',
    seguranca: 'Segurança',
    traducao: 'Tradução',
    mobiliario: 'Mobiliário',
    decoracao: 'Decoração',
    saude: 'Saúde',
    comunicacao: 'Comunicação',
    sinalizacao: 'Sinalização',
    licencas: 'Licenças',
    logistica: 'Logística',
    producao: 'Produção',
};
const label = (key: string) => labels[key] ?? key.charAt(0).toUpperCase() + key.slice(1);

function EvidencePicker({
    entries,
    selected,
    onChange,
}: {
    entries: SourceEntry[];
    selected: number[];
    onChange: (ids: number[]) => void;
}) {
    if (!entries.some((e) => e.segments.length)) return null;
    return (
        <details>
            <summary>Vincular trechos da reunião ({selected.length} selecionados)</summary>
            {entries
                .filter((e) => e.segments.length)
                .map((e) => (
                    <div key={e.id}>
                        <strong>{e.title || `Reunião #${e.id}`}</strong>
                        {e.segments.map((s) => (
                            <label key={s.id} className="event-brief__source-record">
                                <input
                                    type="checkbox"
                                    checked={selected.includes(s.id)}
                                    onChange={(event) =>
                                        onChange(event.target.checked ? [...selected, s.id] : selected.filter((id) => id !== s.id))
                                    }
                                />
                                {Math.floor(s.start_ms / 60000)}:{String(Math.floor(s.start_ms / 1000) % 60).padStart(2, '0')} ·{' '}
                                {s.speaker_name || s.speaker_key}: {s.text}
                            </label>
                        ))}
                    </div>
                ))}
        </details>
    );
}

function RequirementForm({
    caseId,
    revision,
    item,
    areas,
    entries,
    onClose,
}: {
    caseId: number;
    revision: number;
    item?: Requirement;
    areas: string[];
    entries: SourceEntry[];
    onClose: () => void;
}) {
    const form = useForm({
        revision,
        area: item?.area ?? 'producao',
        requirement: item?.requirement ?? '',
        quantity: item?.quantity ?? '1',
        unit: item?.unit ?? 'pacote',
        priority: item?.priority ?? 'obrigatorio',
        classification: item?.classification ?? 'unknown',
        status: item?.status ?? 'draft',
        source: item?.source ?? '',
        evidence_segment_ids: item?.evidence_segment_ids ?? [],
    });
    const base = `/opportunities/${caseId}/event-brief/requirements`;
    return (
        <form
            className="form-grid"
            onSubmit={(e) => {
                e.preventDefault();
                const options = { preserveScroll: true, onSuccess: onClose };
                if (item) form.patch(`${base}/${item.id}`, options);
                else form.post(base, options);
            }}
        >
            <Field label="Área de produção">
                <select value={form.data.area} onChange={(e) => form.setData('area', e.target.value)}>
                    {areas.map((a) => (
                        <option key={a} value={a}>
                            {label(a)}
                        </option>
                    ))}
                </select>
            </Field>
            <Field label="O que precisa ser entregue">
                <textarea required value={form.data.requirement} onChange={(e) => form.setData('requirement', e.target.value)} />
            </Field>
            <div className="two-fields">
                <Field label="Quantidade">
                    <input
                        type="number"
                        min="0.01"
                        step="0.01"
                        value={form.data.quantity}
                        onChange={(e) => form.setData('quantity', e.target.value)}
                    />
                </Field>
                <Field label="Unidade">
                    <input required value={form.data.unit} onChange={(e) => form.setData('unit', e.target.value)} />
                </Field>
            </div>
            <Field label="Prioridade do requisito">
                <select value={form.data.priority} onChange={(e) => form.setData('priority', e.target.value)}>
                    {['obrigatorio', 'desejavel', 'opcional'].map((s) => (
                        <option key={s} value={s}>
                            {label(s)}
                        </option>
                    ))}
                </select>
            </Field>
            <Field label="O que sabemos sobre este requisito">
                <select value={form.data.classification} onChange={(e) => form.setData('classification', e.target.value)}>
                    {['fact', 'hypothesis', 'conflict', 'unknown'].map((s) => (
                        <option key={s} value={s}>
                            {label(s)}
                        </option>
                    ))}
                </select>
            </Field>
            <Field label="Estado do requisito">
                <select value={form.data.status} onChange={(e) => form.setData('status', e.target.value)}>
                    {['draft', 'confirmed', 'cancelled'].map((s) => (
                        <option key={s} value={s}>
                            {label(s)}
                        </option>
                    ))}
                </select>
            </Field>
            <Field label="Origem desta informação">
                <textarea
                    required
                    value={form.data.source}
                    onChange={(e) => form.setData('source', e.target.value)}
                    placeholder="Reunião de alinhamento, solicitação do cliente…"
                />
            </Field>
            <p>Hipóteses e conflitos continuam identificados, mesmo depois de sua decisão de cotar.</p>
            <EvidencePicker
                entries={entries}
                selected={form.data.evidence_segment_ids}
                onChange={(ids) => form.setData('evidence_segment_ids', ids)}
            />
            <FormErrors errors={form.errors} />
            <button className="button button-primary" disabled={form.processing}>
                Salvar requisito
            </button>
        </form>
    );
}
function ProgramForm({
    caseId,
    revision,
    item,
    brief,
    areas,
    entries,
    onClose,
}: {
    caseId: number;
    revision: number;
    item?: ProgramBlock;
    brief: EventBriefData;
    areas: string[];
    entries: SourceEntry[];
    onClose: () => void;
}) {
    const form = useForm({
        revision,
        title: item?.title ?? '',
        description: item?.description ?? '',
        starts_at: localDate(item?.starts_at ?? brief.starts_at, brief.timezone),
        ends_at: localDate(item?.ends_at ?? brief.ends_at, brief.timezone),
        location_note: item?.location_note ?? '',
        responsible_area: item?.responsible_area ?? '',
        attendees_estimate: item?.attendees_estimate ?? null,
        source: item?.source ?? '',
        evidence_segment_ids: item?.evidence_segment_ids ?? [],
    });
    const base = `/opportunities/${caseId}/event-brief/program`;
    return (
        <form
            className="form-grid"
            onSubmit={(e) => {
                e.preventDefault();
                const options = { preserveScroll: true, onSuccess: onClose };
                if (item) form.patch(`${base}/${item.id}`, options);
                else form.post(base, options);
            }}
        >
            <Field label="Nome do bloco">
                <input required value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
            </Field>
            <div className="two-fields">
                <Field label="Início do bloco">
                    <input
                        required
                        type="datetime-local"
                        value={form.data.starts_at}
                        onChange={(e) => form.setData('starts_at', e.target.value)}
                    />
                </Field>
                <Field label="Fim do bloco">
                    <input
                        required
                        type="datetime-local"
                        value={form.data.ends_at}
                        onChange={(e) => form.setData('ends_at', e.target.value)}
                    />
                </Field>
            </div>
            <p>Horário de {brief.timezone}. Blocos paralelos geram um aviso para conferência.</p>
            <Field label="Descrição do bloco">
                <textarea value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
            </Field>
            <Field label="Local ou sala">
                <input value={form.data.location_note} onChange={(e) => form.setData('location_note', e.target.value)} />
            </Field>
            <Field label="Área responsável">
                <select value={form.data.responsible_area} onChange={(e) => form.setData('responsible_area', e.target.value)}>
                    <option value="">A definir</option>
                    {areas.map((a) => (
                        <option key={a} value={a}>
                            {label(a)}
                        </option>
                    ))}
                </select>
            </Field>
            <Field label="Participantes estimados">
                <input
                    type="number"
                    min={0}
                    value={form.data.attendees_estimate ?? ''}
                    onChange={(e) => form.setData('attendees_estimate', e.target.value ? Number(e.target.value) : null)}
                />
            </Field>
            <Field label="Origem da programação">
                <input required value={form.data.source} onChange={(e) => form.setData('source', e.target.value)} />
            </Field>
            <EvidencePicker
                entries={entries}
                selected={form.data.evidence_segment_ids}
                onChange={(ids) => form.setData('evidence_segment_ids', ids)}
            />
            <FormErrors errors={form.errors} />
            <button className="button button-primary" disabled={form.processing}>
                Salvar bloco
            </button>
        </form>
    );
}
function NeedsConfirmation({
    caseId,
    preview,
    onError,
}: {
    caseId: number;
    preview: Preview;
    onError: (e: Record<string, string>) => void;
}) {
    const form = useForm({ requirement_ids: preview.items.map((i) => i.id), confirm_uncertain_ids: [] as number[] });
    const toggle = (key: 'requirement_ids' | 'confirm_uncertain_ids', id: number, checked: boolean) =>
        form.setData(key, checked ? [...form.data[key], id] : form.data[key].filter((v) => v !== id));
    return (
        <form
            className="event-brief__notice form-grid"
            onSubmit={(e) => {
                e.preventDefault();
                form.post(`/opportunities/${caseId}/event-brief/needs-preview/${preview.id}/confirm`, { preserveScroll: true, onError });
            }}
        >
            <h3>Confira o que vai para cotação</h3>
            <p>Prévia da revisão {preview.brief_revision}. Selecione os itens e confirme separadamente as informações incertas.</p>
            {preview.items.map((i) => (
                <div key={i.id} className="event-brief__preview-item">
                    <label>
                        <input
                            type="checkbox"
                            checked={form.data.requirement_ids.includes(i.id)}
                            onChange={(e) => toggle('requirement_ids', i.id, e.target.checked)}
                        />
                        {i.requirement} · {i.quantity} {i.unit}
                    </label>
                    <small>
                        {label(i.classification)} · {i.source}
                    </small>
                    {i.classification !== 'fact' && (
                        <label>
                            <input
                                type="checkbox"
                                checked={form.data.confirm_uncertain_ids.includes(i.id)}
                                onChange={(e) => toggle('confirm_uncertain_ids', i.id, e.target.checked)}
                            />
                            Confirmo que este item pode ser cotado apesar da incerteza
                        </label>
                    )}
                </div>
            ))}
            <FormErrors errors={form.errors} />
            <button className="button button-primary" disabled={form.processing || !form.data.requirement_ids.length}>
                Confirmar necessidades selecionadas
            </button>
        </form>
    );
}
export function BriefStructureEditor({
    caseId,
    eventBrief,
    briefRequirements,
    programBlocks,
    programWarnings,
    needsPreview,
    briefOptions,
    sourceEntries,
    revision,
    approved,
}: BriefStructureProps & { revision: number; approved: boolean }) {
    const [requirement, setRequirement] = useState<Requirement | null | undefined>();
    const [program, setProgram] = useState<ProgramBlock | null | undefined>();
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [dragged, setDragged] = useState<number | null>(null);
    const base = `/opportunities/${caseId}/event-brief`;
    const reorder = (from: number, to: number) => {
        const ids = programBlocks.map((b) => b.id);
        const [id] = ids.splice(from, 1);
        ids.splice(to, 0, id);
        router.patch(`${base}/program-order`, { revision, ids }, { preserveScroll: true, onError: setErrors });
    };
    const date = (iso: string) =>
        new Date(iso).toLocaleString('pt-BR', {
            timeZone: eventBrief.timezone,
            day: '2-digit',
            month: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
        });
    return (
        <>
            <section className="event-brief__section event-brief__structure">
                <header>
                    <div>
                        <span className="eyebrow">REQUISITOS POR ÁREA</span>
                        <h3>O que a produção precisa entregar</h3>
                        <p>{briefRequirements.filter((r) => r.status !== 'cancelled').length} requisitos ativos · incertezas preservadas</p>
                    </div>
                    <button className="button button-subtle" disabled={approved} onClick={() => setRequirement(null)}>
                        <Plus size={15} /> Novo requisito
                    </button>
                </header>
                {!briefRequirements.length && (
                    <p className="event-brief__empty">
                        Transforme o resumo do escopo em entregas por área, como palco, som ou credenciamento.
                    </p>
                )}
                <div className="event-brief__cards">
                    {briefRequirements.map((r) => (
                        <article key={r.id} className="event-brief__card">
                            <span className="eyebrow">
                                {label(r.area)} · {label(r.priority)}
                            </span>
                            <h4>{r.requirement}</h4>
                            <p>
                                {r.quantity} {r.unit} · {label(r.classification)} · {label(r.status)}
                            </p>
                            <small>Origem: {r.source}</small>
                            {r.supplier_need_id ? (
                                <p>Necessidade #{r.supplier_need_id} criada para cotação.</p>
                            ) : (
                                <button className="button button-subtle" disabled={approved} onClick={() => setRequirement(r)}>
                                    <Pencil size={14} /> Editar requisito
                                </button>
                            )}
                        </article>
                    ))}
                </div>
                <button
                    className="button button-subtle"
                    disabled={!briefRequirements.some((r) => r.status !== 'cancelled' && !r.supplier_need_id)}
                    onClick={() => router.post(`${base}/needs-preview`, {}, { preserveScroll: true, onError: setErrors })}
                >
                    Preparar necessidades para cotação
                </button>
                {needsPreview && <NeedsConfirmation key={needsPreview.id} caseId={caseId} preview={needsPreview} onError={setErrors} />}
            </section>
            <section className="event-brief__section event-brief__structure">
                <header>
                    <div>
                        <span className="eyebrow">PROGRAMAÇÃO</span>
                        <h3>O evento, do início ao fim</h3>
                        <p>Arraste para organizar a sequência ou use as setas. Horários permanecem explícitos.</p>
                    </div>
                    <button
                        className="button button-subtle"
                        disabled={approved || !eventBrief.starts_at || !eventBrief.ends_at}
                        onClick={() => setProgram(null)}
                    >
                        <CalendarDays size={15} /> Adicionar bloco
                    </button>
                </header>
                {programWarnings.map((w, i) => (
                    <p key={i} role="status" className="event-brief__notice">
                        {w.message}
                    </p>
                ))}
                {!programBlocks.length && <p className="event-brief__empty">Defina a janela do evento para organizar sua programação.</p>}
                <ol className="event-brief__timeline">
                    {programBlocks.map((b, index) => (
                        <li
                            key={b.id}
                            draggable={!approved}
                            onDragStart={() => setDragged(index)}
                            onDragOver={(e) => e.preventDefault()}
                            onDrop={(e) => {
                                e.preventDefault();
                                if (dragged !== null && dragged !== index) reorder(dragged, index);
                                setDragged(null);
                            }}
                        >
                            <span className="event-brief__timeline-dot">{index + 1}</span>
                            <div>
                                <time>
                                    {date(b.starts_at)} → {date(b.ends_at)}
                                </time>
                                <h4>{b.title}</h4>
                                <p>{b.description}</p>
                                <small>
                                    {b.location_note || 'Local a definir'} ·{' '}
                                    {b.responsible_area ? label(b.responsible_area) : 'Responsável a definir'} · {b.source}
                                </small>
                                <div className="event-brief__actions">
                                    <button
                                        className="icon-button"
                                        aria-label={`Subir ${b.title}`}
                                        disabled={approved || index === 0}
                                        onClick={() => reorder(index, index - 1)}
                                    >
                                        <ArrowUp size={15} />
                                    </button>
                                    <button
                                        className="icon-button"
                                        aria-label={`Descer ${b.title}`}
                                        disabled={approved || index === programBlocks.length - 1}
                                        onClick={() => reorder(index, index + 1)}
                                    >
                                        <ArrowDown size={15} />
                                    </button>
                                    <button className="button button-subtle" disabled={approved} onClick={() => setProgram(b)}>
                                        Editar bloco
                                    </button>
                                    {!approved && (
                                        <ConfirmButton
                                            message="Remover este bloco da programação? A revisão anterior permanece no histórico."
                                            onConfirm={() =>
                                                router.delete(`${base}/program/${b.id}`, {
                                                    data: { revision },
                                                    preserveScroll: true,
                                                    onError: setErrors,
                                                })
                                            }
                                        >
                                            Remover bloco
                                        </ConfirmButton>
                                    )}
                                </div>
                            </div>
                        </li>
                    ))}
                </ol>
            </section>
            <FormErrors errors={errors} />
            <Drawer
                title={requirement ? 'Editar requisito' : 'Novo requisito'}
                open={requirement !== undefined}
                onClose={() => setRequirement(undefined)}
            >
                {requirement !== undefined && (
                    <RequirementForm
                        key={requirement?.id ?? 'new'}
                        caseId={caseId}
                        revision={revision}
                        item={requirement ?? undefined}
                        areas={briefOptions.areas}
                        entries={sourceEntries}
                        onClose={() => setRequirement(undefined)}
                    />
                )}
            </Drawer>
            <Drawer
                title={program ? 'Editar bloco' : 'Novo bloco da programação'}
                open={program !== undefined}
                onClose={() => setProgram(undefined)}
            >
                {program !== undefined && (
                    <ProgramForm
                        key={program?.id ?? 'new'}
                        caseId={caseId}
                        revision={revision}
                        item={program ?? undefined}
                        brief={eventBrief}
                        areas={briefOptions.areas}
                        entries={sourceEntries}
                        onClose={() => setProgram(undefined)}
                    />
                )}
            </Drawer>
        </>
    );
}
