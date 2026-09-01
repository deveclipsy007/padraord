import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, CalendarDays, Search, Workflow } from 'lucide-react';
import { useMemo, useRef, useState, type DragEvent as ReactDragEvent, type MouseEvent as ReactMouseEvent, type PointerEvent as ReactPointerEvent } from 'react';
import { GlassSurface } from '../components/GlassSurface';
import { PageHeader } from '../components/ui/PageHeader';
import { Surface } from '../components/ui/Surface';
import { AppLayout } from '../layout';

type Opportunity = {
    id: number;
    title: string;
    clientName: string;
    stage: string;
    stageLabel: string;
    eventDate?: string | null;
    estimatedValueCents?: number | null;
};

type Props = { opportunities: Opportunity[] };

type DragState = {
    active: boolean;
    startX: number;
    scrollLeft: number;
    moved: boolean;
};

const stages = ['lead', 'qualification', 'briefing', 'budget', 'proposal', 'negotiation', 'contract', 'pre_production', 'production', 'post_event', 'closed'];
const labels: Record<string, string> = { lead: 'Lead', qualification: 'Qualificação', briefing: 'Briefing', budget: 'Orçamento', proposal: 'Proposta', negotiation: 'Negociação', contract: 'Contrato', pre_production: 'Pré-produção', production: 'Execução', post_event: 'Pós-evento', closed: 'Encerrado' };
const stageColors: Record<string, string> = { lead: 'gray', qualification: 'blue', briefing: 'amber', budget: 'violet', proposal: 'blue', negotiation: 'amber', contract: 'green', pre_production: 'blue', production: 'green', post_event: 'gray', closed: 'gray' };
const money = (cents?: number | null) => cents == null ? 'A definir' : new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL', maximumFractionDigits: 0 }).format(cents / 100);

