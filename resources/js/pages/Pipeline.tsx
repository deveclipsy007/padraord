import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, CalendarDays, Check, ChevronDown, List, Search, Workflow, X } from 'lucide-react';
import { useMemo, useRef, useState, type DragEvent as ReactDragEvent, type MouseEvent as ReactMouseEvent, type PointerEvent as ReactPointerEvent } from 'react';
import { GlassSurface } from '../components/GlassSurface';
import { PageHeader } from '../components/ui/PageHeader';
import { Surface } from '../components/ui/Surface';
import { AppLayout } from '../layout';
import type { CommercialStage, Opportunity, OpportunityOrigin, OpportunityPriority, PipelineFilters } from '../types';

type Owner = { id: number; name: string };
type Props = { opportunities: Opportunity[]; stages: { id: CommercialStage; label: string; count: number; estimatedValueCents?: number }[]; filters: PipelineFilters; owners: Owner[]; origins: OpportunityOrigin[] };
type DragState = { active: boolean; startX: number; scrollLeft: number; moved: boolean };

const stages: { id: CommercialStage; label: string; tone: string }[] = [
    { id: 'lead', label: 'Lead', tone: 'gray' },
    { id: 'qualification', label: 'Qualificação', tone: 'blue' },
    { id: 'meeting', label: 'Reunião', tone: 'amber' },
    { id: 'initial_briefing', label: 'Briefing inicial', tone: 'violet' },
    { id: 'viability_offer', label: 'Oferta de Viabilidade', tone: 'violet' },
    { id: 'viability_contracted', label: 'Viabilidade contratada', tone: 'green' },
];
const terminalStages: { id: CommercialStage; label: string }[] = [{ id: 'lost', label: 'Perdidos' }, { id: 'cancelled', label: 'Cancelados' }];
const stageLabel: Record<string, string> = Object.fromEntries([...stages, ...terminalStages].map((stage) => [stage.id, stage.label]));
const originLabel: Record<OpportunityOrigin, string> = { referral: 'Indicação', inbound: 'Entrada', outbound: 'Prospecção', returning_client: 'Cliente recorrente', partner: 'Parceiro', organic: 'Orgânico', other: 'Outro' };
const priorityLabel: Record<OpportunityPriority, string> = { low: 'Baixa', normal: 'Normal', high: 'Alta' };
const money = (cents?: number | null) => cents == null ? 'A definir' : new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL', maximumFractionDigits: 0 }).format(cents / 100);
// O caminho legado /stage continua preservado no backend para links antigos; o fluxo novo usa /commercial-stage.

function queryFrom(filters: PipelineFilters, patch: Partial<PipelineFilters> = {}) {
    const next = { ...filters, ...patch };
    return Object.fromEntries(Object.entries(next).filter(([key, value]) => value !== '' && value !== false && !(key === 'view' && value === 'kanban')));
}

