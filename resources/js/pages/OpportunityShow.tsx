import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowRight,
    CalendarDays,
    Check,
    CircleAlert,
    Clock3,
    FileText,
    Layers3,
    ListChecks,
    MessageSquareText,
    Route,
    Sparkles,
    WalletCards,
    X,
} from 'lucide-react';
import { FormEvent, useState } from 'react';
import { AppLayout } from '../layout';
import { GlassSurface } from '../components/GlassSurface';

import { NextStep, NextStepData, Preparation } from '../components/NextStep';
import { CasePageHeader } from '../components/CasePageHeader';
import { ContextComposer } from '../components/ContextComposer';
import { StatusBadge } from '../components/ui/StatusBadge';

type DecisionItem = {
    id: number;
    kind: 'blocker' | 'next_action';
    source: 'blocker' | 'activity';
    title: string;
    reason: string | null;
    owner: string | null;
    ownerId: number | null;
    stage: string;
    severity: 'normal' | 'high' | 'critical';
    importance: 'low' | 'normal' | 'high';
    effort: 'small' | 'medium' | 'large';
    status: string;
    taskId: number | null;
    taskTitle: string | null;
    directWork: number;
    unlockedWork: number;
    dependencyCycle: boolean;
};

type BlockerHistory = Omit<DecisionItem, 'kind' | 'source' | 'ownerId' | 'taskId'> & {
    resolutionNote: string | null;
    reopenReason: string | null;
    resolvedAt: string | null;
    reopenedAt: string | null;
};

type Props = {
    nextStep: NextStepData;
    preparation: Preparation;
    briefingRevision: number;
    opportunity: {
        id: number;
        title: string;
        clientName: string;
        contactName?: string | null;
        contactEmail?: string | null;
        stage: string;
        stageLabel: string;
        eventDate?: string | null;
        location?: string | null;
        objective?: string | null;
        estimatedValueCents?: number | null;
        nextAction?: string | null;
        briefingStatus: string;
    };
    stages: { id: string; label: string; active: boolean; complete: boolean }[];
    readiness: { items: { key: string; label: string; complete: boolean }[]; complete: number; total: number };
    stats: { messages: number; budgetItems: number; tasks: number };
    history: { action: string; metadata?: { from?: string; to?: string; note?: string }; createdAt: string }[];
    moduleStatuses: { key: string; label: string; status: string; pending: number }[];
    decisionQueue: DecisionItem[];
    nextDecision: DecisionItem | null;
    blockerHistory: BlockerHistory[];
    decisionOptions: {
        tasks: { id: number; title: string; status: string }[];
        users: { id: number; name: string }[];
    };
    contextPreview?: {
        id: number;
        entryId: number;
        status: string;
        actions: {
            module: string;
            field: string;
            current?: string | null;
            suggested: string;
            reason: string;
            kind: string;
            evidence: string;
            impacts?: string[];
        }[];
    } | null;
};
const money = (cents?: number | null) =>
    cents == null
        ? 'A definir'
        : new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL', maximumFractionDigits: 0 }).format(cents / 100);
const moduleLinks = (id: number) => [
    { key: 'journey', label: 'Jornada', href: `/opportunities/${id}/journey`, icon: Route, desc: 'ciclos e decisões' },
    { key: 'briefing', label: 'Briefing', href: `/opportunities/${id}/briefing`, icon: MessageSquareText, desc: 'contexto e lacunas' },
    { key: 'viability', label: 'Viabilidade', href: `/opportunities/${id}/feasibility`, icon: Layers3, desc: 'conceito e entregáveis' },
    { key: 'finance', label: 'Financeiro', href: `/opportunities/${id}/finance`, icon: WalletCards, desc: 'parcelas, baixas e margem' },
    { key: 'budget', label: 'Orçamento', href: `/opportunities/${id}/budget`, icon: WalletCards, desc: 'custos e memória' },
    { key: 'documents', label: 'Documentos', href: `/opportunities/${id}/documents`, icon: FileText, desc: 'propostas e contrato' },
    { key: 'history', label: 'Histórico', href: `/opportunities/${id}/history`, icon: Clock3, desc: 'decisões e fontes' },
];