export default function Pipeline({ opportunities }: Props) {
    const [query, setQuery] = useState('');
    const [isDragging, setIsDragging] = useState(false);
    const [draggedOpportunityId, setDraggedOpportunityId] = useState<number | null>(null);
    const [dropStage, setDropStage] = useState<string | null>(null);
    const [movingOpportunityId, setMovingOpportunityId] = useState<number | null>(null);
    const pipelineRef = useRef<HTMLElement | null>(null);
    const dragStateRef = useRef<DragState>({ active: false, startX: 0, scrollLeft: 0, moved: false });
    const suppressClickRef = useRef(false);
    const suppressCardClickRef = useRef(false);

    const filtered = useMemo(() => opportunities.filter((item) => `${item.title} ${item.clientName}`.toLowerCase().includes(query.toLowerCase())), [opportunities, query]);

    const handlePointerDown = (event: ReactPointerEvent<HTMLElement>) => {
        if (event.pointerType === 'mouse' && event.button !== 0) {
            return;
        }

        const element = pipelineRef.current;

        if (!element) {
            return;
        }

        dragStateRef.current = { active: true, startX: event.clientX, scrollLeft: element.scrollLeft, moved: false };
        suppressClickRef.current = false;
        element.setPointerCapture(event.pointerId);
        setIsDragging(true);
    };

    const handlePointerMove = (event: ReactPointerEvent<HTMLElement>) => {
        const drag = dragStateRef.current;
        const element = pipelineRef.current;

        if (!drag.active || !element) {
            return;
        }

        const distance = event.clientX - drag.startX;

        if (Math.abs(distance) > 6) {
            drag.moved = true;
        }

        element.scrollLeft = drag.scrollLeft - distance;

        if (drag.moved) {
            event.preventDefault();
        }
    };

    const handlePointerUp = (event: ReactPointerEvent<HTMLElement>) => {
        const element = pipelineRef.current;

        if (element?.hasPointerCapture(event.pointerId)) {
            element.releasePointerCapture(event.pointerId);
        }

        suppressClickRef.current = dragStateRef.current.moved;
        dragStateRef.current.active = false;
        setIsDragging(false);
    };

    const handleClickCapture = (event: ReactMouseEvent<HTMLElement>) => {
        if (!suppressClickRef.current) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        suppressClickRef.current = false;
    };

    const handleCardDragStart = (event: ReactDragEvent<Element>, opportunityId: number) => {
        event.stopPropagation();
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(opportunityId));
        suppressCardClickRef.current = false;
        setDraggedOpportunityId(opportunityId);
        setDropStage(null);
    };

    const handleCardDragEnd = (event: ReactDragEvent<Element>) => {
        event.stopPropagation();
        suppressCardClickRef.current = true;
        setDraggedOpportunityId(null);
        setDropStage(null);
    };

    const handleCardClick = (event: ReactMouseEvent<Element>) => {
        if (!suppressCardClickRef.current) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        suppressCardClickRef.current = false;
    };

    const handleLaneDragOver = (event: ReactDragEvent<HTMLDivElement>, stage: string) => {
        event.preventDefault();
        event.stopPropagation();

        if (draggedOpportunityId !== null) {
            event.dataTransfer.dropEffect = 'move';
            setDropStage(stage);
        }
    };

    const handleLaneDragLeave = (event: ReactDragEvent<HTMLDivElement>) => {
        event.stopPropagation();
        const relatedTarget = event.relatedTarget;

        if (!(relatedTarget instanceof Node) || !event.currentTarget.contains(relatedTarget)) {
            setDropStage(null);
        }
    };

    const handleLaneDrop = (event: ReactDragEvent<HTMLDivElement>, stage: string) => {
        event.preventDefault();
        event.stopPropagation();

        const opportunityId = Number(event.dataTransfer.getData('text/plain')) || draggedOpportunityId;
        const opportunity = opportunities.find((item) => item.id === opportunityId);

        setDraggedOpportunityId(null);
        setDropStage(null);

        if (!opportunityId || !opportunity || opportunity.stage === stage || movingOpportunityId !== null) {
            return;
        }

        setMovingOpportunityId(opportunityId);
        router.post(`/opportunities/${opportunityId}/stage`, { stage }, {
            preserveScroll: true,
            onFinish: () => setMovingOpportunityId(null),
        });
    };

    return <AppLayout>
        <Head title="Pipeline" />
        <PageHeader eyebrow="Fluxo comercial" title="Pipeline vivo" description="Arraste para organizar ou abra um caso para decidir o próximo movimento." primaryAction={<label className="search-field"><Search size={15} /><input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Buscar oportunidade" /></label>} />

        <section className="pipeline-summary">
            <GlassSurface><Workflow size={18} /><div><strong>{filtered.length} oportunidades</strong><small>filtradas na operação</small></div></GlassSurface>
            <GlassSurface><CalendarDays size={18} /><div><strong>Próximo movimento</strong><small>começa pela etapa ativa</small></div></GlassSurface>
        </section>

        <Surface className="pipeline-board-shell" padding="sm">
            <div className="pipeline-board-shell__heading"><div><span className="eyebrow">KANBAN COMERCIAL</span><strong>Todos os estágios, em uma única linha</strong></div><small>Arraste os cards entre as etapas ou deslize o quadro horizontalmente.</small></div>
        <section
            ref={pipelineRef}
            className={`pipeline-full${isDragging ? ' is-dragging' : ''}${draggedOpportunityId !== null ? ' is-card-dragging' : ''}`}
            aria-label="Kanban horizontal do pipeline"
            onPointerDown={handlePointerDown}
            onPointerMove={handlePointerMove}
            onPointerUp={handlePointerUp}
            onPointerCancel={handlePointerUp}
            onClickCapture={handleClickCapture}
            role="region"
            tabIndex={0}
        >
            {stages.map((stage) => {
                const items = filtered.filter((item) => item.stage === stage);

                return <div
                    className={`pipeline-lane${dropStage === stage ? ' is-drop-target' : ''}`}
                    key={stage}
                    onDragOver={(event) => handleLaneDragOver(event, stage)}
                    onDragLeave={handleLaneDragLeave}
                    onDrop={(event) => handleLaneDrop(event, stage)}
                >
                    <div className="column-heading"><span className={`status-dot ${stageColors[stage]}`} /><span>{labels[stage]}</span><b>{items.length}</b></div>
                    {items.map((item) => <Link
                        className={`opportunity-card pipeline-card${draggedOpportunityId === item.id ? ' is-card-dragging' : ''}`}
                        href={`/opportunities/${item.id}`}
                        key={item.id}
                        viewTransition
                        draggable
                        aria-grabbed={draggedOpportunityId === item.id}
                        aria-label={`${item.title}, ${labels[item.stage]}. Arraste para mudar de etapa.`}
                        onPointerDown={(event) => event.stopPropagation()}
                        onDragStart={(event) => handleCardDragStart(event, item.id)}
                        onDragEnd={handleCardDragEnd}
                        onClick={handleCardClick}
                    >
                        <div className="card-top"><span className="card-client">{item.clientName}</span><ArrowRight size={14} /></div>
                        <h3>{item.title}</h3>
                        <div className="card-meta"><span><CalendarDays size={13} /> {item.eventDate || 'sem data'}</span><span>{money(item.estimatedValueCents)}</span></div>
                        {movingOpportunityId === item.id && <span className="pipeline-card-moving">Atualizando etapa…</span>}
                    </Link>)}
                    {items.length === 0 && <div className="empty-column">Livre por enquanto</div>}
                </div>;
            })}
        </section>
        </Surface>
    </AppLayout>;
}
