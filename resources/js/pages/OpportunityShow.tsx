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
    { key: 'budget', label: 'Orçamento', href: `/opportunities/${id}/budget`, icon: WalletCards, desc: 'custos e memória' },
    { key: 'documents', label: 'Documentos', href: `/opportunities/${id}/documents`, icon: FileText, desc: 'propostas e contrato' },
    { key: 'production', label: 'Produção', href: `/opportunities/${id}/production`, icon: ListChecks, desc: 'tarefas e marcos' },
    { key: 'post-event', label: 'Pós-evento', href: `/opportunities/${id}/post-event`, icon: Sparkles, desc: 'memória e aprendizados' },
    { key: 'history', label: 'Histórico', href: `/opportunities/${id}/history`, icon: Clock3, desc: 'decisões e fontes' },
];

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
    contextPreview,
}: Props) {
    const [moveOpen, setMoveOpen] = useState(false);
    const stageForm = useForm({ stage: opportunity.stage, note: '' });
    function move(event: FormEvent) {
        event.preventDefault();
        stageForm.post(`/opportunities/${opportunity.id}/stage`, { onSuccess: () => setMoveOpen(false) });
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