const severityLabel: Record<DecisionItem['severity'], string> = {
    normal: 'Decisão pendente',
    high: 'Bloqueio relevante',
    critical: 'Bloqueador crítico',
};

const effortLabel: Record<DecisionItem['effort'], string> = { small: 'esforço pequeno', medium: 'esforço médio', large: 'esforço alto' };

function impactLabel(item: Pick<DecisionItem, 'directWork' | 'unlockedWork'>) {
    const direct = `${item.directWork} ${item.directWork === 1 ? 'frente direta' : 'frentes diretas'}`;
    const unlocked = `${item.unlockedWork} ${item.unlockedWork === 1 ? 'etapa destravada' : 'etapas destravadas'}`;

    return `${direct} · ${unlocked}`;
}

export default function OpportunityShow({
    opportunity,
    stages,
    readiness,
    stats,
    history,
    nextStep,
    preparation,
    briefingRevision,
    moduleStatuses,
    decisionQueue,
    nextDecision,
    blockerHistory,
    decisionOptions,
    contextPreview,
}: Props) {
    const [moveOpen, setMoveOpen] = useState(false);
    const [blockerFormOpen, setBlockerFormOpen] = useState(false);
    const [resolvingId, setResolvingId] = useState<number | null>(null);
    const [reopeningId, setReopeningId] = useState<number | null>(null);
    const stageForm = useForm({ stage: opportunity.stage, note: '' });
    const blockerForm = useForm({
        title: '',
        reason: '',
        severity: 'high',
        importance: 'normal',
        effort: 'medium',
        owner_id: '',
        production_task_id: '',
    });
    const resolutionForm = useForm({ resolution: '' });
    const reopenForm = useForm({ reason: '' });

    function move(event: FormEvent) {
        event.preventDefault();
        stageForm.post(`/opportunities/${opportunity.id}/stage`, { onSuccess: () => setMoveOpen(false) });
    }

    function submitBlocker(event: FormEvent) {
        event.preventDefault();
        blockerForm.post(`/opportunities/${opportunity.id}/blockers`, {
            preserveScroll: true,
            onSuccess: () => {
                blockerForm.reset();
                setBlockerFormOpen(false);
            },
        });
    }

    function resolveBlocker(event: FormEvent, blockerId: number) {
        event.preventDefault();
        resolutionForm.post(`/opportunities/${opportunity.id}/blockers/${blockerId}/resolve`, {
            preserveScroll: true,
            onSuccess: () => {
                resolutionForm.reset();
                setResolvingId(null);
            },
        });
    }

    function reopenBlocker(event: FormEvent, blockerId: number) {
        event.preventDefault();
        reopenForm.post(`/opportunities/${opportunity.id}/blockers/${blockerId}/reopen`, {
            preserveScroll: true,
            onSuccess: () => {
                reopenForm.reset();
                setReopeningId(null);
            },
        });
    }
    return (
        <AppLayout>
            <Head title={opportunity.title} />
            <CasePageHeader
                id={opportunity.id}
                eyebrow="Workspace do caso"
                title={opportunity.title}
                client={opportunity.clientName}
                status={opportunity.stageLabel}
                actions={
                    <div className="topbar-actions">
                        <button className="button button-subtle" type="button" onClick={() => setMoveOpen(true)}>
                            Mover etapa <ArrowRight size={15} />
                        </button>
                        <Link className="button button-subtle" href={`/opportunities/${opportunity.id}/edit`}>
                            Editar dados
                        </Link>
                    </div>
                }
            />
            <Link className="production-handoff" href={`/production/events/${opportunity.id}`}>
                <span className="production-handoff__icon">
                    <ListChecks size={24} />
                </span>
                <div>
                    <span className="eyebrow">ÁREA DE EXECUÇÃO</span>
                    <strong>Abrir Central de Produção</strong>
                    <small>Tarefas, equipe, montagem e pós-evento em um espaço próprio.</small>
                </div>
                <ArrowRight size={19} />
            </Link>
            <NextStep
                id={opportunity.id}
                revision={briefingRevision}
                next={nextStep}
                preparation={preparation}
                approved={opportunity.briefingStatus === 'complete'}
            />
            <ContextComposer caseId={opportunity.id} preview={contextPreview} />
            <details className="journey-disclosure">
                <summary className="button button-subtle">Ver trilha comercial detalhada</summary>
                <section className="stage-rail" aria-label="Progresso da oportunidade">
                    {stages.map((stage, index) => (
                        <div className={`stage-node${stage.active ? ' active' : ''}${stage.complete ? ' complete' : ''}`} key={stage.id}>
                            <span>{stage.complete ? <Check size={12} /> : index + 1}</span>
                            <small>{stage.label}</small>
                        </div>
                    ))}
                </section>
            </details>
            <section className="workspace-grid">
                <div className="workspace-main">
                    <div className="module-grid module-grid-v2">
                        {moduleLinks(opportunity.id).map(({ key, label, href, icon: Icon, desc }) => {
                            const state = moduleStatuses.find((item) => item.key === key);
                            return (
                                <Link className="module-card" href={href} key={label} viewTransition>
                                    <span className="module-icon">
                                        <Icon size={18} />
                                    </span>
                                    <div>
                                        <strong>{label}</strong>
                                        <small>{desc}</small>
                                    </div>
                                    {state && (
                                        <StatusBadge
                                            tone={
                                                state.status === 'approved' || state.status === 'complete'
                                                    ? 'success'
                                                    : state.status === 'needs_review' || state.status === 'blocked'
                                                      ? 'warning'
                                                      : state.status === 'empty'
                                                        ? 'neutral'
                                                        : 'info'
                                            }
                                        >
                                            {state.status === 'empty'
                                                ? 'Vazio'
                                                : state.status === 'needs_review'
                                                  ? 'Revisar'
                                                  : state.status === 'approved'
                                                    ? 'Aprovado'
                                                    : state.status === 'complete'
                                                      ? 'Concluído'
                                                      : 'Rascunho'}
                                            {state.pending > 0 ? ` · ${state.pending}` : ''}
                                        </StatusBadge>
                                    )}
                                    <ArrowRight size={15} />
                                </Link>
                            );
                        })}
                    </div>
                    <section className="decision-queue" aria-label="Fila de decisões do caso">
                        <GlassSurface>
                            <div className="panel-heading decision-queue__heading">
                                <div>
                                    <span className="eyebrow">FILA DE DECISÕES</span>
                                    <h2>O que destrava o caso</h2>
                                </div>
                                <button className="button button-subtle" type="button" onClick={() => setBlockerFormOpen((open) => !open)}>
                                    {blockerFormOpen ? 'Fechar registro' : 'Registrar bloqueio'}
                                </button>
                            </div>
                            {nextDecision ? (
                                <article className="decision-queue__primary">
                                    <div className="decision-queue__primary-copy">
                                        <span className="eyebrow">PRÓXIMA DECISÃO RECOMENDADA</span>
                                        <h3>{nextDecision.title}</h3>
                                        <p>{nextDecision.reason || 'Ação registrada sem contexto adicional.'}</p>
                                        <div className="decision-queue__metadata">
                                            <span className={`decision-queue__severity is-${nextDecision.severity}`}>
                                                {severityLabel[nextDecision.severity]}
                                            </span>
                                            <span>{nextDecision.owner || 'Sem responsável definido'}</span>
                                            <span>{impactLabel(nextDecision)}</span>
                                            {nextDecision.dependencyCycle && (
                                                <span className="decision-queue__chain-warning">Cadeia circular: revisar</span>
                                            )}
                                            <span>{effortLabel[nextDecision.effort]}</span>
                                        </div>
                                    </div>
                                    {nextDecision.kind === 'blocker' && (
                                        <button
                                            className="button button-primary"
                                            type="button"
                                            onClick={() => {
                                                resolutionForm.reset();
                                                setResolvingId(nextDecision.id);
                                            }}
                                        >
                                            Resolver bloqueio
                                        </button>
                                    )}
                                    {nextDecision.kind === 'blocker' && resolvingId === nextDecision.id && (
                                        <form
                                            className="decision-queue__resolution"
                                            onSubmit={(event) => resolveBlocker(event, nextDecision.id)}
                                        >
                                            <label>
                                                Evidência da resolução
                                                <textarea
                                                    rows={3}
                                                    value={resolutionForm.data.resolution}
                                                    onChange={(event) => resolutionForm.setData('resolution', event.target.value)}
                                                    placeholder="O que confirma que este impedimento foi resolvido?"
                                                    required
                                                />
                                            </label>
                                            <div className="form-actions">
                                                <button className="button button-subtle" type="button" onClick={() => setResolvingId(null)}>
                                                    Cancelar
                                                </button>
                                                <button
                                                    className="button button-primary"
                                                    type="submit"
                                                    disabled={resolutionForm.processing}
                                                >
                                                    Confirmar resolução
                                                </button>
                                            </div>
                                        </form>
                                    )}
                                </article>
                            ) : (
                                <div className="decision-queue__empty">
                                    Nenhum bloqueio ou próxima decisão está registrado. Registre apenas o que realmente impede ou destrava o
                                    caso.
                                </div>
                            )}

                            {decisionQueue.length > 1 && (
                                <ol className="decision-queue__list" aria-label="Demais decisões ordenadas">
                                    {decisionQueue.slice(1).map((item) => (
                                        <li key={`${item.kind}-${item.id}`}>
                                            <div>
                                                <strong>{item.title}</strong>
                                                <small>
                                                    {item.owner || 'Sem responsável'} · {impactLabel(item)}
                                                    {item.dependencyCycle ? ' · Cadeia circular: revisar' : ''} · {effortLabel[item.effort]}
                                                </small>
                                            </div>
                                            {item.kind === 'blocker' && (
                                                <button
                                                    className="button button-subtle"
                                                    type="button"
                                                    onClick={() => {
                                                        resolutionForm.reset();
                                                        setResolvingId(item.id);
                                                    }}
                                                >
                                                    Resolver bloqueio
                                                </button>
                                            )}
                                            {item.kind === 'blocker' && resolvingId === item.id && (
                                                <form
                                                    className="decision-queue__resolution"
                                                    onSubmit={(event) => resolveBlocker(event, item.id)}
                                                >
                                                    <label>
                                                        Evidência da resolução
                                                        <textarea
                                                            rows={3}
                                                            value={resolutionForm.data.resolution}
                                                            onChange={(event) => resolutionForm.setData('resolution', event.target.value)}
                                                            required
                                                        />
                                                    </label>
                                                    <div className="form-actions">
                                                        <button
                                                            className="button button-subtle"
                                                            type="button"
                                                            onClick={() => setResolvingId(null)}
                                                        >
                                                            Cancelar
                                                        </button>
                                                        <button
                                                            className="button button-primary"
                                                            type="submit"
                                                            disabled={resolutionForm.processing}
                                                        >
                                                            Confirmar resolução
                                                        </button>
                                                    </div>
                                                </form>
                                            )}
                                        </li>
                                    ))}
                                </ol>
                            )}

                            {blockerFormOpen && (
                                <form className="decision-queue__form" onSubmit={submitBlocker}>
                                    <label>
                                        Decisão ou bloqueio
                                        <input
                                            value={blockerForm.data.title}
                                            onChange={(event) => blockerForm.setData('title', event.target.value)}
                                            placeholder="Ex.: confirmar acesso da equipe"
                                            required
                                        />
                                    </label>
                                    <label>
                                        O que impede o avanço
                                        <textarea
                                            rows={3}
                                            value={blockerForm.data.reason}
                                            onChange={(event) => blockerForm.setData('reason', event.target.value)}
                                            placeholder="Descreva o fato que precisa ser resolvido."
                                            required
                                        />
                                    </label>
                                    <div className="decision-queue__fields">
                                        <label>
                                            Gravidade
                                            <select
                                                value={blockerForm.data.severity}
                                                onChange={(event) => blockerForm.setData('severity', event.target.value)}
                                            >
                                                <option value="normal">Decisão pendente</option>
                                                <option value="high">Bloqueio relevante</option>
                                                <option value="critical">Bloqueador crítico</option>
                                            </select>
                                        </label>
                                        <label>
                                            Importância
                                            <select
                                                value={blockerForm.data.importance}
                                                onChange={(event) => blockerForm.setData('importance', event.target.value)}
                                            >
                                                <option value="low">Baixa</option>
                                                <option value="normal">Normal</option>
                                                <option value="high">Alta</option>
                                            </select>
                                        </label>
                                        <label>
                                            Esforço
                                            <select
                                                value={blockerForm.data.effort}
                                                onChange={(event) => blockerForm.setData('effort', event.target.value)}
                                            >
                                                <option value="small">Pequeno</option>
                                                <option value="medium">Médio</option>
                                                <option value="large">Alto</option>
                                            </select>
                                        </label>
                                    </div>
                                    <div className="decision-queue__fields">
                                        <label>
                                            Responsável
                                            <select
                                                value={blockerForm.data.owner_id}
                                                onChange={(event) => blockerForm.setData('owner_id', event.target.value)}
                                            >
                                                <option value="">Ainda não definido</option>
                                                {decisionOptions.users.map((user) => (
                                                    <option value={user.id} key={user.id}>
                                                        {user.name}
                                                    </option>
                                                ))}
                                            </select>
                                        </label>
                                        <label className="decision-queue__field-wide">
                                            Tarefa impactada
                                            <select
                                                value={blockerForm.data.production_task_id}
                                                onChange={(event) => blockerForm.setData('production_task_id', event.target.value)}
                                            >
                                                <option value="">Nenhuma tarefa específica</option>
                                                {decisionOptions.tasks.map((task) => (
                                                    <option value={task.id} key={task.id}>
                                                        {task.title}
                                                    </option>
                                                ))}
                                            </select>
                                        </label>
                                    </div>
                                    <div className="form-actions">
                                        <button className="button button-subtle" type="button" onClick={() => setBlockerFormOpen(false)}>
                                            Cancelar
                                        </button>
                                        <button className="button button-primary" type="submit" disabled={blockerForm.processing}>
                                            Salvar bloqueio
                                        </button>
                                    </div>
                                </form>
                            )}

                            {blockerHistory.length > 0 && (
                                <details className="decision-queue__history">
                                    <summary>Histórico de bloqueios</summary>
                                    {blockerHistory.map((blocker) => (
                                        <article key={blocker.id}>
                                            <div>
                                                <strong>{blocker.title}</strong>
                                                <small>
                                                    {blocker.status === 'resolved' ? 'Resolvido' : 'Em aberto'} ·{' '}
                                                    {blocker.resolutionNote || blocker.reason}
                                                </small>
                                            </div>
                                            {blocker.status === 'resolved' && (
                                                <button
                                                    className="button button-subtle"
                                                    type="button"
                                                    onClick={() => {
                                                        reopenForm.reset();
                                                        setReopeningId(blocker.id);
                                                    }}
                                                >
                                                    Reabrir bloqueio
                                                </button>
                                            )}
                                            {reopeningId === blocker.id && (
                                                <form
                                                    className="decision-queue__resolution"
                                                    onSubmit={(event) => reopenBlocker(event, blocker.id)}
                                                >
                                                    <label>
                                                        Motivo da reabertura
                                                        <textarea
                                                            rows={3}
                                                            value={reopenForm.data.reason}
                                                            onChange={(event) => reopenForm.setData('reason', event.target.value)}
                                                            required
                                                        />
                                                    </label>
                                                    <div className="form-actions">
                                                        <button
                                                            className="button button-subtle"
                                                            type="button"
                                                            onClick={() => setReopeningId(null)}
                                                        >
                                                            Cancelar
                                                        </button>
                                                        <button
                                                            className="button button-primary"
                                                            type="submit"
                                                            disabled={reopenForm.processing}
                                                        >
                                                            Confirmar reabertura
                                                        </button>
                                                    </div>
                                                </form>
                                            )}
                                        </article>
                                    ))}
                                </details>
                            )}
                        </GlassSurface>
                    </section>
                    <GlassSurface>
                        <div className="panel-heading">
                            <div>
                                <span className="eyebrow">CONTINUIDADE</span>
                                <h2>Histórico recente</h2>
                            </div>
                            <Clock3 size={17} className="muted-icon" />
                        </div>
                        {history.length === 0 ? (
                            <div className="inline-empty">As decisões desta oportunidade aparecerão aqui.</div>
                        ) : (
                            <div className="timeline">
                                {history.map((item, index) => (
                                    <div className="timeline-item" key={`${item.createdAt}-${index}`}>
                                        <span className="timeline-dot" />
                                        <div>
                                            <strong>
                                                {item.action === 'opportunity.stage_changed' ? 'Etapa atualizada' : item.action}
                                            </strong>
                                            <small>
                                                {item.metadata?.from && `${item.metadata.from} → ${item.metadata.to}`} · {item.createdAt}
                                            </small>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </GlassSurface>
                </div>
                <aside className="workspace-side">
                    <GlassSurface>
                        <div className="panel-heading">
                            <div>
                                <span className="eyebrow">PRONTIDÃO</span>
                                <h2>Antes de avançar</h2>
                            </div>
                            <CircleAlert size={17} className="muted-icon" />
                        </div>
                        <div className="readiness-progress">
                            <span style={{ width: `${(readiness.complete / Math.max(readiness.total, 1)) * 100}%` }} />
                        </div>
                        <div className="readiness-list">
                            {readiness.items.map((item) => (
                                <div className="readiness-item" key={item.key}>
                                    <span className={item.complete ? 'check-circle complete' : 'check-circle'}>
                                        {item.complete ? <Check size={12} /> : <X size={12} />}
                                    </span>
                                    <span>{item.label}</span>
                                </div>
                            ))}
                        </div>
                    </GlassSurface>
                    <GlassSurface className="event-facts">
                        <span className="eyebrow">DADOS DO EVENTO</span>
                        <div className="fact-row">
                            <CalendarDays size={14} />
                            <span>{opportunity.eventDate || 'Data ainda não definida'}</span>
                        </div>
                        <div className="fact-row">
                            <WalletCards size={14} />
                            <span>{money(opportunity.estimatedValueCents)}</span>
                        </div>
                        <div className="fact-row">
                            <MessageSquareText size={14} />
                            <span>{stats.messages} mensagens no briefing</span>
                        </div>
                        <div className="fact-row">
                            <ListChecks size={14} />
                            <span>{stats.tasks} tarefas de produção</span>
                        </div>
                    </GlassSurface>
                </aside>
            </section>
            {moveOpen && (
                <div className="modal-backdrop" role="presentation">
                    <div className="modal" role="dialog" aria-modal="true" aria-labelledby="move-title">
                        <div className="modal-heading">
                            <div>
                                <span className="eyebrow">TRILHA DO EVENTO</span>
                                <h2 id="move-title">Mover oportunidade</h2>
                            </div>
                            <button className="icon-button" type="button" onClick={() => setMoveOpen(false)} aria-label="Fechar">
                                <X size={17} />
                            </button>
                        </div>
                        <form className="form-grid" onSubmit={move}>
                            <label>
                                Próxima etapa
                                <select value={stageForm.data.stage} onChange={(event) => stageForm.setData('stage', event.target.value)}>
                                    {stages.map((stage) => (
                                        <option value={stage.id} key={stage.id}>
                                            {stage.label}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <label>
                                Nota da decisão{' '}
                                <textarea
                                    rows={3}
                                    value={stageForm.data.note}
                                    onChange={(event) => stageForm.setData('note', event.target.value)}
                                    placeholder="O que mudou para a equipe?"
                                />
                            </label>
                            <div className="form-actions">
                                <button className="button button-subtle" type="button" onClick={() => setMoveOpen(false)}>
                                    Cancelar
                                </button>
                                <button className="button button-primary" type="submit" disabled={stageForm.processing}>
                                    Salvar etapa
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