export default function Pipeline({ opportunities, stages: serverStages, filters, owners, origins }: Props) {
    const [selected, setSelected] = useState<Opportunity | null>(null);
    const [draggedId, setDraggedId] = useState<number | null>(null);
    const [dropStage, setDropStage] = useState<CommercialStage | null>(null);
    const [movingId, setMovingId] = useState<number | null>(null);
    const [reasonOpen, setReasonOpen] = useState<{ opportunity: Opportunity; to: CommercialStage } | null>(null);
    const [reasonCategory, setReasonCategory] = useState('data_correction');
    const [reasonNote, setReasonNote] = useState('');
    const boardRef = useRef<HTMLElement | null>(null);
    const dragState = useRef<DragState>({ active: false, startX: 0, scrollLeft: 0, moved: false });
    const suppressClick = useRef(false);
    const boardStages = filters.stage === 'lost' || filters.stage === 'cancelled' ? terminalStages.map((stage) => ({ ...stage, tone: 'gray' })) : stages;
    const groups = useMemo(() => Object.fromEntries([...stages, ...terminalStages].map((stage) => [stage.id, opportunities.filter((item) => item.commercialStage === stage.id)])) as Record<CommercialStage, Opportunity[]>, [opportunities]);
    const navigate = (patch: Partial<PipelineFilters>) => router.get('/pipeline', queryFrom(filters, patch), { preserveState: true, preserveScroll: true, replace: true });

    const requestMove = (item: Opportunity, to: CommercialStage) => {
        if (item.commercialStage === to || movingId !== null) return;
        const currentIndex = stages.findIndex((stage) => stage.id === item.commercialStage);
        const targetIndex = stages.findIndex((stage) => stage.id === to);
        const needsReason = to === 'lost' || to === 'cancelled' || currentIndex < 0 || (currentIndex >= 0 && targetIndex >= 0 && targetIndex < currentIndex);
        if (needsReason) {
            setReasonCategory(to === 'lost' ? 'no_budget' : to === 'cancelled' ? 'client_cancelled' : 'data_correction');
            setReasonNote('');
            setReasonOpen({ opportunity: item, to });
            return;
        }
        setMovingId(item.id);
        router.post(`/opportunities/${item.id}/commercial-stage`, { to, revision: item.commercialRevision ?? 0, source: 'commercial' }, { preserveScroll: true, onFinish: () => setMovingId(null) });
    };

    const confirmMove = () => {
        if (!reasonOpen || !reasonNote.trim()) return;
        setMovingId(reasonOpen.opportunity.id);
        router.post(`/opportunities/${reasonOpen.opportunity.id}/commercial-stage`, { to: reasonOpen.to, revision: reasonOpen.opportunity.commercialRevision ?? 0, reason_category: reasonCategory, reason_note: reasonNote.trim(), source: 'commercial' }, { preserveScroll: true, onFinish: () => { setMovingId(null); setReasonOpen(null); } });
    };

    const handlePointerDown = (event: ReactPointerEvent<HTMLElement>) => {
        if ((event.pointerType === 'mouse' && event.button !== 0) || !boardRef.current) return;
        dragState.current = { active: true, startX: event.clientX, scrollLeft: boardRef.current.scrollLeft, moved: false };
        boardRef.current.setPointerCapture(event.pointerId);
    };
    const handlePointerMove = (event: ReactPointerEvent<HTMLElement>) => {
        if (!dragState.current.active || !boardRef.current) return;
        const distance = event.clientX - dragState.current.startX;
        if (Math.abs(distance) > 6) dragState.current.moved = true;
        boardRef.current.scrollLeft = dragState.current.scrollLeft - distance;
        if (dragState.current.moved) event.preventDefault();
    };
    const handlePointerUp = (event: ReactPointerEvent<HTMLElement>) => {
        if (boardRef.current?.hasPointerCapture(event.pointerId)) boardRef.current.releasePointerCapture(event.pointerId);
        suppressClick.current = dragState.current.moved;
        dragState.current.active = false;
    };
    const handleClickCapture = (event: ReactMouseEvent<HTMLElement>) => { if (suppressClick.current) { event.preventDefault(); event.stopPropagation(); suppressClick.current = false; } };
    const handleDragStart = (event: ReactDragEvent<HTMLElement>, id: number) => { event.stopPropagation(); event.dataTransfer.effectAllowed = 'move'; event.dataTransfer.setData('text/plain', String(id)); setDraggedId(id); };
    const handleDrop = (event: ReactDragEvent<HTMLDivElement>, to: CommercialStage) => { event.preventDefault(); event.stopPropagation(); const id = Number(event.dataTransfer.getData('text/plain')) || draggedId; const item = opportunities.find((opportunity) => opportunity.id === id); setDraggedId(null); setDropStage(null); if (item) requestMove(item, to); };

    return <AppLayout>
        <Head title="Pipeline comercial" />
        <PageHeader eyebrow="Comercial" title="Pipeline vivo" description="Uma linha contínua para conduzir cada lead até a Viabilidade contratada." primaryAction={<div className="pipeline-header-actions"><label className="search-field"><Search size={15} /><span className="sr-only">Buscar oportunidade</span><input value={filters.q} onChange={(event) => navigate({ q: event.target.value })} placeholder="Buscar oportunidade" /></label><Link className="button button-primary" href="/?action=new-opportunity"><Workflow size={16} /> Nova oportunidade</Link></div>} />
        <section className="pipeline-summary"><GlassSurface><Workflow size={18} /><div><strong>{opportunities.length} oportunidades</strong><small>ciclo comercial filtrado</small></div></GlassSurface><GlassSurface><CalendarDays size={18} /><div><strong>{serverStages.reduce((sum, stage) => sum + stage.count, 0)} em andamento</strong><small>arraste ou use o seletor da etapa</small></div></GlassSurface></section>
        <Surface className="pipeline-controls" padding="sm"><div className="pipeline-controls__row"><div className="segmented-control" role="tablist" aria-label="Visualização do pipeline"><button type="button" className={filters.view === 'kanban' ? 'is-active' : ''} onClick={() => navigate({ view: 'kanban' })}><Workflow size={15} /> Kanban</button><button type="button" className={filters.view === 'list' ? 'is-active' : ''} onClick={() => navigate({ view: 'list' })}><List size={15} /> Lista</button></div><div className="pipeline-filter-summary"><span>{filters.stage ? stageLabel[filters.stage] : 'Todos os estágios'}</span><span>{filters.priority ? priorityLabel[filters.priority] : 'Todas prioridades'}</span><span>{filters.status === 'active' ? 'Ativos' : filters.status === 'archived' ? 'Arquivados' : 'Todos'}</span></div></div><div className="pipeline-filters"><label><span>Estágio</span><select value={filters.stage} onChange={(event) => navigate({ stage: event.target.value as CommercialStage | '' })}><option value="">Todos os estágios</option>{[...stages, ...terminalStages].map((stage) => <option key={stage.id} value={stage.id}>{stage.label}</option>)}</select></label><label><span>Responsável</span><select value={filters.owner} onChange={(event) => navigate({ owner: event.target.value })}><option value="">Todos</option>{owners.map((owner) => <option key={owner.id} value={owner.id}>{owner.name}</option>)}</select></label><label><span>Prioridade</span><select value={filters.priority} onChange={(event) => navigate({ priority: event.target.value as OpportunityPriority | '' })}><option value="">Todas</option>{Object.entries(priorityLabel).map(([key, label]) => <option key={key} value={key}>{label}</option>)}</select></label><label><span>Origem</span><select value={filters.origin} onChange={(event) => navigate({ origin: event.target.value as OpportunityOrigin | '' })}><option value="">Todas</option>{origins.map((origin) => <option key={origin} value={origin}>{originLabel[origin]}</option>)}</select></label><label className="pipeline-check"><input type="checkbox" checked={filters.overdue} onChange={(event) => navigate({ overdue: event.target.checked })} /><span>Atrasadas</span></label><label className="pipeline-check"><input type="checkbox" checked={filters.unassigned} onChange={(event) => navigate({ unassigned: event.target.checked })} /><span>Sem responsável</span></label><label><span>Situação</span><select value={filters.status} onChange={(event) => navigate({ status: event.target.value as PipelineFilters['status'] })}><option value="active">Ativos</option><option value="archived">Arquivados</option><option value="all">Todos</option></select></label></div></Surface>
        {filters.view === 'list' ? <Surface className="pipeline-list-surface" padding="none"><div className="rd-table-wrap"><table className="rd-table"><thead><tr><th>Oportunidade</th><th>Estágio</th><th>Responsável</th><th>Prioridade</th><th>Próxima ação</th><th aria-label="Ações" /></tr></thead><tbody>{opportunities.map((item) => <tr key={item.id}><td><Link href={`/opportunities/${item.id}`}><strong>{item.title}</strong><small>{item.clientName}</small></Link></td><td><span className="status-pill violet">{item.commercialStageLabel}</span></td><td>{item.ownerName || 'Sem responsável'}</td><td>{priorityLabel[item.priority]}</td><td>{item.nextAction || 'Definir próxima ação'}</td><td><button className="icon-button" type="button" onClick={() => setSelected(item)} aria-label={`Abrir ${item.title}`}><ArrowRight size={16} /></button></td></tr>)}</tbody></table>{!opportunities.length && <div className="directory-empty"><Search size={21} /><strong>Nenhuma oportunidade encontrada</strong><p>Ajuste os filtros para localizar um caso.</p></div>}</div></Surface> : <Surface className="pipeline-board-shell" padding="sm"><div className="pipeline-board-shell__heading"><div><span className="eyebrow">KANBAN COMERCIAL</span><strong>Todos os estágios, em uma única linha</strong></div><small>Arraste os cards entre as etapas ou use o seletor no detalhe do card.</small></div><section ref={boardRef} className={`pipeline-full${draggedId !== null ? ' is-card-dragging' : ''}`} aria-label="Kanban horizontal do pipeline" onPointerDown={handlePointerDown} onPointerMove={handlePointerMove} onPointerUp={handlePointerUp} onPointerCancel={handlePointerUp} onClickCapture={handleClickCapture} role="region" tabIndex={0}>{boardStages.map((stage) => { const items = groups[stage.id] ?? []; return <div className={`pipeline-lane${dropStage === stage.id ? ' is-drop-target' : ''}`} key={stage.id} onDragOver={(event) => { event.preventDefault(); setDropStage(stage.id); }} onDragLeave={() => setDropStage(null)} onDrop={(event) => handleDrop(event, stage.id)}><div className="column-heading"><span className={`status-dot ${stage.tone}`} /><span>{stage.label}</span><b>{items.length}</b></div>{items.map((item) => <article className={`opportunity-card pipeline-card${draggedId === item.id ? ' is-card-dragging' : ''}`} key={item.id} draggable aria-grabbed={draggedId === item.id} onDragStart={(event) => handleDragStart(event, item.id)} onDragEnd={() => { setDraggedId(null); setDropStage(null); }} onPointerDown={(event) => event.stopPropagation()}><button className="pipeline-card__open" type="button" onClick={() => setSelected(item)} aria-label={`Abrir detalhes de ${item.title}`}><div className="card-top"><span className="card-client">{item.clientName}</span><ArrowRight size={14} /></div><h3>{item.title}</h3><div className="card-meta"><span><CalendarDays size={13} /> {item.eventDate || 'Sem data'}</span><span>{money(item.estimatedValueCents)}</span></div>{item.nextAction && <span className="pipeline-card__next">Próximo: {item.nextAction}</span>}{movingId === item.id && <span className="pipeline-card-moving">Atualizando etapa…</span>}</button><label className="pipeline-card__stage"><span className="sr-only">Mover etapa</span><select value={item.commercialStage} disabled={movingId === item.id} onChange={(event) => requestMove(item, event.target.value as CommercialStage)}><option value={item.commercialStage}>{stageLabel[item.commercialStage]}</option>{[...stages, ...terminalStages].filter((candidate) => candidate.id !== item.commercialStage).map((candidate) => <option key={candidate.id} value={candidate.id}>{candidate.label}</option>)}</select><ChevronDown size={13} /></label></article>)}{!items.length && <div className="empty-column">Livre por enquanto</div>}</div>; })}</section></Surface>}
        {selected && <div className="modal-backdrop" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) setSelected(null); }}><div className="modal pipeline-drawer" role="dialog" aria-modal="true" aria-labelledby="pipeline-detail-title"><div className="modal-heading"><div><span className="eyebrow">DETALHE DO CASO</span><h2 id="pipeline-detail-title">{selected.title}</h2></div><button className="icon-button" type="button" onClick={() => setSelected(null)} aria-label="Fechar"><X size={17} /></button></div><p className="drawer-intro">{selected.clientName} · {selected.commercialStageLabel}</p><div className="pipeline-detail-grid"><div><span>Responsável</span><strong>{selected.ownerName || 'Sem responsável'}</strong></div><div><span>Prioridade</span><strong>{priorityLabel[selected.priority]}</strong></div><div><span>Origem</span><strong>{originLabel[selected.origin]}</strong></div><div><span>Prazo</span><strong>{selected.nextActionAt || 'Sem prazo'}</strong></div></div>{selected.nextAction && <div className="impact-notice"><Check size={15} /><span><strong>Próxima ação</strong>{selected.nextAction}</span></div>}<div className="form-actions"><button className="button button-subtle" type="button" onClick={() => setSelected(null)}>Continuar aqui</button><Link className="button button-primary" href={`/opportunities/${selected.id}`}>Abrir workspace <ArrowRight size={15} /></Link></div></div></div>}
        {reasonOpen && <div className="modal-backdrop" role="presentation"><div className="modal" role="dialog" aria-modal="true" aria-labelledby="transition-title"><div className="modal-heading"><div><span className="eyebrow">REVISÃO DE ETAPA</span><h2 id="transition-title">Registrar decisão</h2></div><button className="icon-button" type="button" onClick={() => setReasonOpen(null)} aria-label="Fechar"><X size={17} /></button></div><p className="drawer-intro">Para mover “{reasonOpen.opportunity.title}” para {stageLabel[reasonOpen.to]}, informe o motivo. Isso fica no histórico do caso.</p><label className="rd-field"><span>Categoria</span><select value={reasonCategory} onChange={(event) => setReasonCategory(event.target.value)}>{(reasonOpen.to === 'lost' ? [['price', 'Preço'], ['no_budget', 'Sem orçamento'], ['timing', 'Prazo'], ['no_response', 'Sem retorno'], ['competitor', 'Concorrente'], ['not_fit', 'Sem aderência'], ['client_decision', 'Decisão do cliente'], ['other', 'Outro']] : reasonOpen.to === 'cancelled' ? [['duplicate', 'Duplicado'], ['client_cancelled', 'Cancelado pelo cliente'], ['internal_cancelled', 'Cancelado internamente'], ['event_cancelled', 'Evento cancelado'], ['other', 'Outro']] : [['data_correction', 'Correção de dados'], ['client_request', 'Pedido do cliente'], ['process_adjustment', 'Ajuste de processo'], ['other', 'Outro']]).map(([value, label]) => <option value={value} key={value}>{label}</option>)}</select></label><label className="rd-field"><span>Observação obrigatória</span><textarea rows={4} value={reasonNote} onChange={(event) => setReasonNote(event.target.value)} placeholder="Explique o que mudou e qual é o próximo passo." /></label><div className="form-actions"><button className="button button-subtle" type="button" onClick={() => setReasonOpen(null)}>Cancelar</button><button className="button button-primary" type="button" disabled={!reasonNote.trim() || movingId !== null} onClick={confirmMove}>Confirmar mudança</button></div></div></div>}
    </AppLayout>;
}
