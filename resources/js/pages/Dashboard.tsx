import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowRight, ArrowUpRight, CalendarDays, CheckCircle2, CircleAlert, Clock3, Filter, Plus, Search, Sparkles, X } from 'lucide-react';
import { FormEvent, useEffect, useMemo, useState } from 'react';
import { AppLayout, PrimaryButton } from '../layout';
import { TodayQueue, type QueueTask } from '../components/TodayQueue';
import { rankTodayActions } from '../components/operational-decisions';
import { BarSeries } from '../components/charts/BarSeries';
import { formatCurrencyFromCents } from '../components/charts/chart-utils';
import { BentoGrid, BentoItem } from '../components/ui/BentoGrid';
import { PageHeader } from '../components/ui/PageHeader';
import { StatusBadge } from '../components/ui/StatusBadge';
import { Surface } from '../components/ui/Surface';
import { Opportunity, PipelineColumn } from '../types';

type Props = {
    todayQueue: QueueTask[];
    currentUserId: number;
    todayLabel: string;
    aiMode: string;
    opportunities: Opportunity[];
    columns: PipelineColumn[];
    metrics: { activeOpportunities: number; pendingBriefings: number; nextActions: number };
};

const stageColors: Record<string, string> = {
    lead: 'gray',
    qualification: 'blue',
    meeting: 'amber',
    initial_briefing: 'violet',
    viability_offer: 'violet',
    viability_contracted: 'green',
    lost: 'gray',
    cancelled: 'gray',
};

function money(cents: number | null) {
    if (cents === null) return 'Sem valor definido';
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL', maximumFractionDigits: 0 }).format(cents / 100);
}

