import { router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { FileCheck2, Link2, Plus, RotateCcw, Save, Trash2 } from 'lucide-react';
import { ConfirmButton } from './ConfirmButton';
import { Field, FormErrors } from './FormControls';
import { BriefStructureEditor, type BriefStructureProps } from './BriefStructureEditor';
import { BriefSourcePanel, type SourceEntry, type BriefSource } from './BriefSourcePanel';

type Value = string | number | boolean | null | string[] | Record<string, string>[];
export type EventBriefData = Record<string, Value> & {
    id: number;
    revision: number;
    status: string;
    completeness_score: number;
    missing_critical: string[];
    timezone: string;
};
type Definition = {
    key: string;
    label: string;
    type?: 'number' | 'money' | 'datetime-local' | 'textarea' | 'checkbox' | 'select' | 'lines';
    options?: string[];
};
const confidence = ['unknown', 'estimated', 'confirmed'];
const words: Record<string, string> = {
    unknown: 'Ainda não informado',
    estimated: 'Estimado / em estudo',
    confirmed: 'Confirmado',
    presencial: 'Presencial',
    hibrido: 'Híbrido',
    online: 'Online',
    congresso: 'Congresso',
    convencao: 'Convenção',
    conferencia: 'Conferência',
    seminario: 'Seminário',
    workshop: 'Workshop',
    treinamento: 'Treinamento',
    feira: 'Feira',
    exposicao: 'Exposição',
    lancamento: 'Lançamento',
    ativacao: 'Ativação de marca',
    premiacao: 'Premiação',
    confraternizacao: 'Confraternização',
    casamento: 'Casamento',
    formatura: 'Formatura',
    aniversario: 'Aniversário',
    show: 'Show',
    festival: 'Festival',
    esportivo: 'Esportivo',
    institucional: 'Institucional',
    religioso: 'Religioso',
};
const groups: { title: string; description: string; fields: Definition[] }[] = [
    {
        title: 'Identidade e datas',
        description: 'Qual evento vamos realizar e qual é a sua janela de operação?',
        fields: [
            { key: 'event_name', label: 'Nome do evento' },
            { key: 'event_type', label: 'Tipo de evento', type: 'select' },
            { key: 'event_format', label: 'Formato', type: 'select', options: ['presencial', 'hibrido', 'online'] },
            { key: 'edition', label: 'Edição' },
            { key: 'is_recurring', label: 'Evento recorrente', type: 'checkbox' },
            { key: 'previous_opportunity_id', label: 'Número do caso anterior', type: 'number' },
            { key: 'starts_at', label: 'Início do evento', type: 'datetime-local' },
            { key: 'ends_at', label: 'Término do evento', type: 'datetime-local' },
            { key: 'setup_starts_at', label: 'Início da montagem', type: 'datetime-local' },
            { key: 'teardown_ends_at', label: 'Fim da desmontagem', type: 'datetime-local' },
            { key: 'timezone', label: 'Fuso horário' },
            { key: 'date_confidence', label: 'Confirmação da data', type: 'select', options: confidence },
            { key: 'alternative_dates', label: 'Datas alternativas — uma por linha', type: 'lines' },
        ],
    },
    {
        title: 'Local e público',
        description: 'Dimensão, acesso e necessidades das pessoas que vão participar.',
        fields: [
            { key: 'venue_id', label: 'Local cadastrado', type: 'select' },
            { key: 'venue_status', label: 'Confirmação do local', type: 'select', options: confidence },
            { key: 'city', label: 'Cidade' },
            { key: 'state', label: 'Estado (UF)' },
            { key: 'location_note', label: 'Nome e informações do local', type: 'textarea' },
            { key: 'venue_requirements', label: 'Exigências do local', type: 'textarea' },
            { key: 'audience_expected_min', label: 'Público mínimo estimado', type: 'number' },
            { key: 'audience_expected_max', label: 'Público máximo estimado', type: 'number' },
            { key: 'audience_confidence', label: 'Confirmação do público', type: 'select', options: confidence },
            { key: 'audience_profile', label: 'Perfil do público', type: 'textarea' },
            { key: 'audience_segments', label: 'Grupos do público — um por linha', type: 'lines' },
            { key: 'has_vip', label: 'Há convidados VIP', type: 'checkbox' },
            { key: 'vip_notes', label: 'Atendimento aos convidados VIP', type: 'textarea' },
            { key: 'accessibility_requirements', label: 'Acessibilidade e inclusão', type: 'textarea' },
        ],
    },
    {
        title: 'Objetivo e proposta',
        description: 'O resultado esperado e o que faz parte desta entrega.',
        fields: [
            { key: 'objective', label: 'Objetivo do evento', type: 'textarea' },
            { key: 'scope_summary', label: 'Resumo do escopo', type: 'textarea' },
            { key: 'key_message', label: 'Mensagem principal', type: 'textarea' },
            { key: 'tone', label: 'Tom da experiência' },
            { key: 'brand_notes', label: 'Orientações da marca', type: 'textarea' },
            { key: 'brand_assets', label: 'Referências de marca — uma por linha', type: 'lines' },
            { key: 'references', label: 'Referências da experiência — uma por linha', type: 'lines' },
        ],
    },
    {
        title: 'Investimento e condições',
        description: 'Valores declarados pelo cliente; não representam aprovação do orçamento.',
        fields: [
            { key: 'budget_declared_cents', label: 'Investimento declarado (R$)', type: 'money' },
            { key: 'budget_range_min_cents', label: 'Faixa mínima (R$)', type: 'money' },
            { key: 'budget_range_max_cents', label: 'Faixa máxima (R$)', type: 'money' },
            { key: 'budget_confidence', label: 'Confirmação do investimento', type: 'select', options: confidence },
            { key: 'budget_includes_taxes', label: 'Valores incluem impostos', type: 'checkbox' },
            { key: 'payment_expectation', label: 'Expectativa de pagamento', type: 'textarea' },
            { key: 'budget_notes', label: 'Observações do investimento', type: 'textarea' },
            { key: 'restrictions_notes', label: 'Restrições já conhecidas', type: 'textarea' },
            { key: 'references_notes', label: 'Referências recebidas anteriormente', type: 'textarea' },
            { key: 'legacy_date_note', label: 'Data informada no contexto original' },
        ],
    },
];
const lists = [
    {
        key: 'success_criteria',
        title: 'Como vamos medir o sucesso',
        columns: [
            ['metric', 'Indicador'],
            ['target', 'Meta'],
        ],
    },
    {
        key: 'constraints',
        title: 'Limites da entrega',
        columns: [
            ['kind', 'Tipo'],
            ['description', 'Descrição'],
        ],
    },
    {
        key: 'risks',
        title: 'Riscos e prevenção',
        columns: [
            ['severity', 'Gravidade'],
            ['description', 'Risco'],
            ['mitigation', 'Como prevenir'],
        ],
    },
    {
        key: 'deadlines',
        title: 'Prazos importantes',
        columns: [
            ['title', 'Entrega'],
            ['due_at', 'Prazo'],
        ],
    },
];
export const briefFieldLabels: Record<string, string> = Object.fromEntries([
    ...groups.flatMap((g) => g.fields.map((f) => [f.key, f.label])),
    ...lists.map((l) => [l.key, l.title]),
    ['budget', 'Investimento declarado ou faixa'],
    ['location', 'Local do evento'],
]);
export function localDate(value: Value | undefined, timezone: string): string {
    if (!value || typeof value !== 'string') return '';
    const d = new Date(value);
    if (Number.isNaN(d.valueOf())) return '';
    const p = Object.fromEntries(
        new Intl.DateTimeFormat('sv-SE', {
            timeZone: timezone,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            hourCycle: 'h23',
        })
            .formatToParts(d)
            .map((p) => [p.type, p.value]),
    );
    return `${p.year}-${p.month}-${p.day}T${p.hour}:${p.minute}`;
}
function initial(brief: EventBriefData): Record<string, Value> {
    const result: Record<string, Value> = {};
    for (const f of groups.flatMap((g) => g.fields))
        result[f.key] =
            f.type === 'datetime-local'
                ? localDate(brief[f.key], brief.timezone)
                : (brief[f.key] ?? (f.type === 'checkbox' ? false : f.type === 'lines' ? [] : null));
    for (const list of lists) result[list.key] = brief[list.key] ?? [];
    return result;
}
export type EventBriefEditorProps = BriefStructureProps & {
    eventBrief: EventBriefData;
    briefSources: BriefSource[];
    sourceEntries: SourceEntry[];
    briefOptions: { event_types: string[]; areas: string[]; venues: { id: number; name: string }[] };
};
export function EventBriefEditor(props: EventBriefEditorProps) {
    const { caseId, eventBrief: brief, briefOptions, briefSources, sourceEntries } = props;
    const form = useForm({ revision: brief.revision, fields: initial(brief) });
    const reopen = useForm({ revision: brief.revision, reason: '' });
    const [showReopen, setShowReopen] = useState(false);
    const [sourceField, setSourceField] = useState<string | null>(null);
    const [actionErrors, setActionErrors] = useState<Record<string, string>>({});
    const base = `/opportunities/${caseId}/event-brief`;
    const approved = brief.status === 'approved';
    const reset = (b: EventBriefData) => {
        const value = { revision: b.revision, fields: initial(b) };
        form.setData(value);
        form.setDefaults(value);
    };
    useEffect(() => {
        if (!form.isDirty) reset(brief);
    }, [brief.revision]);
    const set = (key: string, value: Value) => form.setData('fields', { ...form.data.fields, [key]: value });
    return (
        <section className="event-brief" id="briefing-estruturado" aria-label="Briefing do evento">
            <header className="event-brief__header">
                <div>
                    <span className="eyebrow">DEFINIÇÃO DO EVENTO · REVISÃO {brief.revision}</span>
                    <h2>Um briefing que orienta a entrega</h2>
                    <p>
                        {approved
                            ? 'Versão aprovada e preservada no histórico.'
                            : 'Preencha o que está confirmado e mantenha as incertezas visíveis.'}
                    </p>
                </div>
                <div className="event-brief__score">
                    <strong>{brief.completeness_score}%</strong>
                    <span>dos campos essenciais</span>
                    <progress aria-label="Preenchimento dos campos essenciais" max={100} value={brief.completeness_score} />
                </div>
            </header>
            {!!brief.missing_critical.length && (
                <div className="event-brief__notice">
                    <strong>Para aprovar, falta completar:</strong>
                    <p>{brief.missing_critical.map((k) => briefFieldLabels[k] ?? k).join(' · ')}</p>
                </div>
            )}
            {form.data.revision !== brief.revision && form.isDirty && (
                <div role="alert" className="event-brief__notice">
                    Existe uma revisão mais recente. Seu texto está preservado neste formulário.{' '}
                    <ConfirmButton
                        message="Carregar a revisão atual e substituir sua edição ainda não salva?"
                        onConfirm={() => reset(brief)}
                    >
                        Carregar revisão {brief.revision}
                    </ConfirmButton>
                </div>
            )}
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.patch(base, { preserveScroll: true, onSuccess: (p) => reset(p.props.eventBrief as EventBriefData) });
                }}
            >
                <fieldset disabled={approved || form.processing} className="event-brief__fieldset">
                    {groups.map((group, index) => (
                        <details key={group.title} className="event-brief__section" open={index === 0}>
                            <summary>
                                <span>{String(index + 1).padStart(2, '0')}</span>
                                <strong>{group.title}</strong>
                            </summary>
                            <p>{group.description}</p>
                            <div className="event-brief__fields form-grid">
                                {group.fields.map((f) => (
                                    <div
                                        key={f.key}
                                        className={f.type === 'textarea' || f.type === 'lines' ? 'event-brief__wide' : undefined}
                                    >
                                        <Field label={f.label} error={(form.errors as Record<string, string>)[`fields.${f.key}`]}>
                                            {f.type === 'checkbox' ? (
                                                <input
                                                    type="checkbox"
                                                    checked={Boolean(form.data.fields[f.key])}
                                                    onChange={(e) => set(f.key, e.target.checked)}
                                                />
                                            ) : f.type === 'select' ? (
                                                <select
                                                    value={String(form.data.fields[f.key] ?? '')}
                                                    onChange={(e) =>
                                                        set(
                                                            f.key,
                                                            f.key === 'venue_id'
                                                                ? e.target.value
                                                                    ? Number(e.target.value)
                                                                    : null
                                                                : e.target.value || null,
                                                        )
                                                    }
                                                >
                                                    <option value="">Selecionar…</option>
                                                    {f.key === 'venue_id'
                                                        ? briefOptions.venues.map((v) => (
                                                              <option key={v.id} value={v.id}>
                                                                  {v.name}
                                                              </option>
                                                          ))
                                                        : (f.key === 'event_type' ? briefOptions.event_types : (f.options ?? [])).map(
                                                              (o) => (
                                                                  <option key={o} value={o}>
                                                                      {words[o] ?? o}
                                                                  </option>
                                                              ),
                                                          )}
                                                </select>
                                            ) : f.type === 'textarea' || f.type === 'lines' ? (
                                                <textarea
                                                    rows={3}
                                                    value={
                                                        f.type === 'lines'
                                                            ? (form.data.fields[f.key] as string[]).join('\n')
                                                            : String(form.data.fields[f.key] ?? '')
                                                    }
                                                    onChange={(e) =>
                                                        set(f.key, f.type === 'lines' ? e.target.value.split('\n') : e.target.value)
                                                    }
                                                />
                                            ) : (
                                                <input
                                                    type={f.type === 'money' ? 'number' : (f.type ?? 'text')}
                                                    min={f.type === 'money' || f.type === 'number' ? 0 : undefined}
                                                    step={f.type === 'money' ? '0.01' : f.type === 'number' ? 1 : undefined}
                                                    value={
                                                        f.type === 'money'
                                                            ? form.data.fields[f.key] === null
                                                                ? ''
                                                                : Number(form.data.fields[f.key]) / 100
                                                            : String(form.data.fields[f.key] ?? '')
                                                    }
                                                    onChange={(e) =>
                                                        set(
                                                            f.key,
                                                            f.type === 'money'
                                                                ? e.target.value === ''
                                                                    ? null
                                                                    : Math.round(Number(e.target.value) * 100)
                                                                : f.type === 'number'
                                                                  ? e.target.value === ''
                                                                      ? null
                                                                      : Number(e.target.value)
                                                                  : e.target.value,
                                                        )
                                                    }
                                                />
                                            )}
                                        </Field>
                                        {!!sourceEntries.length && (
                                            <button type="button" className="event-brief__source" onClick={() => setSourceField(f.key)}>
                                                <Link2 size={13} />
                                                {briefSources.some((s) => s.field_path === f.key)
                                                    ? 'Consultar origem'
                                                    : 'Vincular trecho da reunião'}
                                            </button>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </details>
                    ))}
                    <details className="event-brief__section">
                        <summary>
                            <span>05</span>
                            <strong>Sucesso, riscos e compromissos</strong>
                        </summary>
                        <div className="form-grid">
                            {lists.map((list) => (
                                <section key={list.key} className="event-brief__list">
                                    <h3>{list.title}</h3>
                                    {(form.data.fields[list.key] as Record<string, string>[]).map((row, index) => (
                                        <div key={index} className="event-brief__list-row">
                                            {list.columns.map(([key, label]) => (
                                                <Field key={key} label={label}>
                                                    {key === 'severity' || key === 'kind' ? (
                                                        <select
                                                            value={row[key] ?? ''}
                                                            onChange={(e) =>
                                                                set(
                                                                    list.key,
                                                                    (form.data.fields[list.key] as Record<string, string>[]).map((r, i) =>
                                                                        i === index ? { ...r, [key]: e.target.value } : r,
                                                                    ),
                                                                )
                                                            }
                                                        >
                                                            <option value="">Selecionar…</option>
                                                            {(key === 'severity'
                                                                ? [
                                                                      ['low', 'Baixa'],
                                                                      ['medium', 'Média'],
                                                                      ['high', 'Alta'],
                                                                      ['critical', 'Crítica'],
                                                                  ]
                                                                : [
                                                                      ['technical', 'Técnico'],
                                                                      ['schedule', 'Horário'],
                                                                      ['budget', 'Orçamento'],
                                                                      ['accessibility', 'Acessibilidade'],
                                                                      ['legal', 'Legal'],
                                                                      ['venue', 'Local'],
                                                                      ['other', 'Outro'],
                                                                  ]
                                                            ).map(([v, l]) => (
                                                                <option key={v} value={v}>
                                                                    {l}
                                                                </option>
                                                            ))}
                                                        </select>
                                                    ) : (
                                                        <input
                                                            type={key === 'due_at' ? 'datetime-local' : 'text'}
                                                            value={row[key] ?? ''}
                                                            onChange={(e) =>
                                                                set(
                                                                    list.key,
                                                                    (form.data.fields[list.key] as Record<string, string>[]).map((r, i) =>
                                                                        i === index ? { ...r, [key]: e.target.value } : r,
                                                                    ),
                                                                )
                                                            }
                                                        />
                                                    )}
                                                </Field>
                                            ))}
                                            <button
                                                type="button"
                                                className="icon-button"
                                                aria-label={`Remover item ${index + 1} de ${list.title}`}
                                                onClick={() =>
                                                    set(
                                                        list.key,
                                                        (form.data.fields[list.key] as Record<string, string>[]).filter(
                                                            (_, i) => i !== index,
                                                        ),
                                                    )
                                                }
                                            >
                                                <Trash2 size={16} />
                                            </button>
                                        </div>
                                    ))}
                                    <button
                                        type="button"
                                        className="button button-subtle"
                                        onClick={() =>
                                            set(list.key, [
                                                ...(form.data.fields[list.key] as Record<string, string>[]),
                                                Object.fromEntries(list.columns.map(([k]) => [k, ''])),
                                            ])
                                        }
                                    >
                                        <Plus size={14} /> Adicionar {list.title.toLowerCase()}
                                    </button>
                                </section>
                            ))}
                        </div>
                    </details>
                    <FormErrors errors={form.errors} />
                    {!approved && (
                        <div className="event-brief__actions">
                            <button className="button button-primary" disabled={form.processing || !form.isDirty}>
                                <Save size={15} /> Salvar briefing do evento
                            </button>
                            <span>Salvar cria uma revisão. A aprovação é uma ação separada.</span>
                        </div>
                    )}
                </fieldset>
            </form>
            <div className="event-brief__actions">
                {approved ? (
                    <button className="button button-subtle" onClick={() => setShowReopen(!showReopen)}>
                        <RotateCcw size={15} /> Reabrir briefing
                    </button>
                ) : (
                    <button
                        className="button button-subtle"
                        disabled={form.isDirty || !!brief.missing_critical.length || form.processing}
                        onClick={() =>
                            router.post(`${base}/approve`, { revision: brief.revision }, { preserveScroll: true, onError: setActionErrors })
                        }
                    >
                        <FileCheck2 size={15} /> Aprovar briefing do evento
                    </button>
                )}
                <span>A aprovação do briefing preserva o escopo; orçamento e contratação têm suas próprias decisões.</span>
            </div>
            <FormErrors errors={actionErrors} />
            {showReopen && (
                <form
                    className="form-grid event-brief__notice"
                    onSubmit={(e) => {
                        e.preventDefault();
                        reopen.transform((d) => ({ ...d, revision: brief.revision }));
                        reopen.post(`${base}/reopen`, { preserveScroll: true, onSuccess: () => setShowReopen(false) });
                    }}
                >
                    <Field label="Motivo da reabertura">
                        <textarea
                            required
                            minLength={10}
                            value={reopen.data.reason}
                            onChange={(e) => reopen.setData('reason', e.target.value)}
                        />
                    </Field>
                    <FormErrors errors={reopen.errors} />
                    <button className="button button-primary" disabled={reopen.processing}>
                        Confirmar reabertura
                    </button>
                </form>
            )}
            <BriefStructureEditor {...props} revision={brief.revision} approved={approved} />
            <BriefSourcePanel
                caseId={caseId}
                brief={brief}
                sources={briefSources}
                entries={sourceEntries}
                field={sourceField}
                onClose={() => setSourceField(null)}
                labels={briefFieldLabels}
            />
        </section>
    );
}