export default function Dashboard({ opportunities, columns, metrics, todayQueue, currentUserId, todayLabel, aiMode }: Props) {
    const [showForm, setShowForm] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        title: '',
        client_name: '',
        contact_name: '',
        contact_email: '',
        origin: 'other',
        priority: 'normal',
        next_action: '',
        next_action_at: '',
    });
    const grouped = useMemo(
        () =>
            Object.fromEntries(
                columns.map((column) => [column.id, opportunities.filter((opportunity) => opportunity.commercialStage === column.id)]),
            ),
        [columns, opportunities],
    );
    const demoOpportunityId = opportunities[0]?.id;
    const focusActions = useMemo(() => {
        const caseActions: QueueTask[] = opportunities
            .filter(
                (item) =>
                    !item.archived &&
                    !['lost', 'cancelled', 'closed'].includes(item.stage) &&
                    (item.nextAction || item.briefingStatus === 'awaiting_review'),
            )
            .map((item) => ({
                id: item.id,
                kind: item.briefingStatus === 'awaiting_review' ? 'review' : 'case',
                title: item.briefingStatus === 'awaiting_review' ? `Revisar briefing · ${item.title}` : item.nextAction!,
                context: item.title,
                href: item.briefingStatus === 'awaiting_review' ? `/opportunities/${item.id}/briefing` : `/opportunities/${item.id}`,
                owner: item.ownerName,
                ownerId: item.ownerId,
                dueAt: item.nextActionAt ?? null,
                priority: item.priority,
                status: item.commercialStage,
                overdue: item.nextActionOverdue ?? false,
                availableActions: ['open'],
            }));
        return rankTodayActions([...todayQueue, ...caseActions]);
    }, [todayQueue, opportunities]);
    const pendingDecisions = opportunities.filter((item) => item.briefingStatus !== 'complete').slice(0, 3);
    const activeStages = columns
        .filter((column) => column.count > 0)
        .sort((a, b) => b.count - a.count)
        .slice(0, 5);
    const stagesWithValue = columns.filter((column) => (column.estimatedValueCents ?? 0) > 0);
    useEffect(() => {
        if (new URLSearchParams(window.location.search).get('action') === 'new-opportunity') setShowForm(true);
    }, []);

    function submit(event: FormEvent) {
        event.preventDefault();
        post('/opportunities', {
            onSuccess: () => {
                reset();
                setShowForm(false);
            },
        });
    }

    return (
        <AppLayout>
            <Head title="Hoje" />
            <PageHeader
                eyebrow={todayLabel}
                title="Olá, Padrão RD."
                description="Uma leitura direta do que precisa de atenção agora."
                secondaryActions={
                    <>
                        <button className="command-button" type="button" onClick={() => window.dispatchEvent(new Event('rd:command'))}>
                            <Search size={15} />
                            <span>Buscar</span>
                            <kbd>⌘ K</kbd>
                        </button>
                        <button className="icon-button" aria-label="Abrir pipeline completo" onClick={() => router.visit('/pipeline')}>
                            <Filter size={17} />
                        </button>
                    </>
                }
                primaryAction={<PrimaryButton onClick={() => setShowForm(true)}>Nova oportunidade</PrimaryButton>}
            />

            <nav className="workspace-portals" aria-label="Áreas de trabalho">
                <Link href="/projects">
                    <span>01 / PLANEJAR</span>
                    <strong>Projetos</strong>
                    <small>Escopo, briefing e decisões</small>
                    <ArrowUpRight size={20} />
                </Link>
                <Link href="/production">
                    <span>02 / EXECUTAR</span>
                    <strong>Produção</strong>
                    <small>Equipe, tarefas e pós-evento</small>
                    <ArrowUpRight size={20} />
                </Link>
                <Link href="/agenda">
                    <span>03 / ACOMPANHAR</span>
                    <strong>Agenda</strong>
                    <small>Responsáveis e próximos passos</small>
                    <ArrowUpRight size={20} />
                </Link>
            </nav>
            <section className="today-focus" aria-labelledby="today-focus-title">
                <div className="today-focus__heading">
                    <div>
                        <span className="eyebrow">SUA ATENÇÃO HOJE</span>
                        <h2 id="today-focus-title">Três movimentos para avançar.</h2>
                    </div>
                    <Link href="/agenda">
                        Ver toda a fila <ArrowUpRight size={15} />
                    </Link>
                </div>
                {focusActions.length ? (
                    <div className="today-focus__grid">
                        {focusActions.map((item, index) => (
                            <Link href={item.href} className="today-focus__item" key={`${item.kind}-${item.id}`}>
                                <span className="today-focus__index">0{index + 1}</span>
                                <div>
                                    <small>{item.context}</small>
                                    <strong>{item.title}</strong>
                                    <p>{item.reason}</p>
                                    <span>
                                        {item.owner || 'Definir responsável'}
                                        {item.dueAt ? ` · ${item.dueAt}` : ''}
                                    </span>
                                </div>
                                <ArrowUpRight size={17} />
                            </Link>
                        ))}
                    </div>
                ) : (
                    <p className="today-focus__empty">Nenhuma ação na fila atual. Confira a agenda da equipe para ver outros prazos.</p>
                )}
            </section>
            <BentoGrid className="dashboard-bento" aria-label="Resumo operacional de hoje">
                <BentoItem colSpan={2}>
                    <Surface tone="elevated" padding="lg" className="operation-hero">
                        <div className="operation-hero__top">
                            <div>
                                <span className="eyebrow">OPERAÇÃO HOJE</span>
                                <h2>
                                    {todayQueue.length
                                        ? `${todayQueue.length} itens pedem ação.`
                                        : metrics.nextActions
                                          ? `${metrics.nextActions} próximas ações.`
                                          : 'Sua operação em perspectiva.'}
                                </h2>
                                <p>Continue pela decisão mais importante sem reconstruir o contexto.</p>
                            </div>
                            <StatusBadge
                                tone={todayQueue.length || metrics.pendingBriefings || metrics.nextActions ? 'warning' : 'neutral'}
                            >
                                {todayQueue.length || metrics.pendingBriefings || metrics.nextActions
                                    ? 'Pendências para revisar'
                                    : 'Visão da operação'}
                            </StatusBadge>
                        </div>
                        <div className="operation-orbits" aria-label="Indicadores principais">
                            <Link href="/pipeline">
                                <strong>{metrics.activeOpportunities}</strong>
                                <span>oportunidades</span>
                            </Link>
                            <Link href="/briefings">
                                <strong>{metrics.pendingBriefings}</strong>
                                <span>briefings</span>
                            </Link>
                            <Link href="/agenda">
                                <strong>{metrics.nextActions}</strong>
                                <span>próximas ações</span>
                            </Link>
                        </div>
                        <button
                            className="button button-primary"
                            type="button"
                            onClick={() => router.visit(todayQueue.length || metrics.nextActions ? '/agenda' : '/pipeline')}
                        >
                            Abrir próxima ação <ArrowRight size={15} />
                        </button>
                    </Surface>
                </BentoItem>

                <BentoItem colSpan={2}>
                    <Surface className="decision-widget">
                        <div className="bento-heading">
                            <div>
                                <span className="eyebrow">DECISÕES</span>
                                <h2>Aguardando você</h2>
                            </div>
                            <Link href="/briefings">
                                Ver fila <ArrowUpRight size={14} />
                            </Link>
                        </div>
                        <div className="decision-list">
                            {pendingDecisions.length ? (
                                pendingDecisions.map((item) => (
                                    <Link key={item.id} href={`/opportunities/${item.id}/briefing`}>
                                        <span className="decision-list__icon">
                                            <CircleAlert size={15} />
                                        </span>
                                        <div>
                                            <strong>{item.title}</strong>
                                            <small>Briefing precisa de revisão</small>
                                        </div>
                                        <ArrowUpRight size={14} />
                                    </Link>
                                ))
                            ) : (
                                <div className="bento-empty">Nenhuma decisão pendente agora.</div>
                            )}
                        </div>
                    </Surface>
                </BentoItem>

                <BentoItem>
                    <Surface className="stage-widget">
                        <div className="bento-heading">
                            <div>
                                <span className="eyebrow">COMERCIAL</span>
                                <h2>Distribuição</h2>
                            </div>
                            <Link href="/pipeline" aria-label="Abrir o pipeline comercial completo">
                                <ArrowUpRight size={15} />
                            </Link>
                        </div>
                        <BarSeries
                            title="Casos por etapa comercial"
                            categorical
                            data={activeStages.map((column) => ({ label: column.label, value: column.count }))}
                            emptyMessage="Sem oportunidades ativas."
                        />
                        {stagesWithValue.length > 0 && (
                            <>
                                <p className="stage-widget__divider">Valor estimado por etapa</p>
                                <BarSeries
                                    title="Valor estimado por etapa comercial"
                                    categorical
                                    data={stagesWithValue.map((column) => ({
                                        label: column.label,
                                        value: column.estimatedValueCents ?? 0,
                                    }))}
                                    format={formatCurrencyFromCents}
                                    emptyMessage="Nenhuma oportunidade com valor estimado."
                                />
                            </>
                        )}
                    </Surface>
                </BentoItem>

                <BentoItem>
                    <Surface className="rd-agent-home">
                        <div className="ai-bento-card__copy">
                            <span>
                                <Sparkles size={14} /> AGENTE RD
                            </span>
                            <h2>Clareza para decidir.</h2>
                            <p>
                                {aiMode === 'manual'
                                    ? 'Organize o trabalho com os controles manuais. A IA está desativada.'
                                    : aiMode === 'demo'
                                      ? 'Explore sugestões de demonstração, sem uso de IA externa.'
                                      : 'Reúna contexto e revise as sugestões antes de aplicar.'}
                            </p>
                            <button type="button" onClick={() => router.visit('/settings/ai')}>
                                Ver configuração <ArrowUpRight size={14} />
                            </button>
                        </div>
                        <div className="agent-orbit" aria-hidden="true">
                            <Sparkles size={30} />
                            <i />
                            <i />
                        </div>
                    </Surface>
                </BentoItem>

                <BentoItem colSpan={2}>
                    <Surface className="today-bento">
                        <div className="bento-heading">
                            <div>
                                <span className="eyebrow">HOJE E AMANHÃ</span>
                                <h2>Fila operacional</h2>
                            </div>
                            <Link href="/agenda">
                                Agenda <ArrowUpRight size={14} />
                            </Link>
                        </div>
                        <TodayQueue tasks={todayQueue} userId={currentUserId} />
                    </Surface>
                </BentoItem>

                <BentoItem colSpan={2}>
                    <Surface className="pipeline-preview">
                        <div className="bento-heading">
                            <div>
                                <span className="eyebrow">FLUXO COMERCIAL</span>
                                <h2>Pipeline resumido</h2>
                            </div>
                            <Link href="/pipeline">
                                Kanban completo <ArrowUpRight size={14} />
                            </Link>
                        </div>
                        <div className="pipeline-mini">
                            {columns
                                .filter((column) => column.count > 0)
                                .slice(0, 4)
                                .map((column) => (
                                    <div key={column.id}>
                                        <header>
                                            <span className={`status-dot ${stageColors[column.id] ?? 'gray'}`} />
                                            {column.label}
                                            <b>{column.count}</b>
                                        </header>
                                        {(grouped[column.id] ?? []).slice(0, 2).map((opportunity) => (
                                            <Link key={opportunity.id} href={`/opportunities/${opportunity.id}`}>
                                                <strong>{opportunity.title}</strong>
                                                <span>{opportunity.clientName}</span>
                                            </Link>
                                        ))}
                                    </div>
                                ))}
                        </div>
                    </Surface>
                </BentoItem>

                <BentoItem>
                    <Surface className="continuity-bento">
                        <span className="eyebrow">CONTINUIDADE</span>
                        <h2>{opportunities[0]?.title || 'Nenhum caso recente'}</h2>
                        <p>{opportunities[0] ? 'Retome de onde a equipe parou.' : 'Crie uma oportunidade para iniciar a jornada.'}</p>
                        <Link
                            className="button button-subtle"
                            href={demoOpportunityId ? `/opportunities/${demoOpportunityId}` : '/pipeline'}
                        >
                            {demoOpportunityId ? 'Continuar caso' : 'Abrir comercial'} <ArrowRight size={14} />
                        </Link>
                    </Surface>
                </BentoItem>

                <BentoItem>
                    <Surface className="activity-bento">
                        <span className="eyebrow">ATIVIDADE RECENTE</span>
                        <h2>Histórico operacional</h2>
                        <p>Abra o histórico para consultar alterações e decisões registradas.</p>
                        <Link className="button button-subtle" href="/history">
                            Ver histórico <ArrowRight size={14} />
                        </Link>
                    </Surface>
                </BentoItem>
            </BentoGrid>

            {showForm && (
                <div className="modal-backdrop" role="presentation">
                    <div className="modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">
                        <div className="modal-heading">
                            <div>
                                <span className="eyebrow">NOVA OPORTUNIDADE</span>
                                <h2 id="modal-title">Começar pelo contexto.</h2>
                            </div>
                            <button className="icon-button" type="button" onClick={() => setShowForm(false)} aria-label="Fechar">
                                <X size={17} />
                            </button>
                        </div>
                        <p className="drawer-intro">Preencha só o essencial para que a equipe saiba quem deve agir e quando.</p>
                        <form onSubmit={submit} className="form-grid">
                            <label>
                                Nome da oportunidade
                                <input value={data.title} onChange={(event) => setData('title', event.target.value)} autoFocus />
                                {errors.title && <small className="field-error">{errors.title}</small>}
                            </label>
                            <label>
                                Cliente
                                <input value={data.client_name} onChange={(event) => setData('client_name', event.target.value)} />
                                {errors.client_name && <small className="field-error">{errors.client_name}</small>}
                            </label>
                            <label>
                                Contato principal
                                <input value={data.contact_name} onChange={(event) => setData('contact_name', event.target.value)} />
                            </label>
                            <label>
                                E-mail
                                <input
                                    type="email"
                                    value={data.contact_email}
                                    onChange={(event) => setData('contact_email', event.target.value)}
                                />
                            </label>
                            <label>
                                Origem
                                <select value={data.origin} onChange={(event) => setData('origin', event.target.value)}>
                                    <option value="other">Outro</option>
                                    <option value="referral">Indicação</option>
                                    <option value="inbound">Entrada</option>
                                    <option value="outbound">Prospecção</option>
                                    <option value="returning_client">Cliente recorrente</option>
                                    <option value="partner">Parceiro</option>
                                    <option value="organic">Orgânico</option>
                                </select>
                            </label>
                            <label>
                                Prioridade
                                <select value={data.priority} onChange={(event) => setData('priority', event.target.value)}>
                                    <option value="low">Baixa</option>
                                    <option value="normal">Normal</option>
                                    <option value="high">Alta</option>
                                </select>
                            </label>
                            <label>
                                Próxima ação
                                <input
                                    value={data.next_action}
                                    onChange={(event) => setData('next_action', event.target.value)}
                                    placeholder="Ex.: Confirmar data da reunião"
                                />
                            </label>
                            <label>
                                Prazo da próxima ação
                                <input
                                    type="datetime-local"
                                    value={data.next_action_at}
                                    onChange={(event) => setData('next_action_at', event.target.value)}
                                />
                            </label>
                            <div className="form-actions">
                                <button type="button" className="button button-subtle" onClick={() => setShowForm(false)}>
                                    Cancelar
                                </button>
                                <button type="submit" className="button button-primary" disabled={processing}>
                                    <Plus size={16} />
                                    {processing ? 'Criando…' : 'Criar oportunidade'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
