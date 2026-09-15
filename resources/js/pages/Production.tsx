import { Head, router, useForm } from '@inertiajs/react';
import {
    CalendarDays,
    CalendarClock,
    Check,
    CheckCircle2,
    CircleAlert,
    ClipboardCheck,
    FileCheck2,
    ListChecks,
    Plus,
    ReceiptText,
    RefreshCw,
    ShieldCheck,
    UserRound,
    UsersRound,
} from 'lucide-react';
import { FormEvent, useState } from 'react';
import { CaseAttachmentsPanel, type AttachmentLink, type CaseAttachment } from '../components/CaseAttachmentsPanel';
import { GlassSurface } from '../components/GlassSurface';
import { CasePageHeader } from '../components/CasePageHeader';
import { SegmentedControl } from '../components/ui/SegmentedControl';
import { Surface } from '../components/ui/Surface';
import { AppLayout } from '../layout';

type Task = {
    id: number;
    title: string;
    description?: string | null;
    status: string;
    priority: string;
    phase?: string | null;
    dueDate?: string | null;
    scheduledStartsAt?: string | null;
    scheduledEndsAt?: string | null;
    dependencyId?: number | null;
    dependencyTitle?: string | null;
    blockedReason?: string | null;
    technicalValidationId?: number | null;
    technicalValidation?: { id: number; reference: string; status: string } | null;
    team?: { userId: number; name: string | null; role: string | null; isResponsible: boolean }[];
    revision?: number;
};
type Validation = {
    id: number;
    reference: string;
    measurements?: Record<string, string> | null;
    evidence?: string | null;
    supplierName?: string | null;
    status: string;
    revision: number;
    confirmationEvidence?: string | null;
};
type ScopePreview = {
    id: number;
    source?: { budget_id?: number; budget_version?: number } | null;
    items: { description: string; category?: string; quantity?: string | number; unit?: string | null }[];
    status: string;
    result?: { task_ids?: number[]; count?: number } | null;
} | null;
type Props = {
    opportunity: { id: number; title: string; clientName: string; eventDate?: string | null };
    tasks: Task[];
    validations: Validation[];
    scopePreview: ScopePreview;
    timeline: {
        id: number;
        title: string;
        phase: string | null;
        status: string;
        dependency_id: number | null;
        dependency_title: string | null;
        scheduled_starts_at: string;
        scheduled_ends_at: string;
        team: { user_id: number; name: string | null; role: string | null; is_responsible: boolean }[];
    }[];
    teamMembers: { id: number; name: string }[];
    checklists: {
        id: number;
        taskId: number | null;
        taskTitle: string | null;
        phase: string;
        title: string;
        status: 'open' | 'completed';
        items: {
            id: number;
            title: string;
            requiresPhoto: boolean;
            completedAt: string | null;
            photo: { id: number; name: string; downloadUrl: string } | null;
        }[];
    }[];
    serviceOrders: {
        id: number;
        code: string;
        title: string;
        status: 'draft' | 'issued' | 'received';
        amountCents: number | null;
        supplierName: string | null;
        scope: { quote?: { service?: string }; tasks?: { id: number; title: string }[] };
        receipt: { id: number; name: string; downloadUrl: string } | null;
        receivedAt: string | null;
    }[];
    supplierQuotes: { id: number; supplierId: number; supplierName: string | null; service: string; amountCents: number }[];
    attachments: CaseAttachment[];
    attachmentLinks: AttachmentLink[];
    attachmentQuota: number;
};

const labels: Record<string, string> = { todo: 'A fazer', in_progress: 'Em andamento', done: 'Concluída', blocked: 'Bloqueada' };
const phaseLabels: Record<string, string> = { preparation: 'Preparação', setup: 'Montagem', event: 'Evento', teardown: 'Desmontagem' };

export default function Production({
    opportunity,
    tasks,
    validations,
    scopePreview,
    timeline,
    teamMembers,
    checklists,
    serviceOrders,
    supplierQuotes,
    attachments,
    attachmentLinks,
    attachmentQuota,
}: Props) {
    const [view, setView] = useState<'list' | 'timeline' | 'calendar'>('list');
    const [confirmingValidation, setConfirmingValidation] = useState<number | null>(null);
    const taskForm = useForm({
        title: '',
        description: '',
        phase: 'preparation',
        priority: 'normal',
        due_date: '',
        dependency_id: '',
        technical_validation_id: '',
    });
    const validationForm = useForm({ reference: '', measurements: '', evidence: '', supplier_name: '' });
    const confirmationForm = useForm({ evidence: '', revision: 0 });

    function submitTask(event: FormEvent) {
        event.preventDefault();
        taskForm.post(`/opportunities/${opportunity.id}/production/tasks`, { preserveScroll: true, onSuccess: () => taskForm.reset() });
    }

    function submitValidation(event: FormEvent) {
        event.preventDefault();
        validationForm.transform((data) => ({
            ...data,
            measurements: data.measurements
                ? Object.fromEntries(
                      data.measurements
                          .split('\n')
                          .map((line) => line.split(':').map((part) => part.trim()))
                          .filter((parts) => parts.length === 2),
                  )
                : undefined,
        }));
        validationForm.post(`/opportunities/${opportunity.id}/production/technical-validations`, {
            preserveScroll: true,
            onSuccess: () => validationForm.reset(),
        });
    }

    function confirmValidation(event: FormEvent, validation: Validation) {
        event.preventDefault();
        confirmationForm.transform((data) => ({ ...data, revision: validation.revision }));
        confirmationForm.post(`/opportunities/${opportunity.id}/production/technical-validations/${validation.id}/confirm`, {
            preserveScroll: true,
            onSuccess: () => {
                confirmationForm.reset();
                setConfirmingValidation(null);
            },
        });
    }

    return (
        <AppLayout>
            <Head title={`Produção · ${opportunity.title}`} />
            <CasePageHeader
                id={opportunity.id}
                eyebrow="Produção"
                title="Controle de execução"
                client={opportunity.clientName}
                status={`evento em ${opportunity.eventDate || 'data a definir'}`}
                actions={
                    <span className="status-pill gray">
                        <Check size={12} /> {tasks.filter((task) => task.status === 'done').length}/{tasks.length} concluídas
                    </span>
                }
            />
            <SegmentedControl
                value={view}
                onChange={setView}
                label="Visualização da produção"
                segments={[
                    { value: 'list', label: 'Lista' },
                    { value: 'timeline', label: 'Linha do tempo' },
                    { value: 'calendar', label: 'Agenda' },
                ]}
            />
            <section className={`production-grid production-grid--${view}`}>
                <GlassSurface className="tasks-panel">
                    <div className="panel-heading">
                        <div>
                            <span className="eyebrow">
                                {view === 'list'
                                    ? 'CHECKLIST OPERACIONAL'
                                    : view === 'timeline'
                                      ? 'MARCOS E DEPENDÊNCIAS'
                                      : 'PRAZOS DO EVENTO'}
                            </span>
                            <h2>{view === 'list' ? 'Tarefas do evento' : view === 'timeline' ? 'Linha do tempo' : 'Agenda de produção'}</h2>
                        </div>
                        <ListChecks size={17} className="muted-icon" />
                    </div>
                    {tasks.length === 0 ? (
                        <div className="empty-state">
                            <ListChecks size={25} />
                            <h3>A produção começa aqui.</h3>
                            <p>Converta o escopo aprovado em uma prévia ou crie a primeira tarefa manualmente.</p>
                        </div>
                    ) : (
                        <div className="task-list">
                            {tasks.map((task) => (
                                <div className={`task-row ${task.status}`} key={task.id}>
                                    <button
                                        className="task-check"
                                        type="button"
                                        aria-label={`${task.status === 'done' ? 'Reabrir' : 'Concluir'} ${task.title}`}
                                        onClick={() =>
                                            router.patch(
                                                `/production/tasks/${task.id}`,
                                                { status: task.status === 'done' ? 'todo' : 'done' },
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <Check size={13} />
                                    </button>
                                    <div className="task-copy">
                                        <strong>{task.title}</strong>
                                        <small>
                                            {phaseLabels[task.phase || 'preparation']} · {task.description || 'Sem descrição'} ·{' '}
                                            {task.dueDate ? `prazo ${task.dueDate}` : 'sem prazo'}
                                        </small>
                                        {task.dependencyTitle && <small>Depende de: {task.dependencyTitle}</small>}
                                        {task.technicalValidation && (
                                            <small>
                                                Validação técnica: {task.technicalValidation.reference} ·{' '}
                                                {task.technicalValidation.status === 'confirmed' ? 'reconfirmada' : 'pendente'}
                                            </small>
                                        )}
                                        {task.blockedReason && (
                                            <small className="task-blocked-reason">Bloqueio: {task.blockedReason}</small>
                                        )}
                                    </div>
                                    <span
                                        className={`status-pill ${task.priority === 'high' ? 'amber' : task.status === 'done' ? 'green' : task.status === 'blocked' ? 'red' : 'gray'}`}
                                    >
                                        {task.priority === 'high' ? 'Prioridade alta' : labels[task.status]}
                                    </span>
                                    <UserRound size={14} className="muted-icon" />
                                </div>
                            ))}
                        </div>
                    )}
                </GlassSurface>
                <GlassSurface className="task-form-card">
                    <div className="panel-heading">
                        <div>
                            <span className="eyebrow">NOVO MARCO</span>
                            <h2>Adicionar tarefa</h2>
                        </div>
                        <Plus size={17} className="muted-icon" />
                    </div>
                    <form className="form-grid compact-form" onSubmit={submitTask}>
                        <label>
                            Título
                            <input
                                value={taskForm.data.title}
                                onChange={(event) => taskForm.setData('title', event.target.value)}
                                placeholder="Ex.: confirmar fornecedor final"
                                required
                            />
                        </label>
                        <label>
                            Descrição
                            <textarea
                                rows={3}
                                value={taskForm.data.description}
                                onChange={(event) => taskForm.setData('description', event.target.value)}
                                placeholder="Contexto para quem vai executar"
                            />
                        </label>
                        <div className="two-fields">
                            <label>
                                Fase
                                <select value={taskForm.data.phase} onChange={(event) => taskForm.setData('phase', event.target.value)}>
                                    <option value="preparation">Preparação</option>
                                    <option value="setup">Montagem</option>
                                    <option value="event">Evento</option>
                                    <option value="teardown">Desmontagem</option>
                                </select>
                            </label>
                            <label>
                                Prioridade
                                <select
                                    value={taskForm.data.priority}
                                    onChange={(event) => taskForm.setData('priority', event.target.value)}
                                >
                                    <option value="normal">Normal</option>
                                    <option value="high">Alta</option>
                                    <option value="low">Baixa</option>
                                </select>
                            </label>
                        </div>
                        <div className="two-fields">
                            <label>
                                Prazo
                                <input
                                    type="date"
                                    value={taskForm.data.due_date}
                                    onChange={(event) => taskForm.setData('due_date', event.target.value)}
                                />
                            </label>
                            <label>
                                Depende de
                                <select
                                    value={taskForm.data.dependency_id}
                                    onChange={(event) => taskForm.setData('dependency_id', event.target.value)}
                                >
                                    <option value="">Nenhuma</option>
                                    {tasks.map((task) => (
                                        <option key={task.id} value={task.id}>
                                            {task.title}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        </div>
                        {validations.length > 0 && (
                            <label>
                                Validação técnica relacionada
                                <select
                                    value={taskForm.data.technical_validation_id}
                                    onChange={(event) => taskForm.setData('technical_validation_id', event.target.value)}
                                >
                                    <option value="">Nenhuma</option>
                                    {validations.map((validation) => (
                                        <option key={validation.id} value={validation.id}>
                                            {validation.reference}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        )}
                        <button className="button button-primary" type="submit" disabled={taskForm.processing}>
                            <Plus size={15} /> Criar tarefa
                        </button>
                    </form>
                    <div className="production-note">
                        <CircleAlert size={14} />
                        <span>
                            Dependências e reconfirmações técnicas bloqueiam uma liberação prematura. O histórico mantém cada decisão.
                        </span>
                    </div>
                </GlassSurface>
            </section>

            <section className="production-support-grid">
                <GlassSurface>
                    <div className="panel-heading">
                        <div>
                            <span className="eyebrow">ESCOPO CONTRATADO</span>
                            <h2>Preparar tarefas</h2>
                        </div>
                        <FileCheck2 size={18} className="muted-icon" />
                    </div>
                    <p>
                        Use o orçamento aprovado como ponto de partida. A conversão cria uma prévia editável e só grava tarefas depois da
                        confirmação.
                    </p>
                    {scopePreview ? (
                        <>
                            <div className="assistance-record">
                                <strong>{scopePreview.items.length} item(ns) preparados</strong>
                                <small>
                                    {scopePreview.status === 'confirmed'
                                        ? 'Prévia confirmada · tarefas preservadas'
                                        : 'Aguardando confirmação humana'}
                                </small>
                            </div>
                            {scopePreview.status !== 'confirmed' && (
                                <button
                                    className="button button-primary"
                                    type="button"
                                    onClick={() =>
                                        router.post(
                                            `/opportunities/${opportunity.id}/production/previews/${scopePreview.id}/confirm`,
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    <Check size={15} /> Confirmar tarefas
                                </button>
                            )}
                        </>
                    ) : (
                        <button
                            className="button button-subtle"
                            type="button"
                            onClick={() => router.post(`/opportunities/${opportunity.id}/production/prepare`, {}, { preserveScroll: true })}
                        >
                            <RefreshCw size={15} /> Preparar prévia do escopo
                        </button>
                    )}
                </GlassSurface>
                <GlassSurface>
                    <div className="panel-heading">
                        <div>
                            <span className="eyebrow">VALIDAÇÃO TÉCNICA</span>
                            <h2>Visita e reconfirmação</h2>
                        </div>
                        <ShieldCheck size={18} className="muted-icon" />
                    </div>
                    <p>Registre medidas, desenhos e evidências. Qualquer alteração invalida a confirmação anterior.</p>
                    <form className="form-grid compact-form" onSubmit={submitValidation}>
                        <label>
                            Referência
                            <input
                                required
                                value={validationForm.data.reference}
                                onChange={(event) => validationForm.setData('reference', event.target.value)}
                                placeholder="Ex.: Planta do palco"
                            />
                        </label>
                        <label>
                            Medidas (uma por linha)
                            <textarea
                                rows={3}
                                value={validationForm.data.measurements}
                                onChange={(event) => validationForm.setData('measurements', event.target.value)}
                                placeholder="largura: 8m\nprofundidade: 4m"
                            />
                        </label>
                        <label>
                            Fornecedor relacionado
                            <input
                                value={validationForm.data.supplier_name}
                                onChange={(event) => validationForm.setData('supplier_name', event.target.value)}
                                placeholder="Nome para reconfirmar"
                            />
                        </label>
                        <label>
                            Evidência da visita
                            <textarea
                                rows={2}
                                value={validationForm.data.evidence}
                                onChange={(event) => validationForm.setData('evidence', event.target.value)}
                                placeholder="Link, documento ou observação verificável"
                            />
                        </label>
                        <button className="button button-subtle" type="submit" disabled={validationForm.processing}>
                            <Plus size={15} /> Salvar validação
                        </button>
                    </form>
                    {validations.length > 0 && (
                        <div className="validation-list">
                            {validations.map((validation) => (
                                <article className="assistance-record" key={validation.id}>
                                    <div>
                                        <strong>{validation.reference}</strong>
                                        <small>
                                            {validation.supplierName || 'Fornecedor não informado'} ·{' '}
                                            {validation.status === 'confirmed' ? 'Reconfirmada' : 'Pendente'}
                                        </small>
                                        {validation.confirmationEvidence && <small>Evidência: {validation.confirmationEvidence}</small>}
                                    </div>
                                    {validation.status !== 'confirmed' &&
                                        (confirmingValidation === validation.id ? (
                                            <form className="inline-form" onSubmit={(event) => confirmValidation(event, validation)}>
                                                <input
                                                    required
                                                    value={confirmationForm.data.evidence}
                                                    onChange={(event) => confirmationForm.setData('evidence', event.target.value)}
                                                    placeholder="Evidência da reconfirmação"
                                                />
                                                <button
                                                    className="button button-primary"
                                                    type="submit"
                                                    disabled={confirmationForm.processing}
                                                >
                                                    Confirmar
                                                </button>
                                            </form>
                                        ) : (
                                            <button
                                                className="button button-subtle"
                                                type="button"
                                                onClick={() => {
                                                    confirmationForm.setData('revision', validation.revision);
                                                    setConfirmingValidation(validation.id);
                                                }}
                                            >
                                                Reconfirmar
                                            </button>
                                        ))}
                                </article>
                            ))}
                        </div>
                    )}
                </GlassSurface>
            </section>
            <section className="milestone-strip">
                <div>
                    <CalendarDays size={16} />
                    <span>Próximo marco</span>
                    <strong>{opportunity.eventDate || 'Defina a data do evento'}</strong>
                </div>
                <div>
                    <Check size={16} />
                    <span>Modo de validação</span>
                    <strong>Checklist manual com histórico</strong>
                </div>
            </section>
            <ProductionDeliveryConsole
                opportunity={opportunity}
                tasks={tasks}
                timeline={timeline}
                teamMembers={teamMembers}
                checklists={checklists}
                serviceOrders={serviceOrders}
                supplierQuotes={supplierQuotes}
                attachments={attachments}
            />
            <CaseAttachmentsPanel
                opportunityId={opportunity.id}
                attachments={attachments}
                attachmentLinks={attachmentLinks}
                attachmentQuota={attachmentQuota}
            />
        </AppLayout>
    );
}

type DeliveryConsoleProps = Pick<
    Props,
    'opportunity' | 'tasks' | 'timeline' | 'teamMembers' | 'checklists' | 'serviceOrders' | 'supplierQuotes' | 'attachments'
>;

function ProductionDeliveryConsole({
    opportunity,
    tasks,
    timeline,
    teamMembers,
    checklists,
    serviceOrders,
    supplierQuotes,
    attachments,
}: DeliveryConsoleProps) {
    const [photoSelections, setPhotoSelections] = useState<Record<number, string>>({});
    const [receiptSelections, setReceiptSelections] = useState<Record<number, string>>({});
    const scheduleForm = useForm({
        task_id: '',
        scheduled_starts_at: '',
        scheduled_ends_at: '',
        responsible_id: '',
        role: '',
    });
    const checklistForm = useForm({ task_id: '', phase: 'setup', title: '', items_text: '' });
    const orderForm = useForm({ source_quote_id: '', task_ids: [] as number[], title: '' });
    const activeImages = attachments.filter((attachment) => !attachment.isArchived && attachment.mimeType?.startsWith('image/'));
    const activeReceipts = attachments.filter(
        (attachment) => !attachment.isArchived && (attachment.mimeType === 'application/pdf' || attachment.mimeType?.startsWith('image/')),
    );
    const doneCount = tasks.filter((task) => task.status === 'done').length;
    const scheduledCount = tasks.filter((task) => task.scheduledStartsAt && task.scheduledEndsAt).length;
    const blockedCount = tasks.filter((task) => task.status === 'blocked').length;

    function submitSchedule(event: FormEvent) {
        event.preventDefault();
        if (!scheduleForm.data.task_id || !scheduleForm.data.responsible_id) return;
        scheduleForm.transform((data) => ({
            scheduled_starts_at: data.scheduled_starts_at,
            scheduled_ends_at: data.scheduled_ends_at,
            team: [
                {
                    user_id: Number(data.responsible_id),
                    role: data.role,
                    is_responsible: true,
                },
            ],
        }));
        scheduleForm.post(`/production/tasks/${scheduleForm.data.task_id}/schedule`, {
            preserveScroll: true,
            onSuccess: () => scheduleForm.reset(),
        });
    }

    function submitChecklist(event: FormEvent) {
        event.preventDefault();
        checklistForm.transform((data) => ({
            task_id: data.task_id || null,
            phase: data.phase,
            title: data.title,
            items: data.items_text
                .split('\n')
                .map((line) => line.trim())
                .filter(Boolean)
                .map((line) => ({
                    title: line.replace(/\s*\*$/, ''),
                    requires_photo: line.endsWith('*'),
                })),
        }));
        checklistForm.post(`/opportunities/${opportunity.id}/production/checklists`, {
            preserveScroll: true,
            onSuccess: () => checklistForm.reset(),
        });
    }

    function submitOrder(event: FormEvent) {
        event.preventDefault();
        const quote = supplierQuotes.find((item) => item.id === Number(orderForm.data.source_quote_id));
        if (!quote || orderForm.data.task_ids.length === 0) return;
        orderForm.transform((data) => ({
            supplier_id: quote.supplierId,
            source_quote_id: Number(data.source_quote_id),
            task_ids: data.task_ids,
            title: data.title,
        }));
        orderForm.post(`/opportunities/${opportunity.id}/production/service-orders`, {
            preserveScroll: true,
            onSuccess: () => orderForm.reset(),
        });
    }

    return (
        <section className="production-delivery-console" aria-label="Controle operacional de produção">
            <header className="production-delivery-console__header">
                <div>
                    <span className="eyebrow">CONTROLE OPERACIONAL</span>
                    <h2>Agenda, conferências e fornecedores</h2>
                    <p>A linha de execução é atualizada por tarefas reais, com responsáveis, evidências privadas e escopo congelado.</p>
                </div>
                <UsersRound size={19} aria-hidden="true" />
            </header>

            <div className="production-delivery-console__metrics">
                <Metric value={`${doneCount}/${tasks.length}`} label="tarefas concluídas" />
                <Metric value={`${scheduledCount}`} label="com escala" />
                <Metric value={`${blockedCount}`} label="bloqueios" critical={blockedCount > 0} />
                <Metric
                    value={`${checklists.filter((checklist) => checklist.status === 'completed').length}/${checklists.length}`}
                    label="checklists fechados"
                />
            </div>

            <div className="production-delivery-console__grid">
                <Surface as="section" tone="plain" className="production-delivery-console__timeline">
                    <div className="production-delivery-console__heading">
                        <div>
                            <span className="eyebrow">CRONOGRAMA</span>
                            <h3>Linha do tempo com dependências</h3>
                        </div>
                        <CalendarClock size={17} aria-hidden="true" />
                    </div>
                    {timeline.length === 0 ? (
                        <p className="production-delivery-console__empty">
                            Defina início, fim e responsável para liberar a leitura cronológica.
                        </p>
                    ) : (
                        <div className="production-delivery-timeline">
                            {timeline.map((item, index) => (
                                <article key={item.id}>
                                    <span>{String(index + 1).padStart(2, '0')}</span>
                                    <div>
                                        <strong>{item.title}</strong>
                                        <small>
                                            {formatDateTime(item.scheduled_starts_at)} — {formatDateTime(item.scheduled_ends_at)}
                                        </small>
                                        <small>
                                            {phaseLabels[item.phase || 'preparation']}
                                            {item.dependency_title ? ` · após ${item.dependency_title}` : ''}
                                        </small>
                                        {item.team.length > 0 && (
                                            <em>
                                                {item.team
                                                    .map(
                                                        (member) =>
                                                            `${member.name || 'Pessoa removida'}${member.is_responsible ? ' · responsável' : ''}`,
                                                    )
                                                    .join(' · ')}
                                            </em>
                                        )}
                                    </div>
                                </article>
                            ))}
                        </div>
                    )}
                </Surface>

                <Surface as="section" tone="plain" className="production-delivery-console__schedule">
                    <div className="production-delivery-console__heading">
                        <div>
                            <span className="eyebrow">ESCALA</span>
                            <h3>Reservar responsável</h3>
                        </div>
                        <UsersRound size={17} aria-hidden="true" />
                    </div>
                    <form className="form-grid compact-form" onSubmit={submitSchedule}>
                        <label>
                            Tarefa
                            <select
                                value={scheduleForm.data.task_id}
                                onChange={(event) => scheduleForm.setData('task_id', event.target.value)}
                                required
                            >
                                <option value="">Selecionar</option>
                                {tasks.map((task) => (
                                    <option key={task.id} value={task.id}>
                                        {task.title}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <div className="two-fields">
                            <label>
                                Início
                                <input
                                    type="datetime-local"
                                    value={scheduleForm.data.scheduled_starts_at}
                                    onChange={(event) => scheduleForm.setData('scheduled_starts_at', event.target.value)}
                                    required
                                />
                            </label>
                            <label>
                                Fim
                                <input
                                    type="datetime-local"
                                    value={scheduleForm.data.scheduled_ends_at}
                                    onChange={(event) => scheduleForm.setData('scheduled_ends_at', event.target.value)}
                                    required
                                />
                            </label>
                        </div>
                        <label>
                            Responsável
                            <select
                                value={scheduleForm.data.responsible_id}
                                onChange={(event) => scheduleForm.setData('responsible_id', event.target.value)}
                                required
                            >
                                <option value="">Selecionar pessoa</option>
                                {teamMembers.map((member) => (
                                    <option key={member.id} value={member.id}>
                                        {member.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label>
                            Função
                            <input
                                value={scheduleForm.data.role}
                                onChange={(event) => scheduleForm.setData('role', event.target.value)}
                                placeholder="Ex.: coordenação de montagem"
                            />
                        </label>
                        {scheduleForm.errors.scheduled_starts_at && (
                            <p className="production-delivery-console__error">{scheduleForm.errors.scheduled_starts_at}</p>
                        )}
                        <button className="button button-primary" type="submit" disabled={scheduleForm.processing}>
                            <CalendarClock size={14} /> Conferir e reservar
                        </button>
                    </form>
                </Surface>
            </div>

            <div className="production-delivery-console__grid production-delivery-console__grid--wide">
                <Surface as="section" tone="plain" className="production-delivery-console__checklists">
                    <div className="production-delivery-console__heading">
                        <div>
                            <span className="eyebrow">CONFERÊNCIAS</span>
                            <h3>Montagem e devolução</h3>
                        </div>
                        <ClipboardCheck size={17} aria-hidden="true" />
                    </div>
                    {checklists.length === 0 ? (
                        <p className="production-delivery-console__empty">Crie um checklist para tornar a entrega de campo conferível.</p>
                    ) : (
                        <div className="production-delivery-checklists">
                            {checklists.map((checklist) => (
                                <article key={checklist.id}>
                                    <header>
                                        <div>
                                            <strong>{checklist.title}</strong>
                                            <small>
                                                {phaseLabels[checklist.phase]}
                                                {checklist.taskTitle ? ` · ${checklist.taskTitle}` : ''}
                                            </small>
                                        </div>
                                        <span>{checklist.status === 'completed' ? 'Concluído' : 'Aberto'}</span>
                                    </header>
                                    {checklist.items.map((item) => (
                                        <div className={item.completedAt ? 'is-complete' : ''} key={item.id}>
                                            <CheckCircle2 size={15} />
                                            <div>
                                                <strong>{item.title}</strong>
                                                {item.requiresPhoto && <small>foto obrigatória</small>}
                                                {item.photo && <a href={item.photo.downloadUrl}>{item.photo.name}</a>}
                                            </div>
                                            {!item.completedAt && (
                                                <div className="production-delivery-checklists__action">
                                                    {item.requiresPhoto && (
                                                        <select
                                                            aria-label={`Foto para ${item.title}`}
                                                            value={photoSelections[item.id] || ''}
                                                            onChange={(event) =>
                                                                setPhotoSelections((current) => ({
                                                                    ...current,
                                                                    [item.id]: event.target.value,
                                                                }))
                                                            }
                                                        >
                                                            <option value="">Selecionar foto</option>
                                                            {activeImages.map((image) => (
                                                                <option key={image.id} value={image.id}>
                                                                    {image.originalName}
                                                                </option>
                                                            ))}
                                                        </select>
                                                    )}
                                                    <button
                                                        className="button button-subtle"
                                                        type="button"
                                                        disabled={item.requiresPhoto && !photoSelections[item.id]}
                                                        onClick={() =>
                                                            router.post(
                                                                `/opportunities/${opportunity.id}/production/checklists/items/${item.id}/complete`,
                                                                { photo_attachment_id: photoSelections[item.id] || null },
                                                                { preserveScroll: true },
                                                            )
                                                        }
                                                    >
                                                        <Check size={13} /> Conferir
                                                    </button>
                                                </div>
                                            )}
                                        </div>
                                    ))}
                                </article>
                            ))}
                        </div>
                    )}
                </Surface>

                <Surface as="section" tone="plain" className="production-delivery-console__form">
                    <div className="production-delivery-console__heading">
                        <div>
                            <span className="eyebrow">NOVO ROTEIRO</span>
                            <h3>Criar checklist</h3>
                        </div>
                        <Plus size={17} aria-hidden="true" />
                    </div>
                    <form className="form-grid compact-form" onSubmit={submitChecklist}>
                        <div className="two-fields">
                            <label>
                                Fase
                                <select
                                    value={checklistForm.data.phase}
                                    onChange={(event) => checklistForm.setData('phase', event.target.value)}
                                >
                                    <option value="setup">Montagem</option>
                                    <option value="teardown">Desmontagem</option>
                                </select>
                            </label>
                            <label>
                                Tarefa
                                <select
                                    value={checklistForm.data.task_id}
                                    onChange={(event) => checklistForm.setData('task_id', event.target.value)}
                                >
                                    <option value="">Sem tarefa</option>
                                    {tasks
                                        .filter((task) => task.phase === checklistForm.data.phase)
                                        .map((task) => (
                                            <option key={task.id} value={task.id}>
                                                {task.title}
                                            </option>
                                        ))}
                                </select>
                            </label>
                        </div>
                        <label>
                            Título
                            <input
                                value={checklistForm.data.title}
                                onChange={(event) => checklistForm.setData('title', event.target.value)}
                                required
                                placeholder="Ex.: Liberação de montagem"
                            />
                        </label>
                        <label>
                            Itens, um por linha
                            <textarea
                                rows={5}
                                value={checklistForm.data.items_text}
                                onChange={(event) => checklistForm.setData('items_text', event.target.value)}
                                required
                                placeholder={'Estrutura posicionada *\nEnergia conferida\nAcesso liberado'}
                            />
                        </label>
                        <p className="production-delivery-console__hint">
                            <CircleAlert size={13} /> Termine uma linha com <strong>*</strong> para exigir foto.
                        </p>
                        <button className="button button-primary" type="submit" disabled={checklistForm.processing}>
                            <Plus size={14} /> Criar checklist
                        </button>
                    </form>
                </Surface>
            </div>

            <div className="production-delivery-console__grid production-delivery-console__grid--wide">
                <Surface as="section" tone="plain" className="production-delivery-console__orders">
                    <div className="production-delivery-console__heading">
                        <div>
                            <span className="eyebrow">FORNECEDORES</span>
                            <h3>Ordens de serviço</h3>
                        </div>
                        <ReceiptText size={17} aria-hidden="true" />
                    </div>
                    {serviceOrders.length === 0 ? (
                        <p className="production-delivery-console__empty">
                            Gere uma ordem a partir de uma cotação vigente e das tarefas selecionadas.
                        </p>
                    ) : (
                        <div className="production-delivery-orders">
                            {serviceOrders.map((order) => (
                                <article key={order.id}>
                                    <header>
                                        <div>
                                            <span>{order.code}</span>
                                            <strong>{order.title}</strong>
                                            <small>
                                                {order.supplierName || 'Fornecedor indisponível'} · {formatCurrency(order.amountCents)}
                                            </small>
                                        </div>
                                        <em>
                                            {order.status === 'draft' ? 'Rascunho' : order.status === 'issued' ? 'Emitida' : 'Recebida'}
                                        </em>
                                    </header>
                                    <p>
                                        {order.scope.quote?.service || 'Serviço não identificado'} · {order.scope.tasks?.length || 0}{' '}
                                        tarefa(s) vinculada(s)
                                    </p>
                                    <footer>
                                        {order.status === 'draft' && (
                                            <button
                                                className="button button-primary"
                                                type="button"
                                                onClick={() =>
                                                    router.post(
                                                        `/opportunities/${opportunity.id}/production/service-orders/${order.id}/issue`,
                                                        {},
                                                        { preserveScroll: true },
                                                    )
                                                }
                                            >
                                                <FileCheck2 size={13} /> Emitir ordem
                                            </button>
                                        )}
                                        {order.status === 'issued' && (
                                            <div>
                                                <select
                                                    aria-label={`Comprovante para ${order.title}`}
                                                    value={receiptSelections[order.id] || ''}
                                                    onChange={(event) =>
                                                        setReceiptSelections((current) => ({ ...current, [order.id]: event.target.value }))
                                                    }
                                                >
                                                    <option value="">Selecionar comprovante</option>
                                                    {activeReceipts.map((attachment) => (
                                                        <option key={attachment.id} value={attachment.id}>
                                                            {attachment.originalName}
                                                        </option>
                                                    ))}
                                                </select>
                                                <button
                                                    className="button button-subtle"
                                                    type="button"
                                                    disabled={!receiptSelections[order.id]}
                                                    onClick={() =>
                                                        router.post(
                                                            `/opportunities/${opportunity.id}/production/service-orders/${order.id}/receive`,
                                                            { receipt_attachment_id: receiptSelections[order.id] },
                                                            { preserveScroll: true },
                                                        )
                                                    }
                                                >
                                                    <Check size={13} /> Registrar recebimento
                                                </button>
                                            </div>
                                        )}
                                        {order.status === 'received' && (
                                            <small>
                                                Recebida em {formatDateTime(order.receivedAt)}
                                                {order.receipt && <a href={order.receipt.downloadUrl}>{order.receipt.name}</a>}
                                            </small>
                                        )}
                                    </footer>
                                </article>
                            ))}
                        </div>
                    )}
                </Surface>

                <Surface as="section" tone="plain" className="production-delivery-console__form">
                    <div className="production-delivery-console__heading">
                        <div>
                            <span className="eyebrow">NOVA ORDEM</span>
                            <h3>Gerar rascunho</h3>
                        </div>
                        <Plus size={17} aria-hidden="true" />
                    </div>
                    <form className="form-grid compact-form" onSubmit={submitOrder}>
                        <label>
                            Cotação vigente
                            <select
                                value={orderForm.data.source_quote_id}
                                onChange={(event) => orderForm.setData('source_quote_id', event.target.value)}
                                required
                            >
                                <option value="">Selecionar cotação</option>
                                {supplierQuotes.map((quote) => (
                                    <option key={quote.id} value={quote.id}>
                                        {quote.supplierName} · {quote.service} · {formatCurrency(quote.amountCents)}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label>
                            Título
                            <input
                                value={orderForm.data.title}
                                onChange={(event) => orderForm.setData('title', event.target.value)}
                                required
                                placeholder="Ex.: Ordem de serviço · som"
                            />
                        </label>
                        <label>
                            Tarefas do fornecedor
                            <select
                                multiple
                                value={orderForm.data.task_ids.map(String)}
                                onChange={(event) =>
                                    orderForm.setData(
                                        'task_ids',
                                        Array.from(event.currentTarget.selectedOptions, (option) => Number(option.value)),
                                    )
                                }
                            >
                                {tasks.map((task) => (
                                    <option key={task.id} value={task.id}>
                                        {task.title}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <p className="production-delivery-console__hint">
                            <CircleAlert size={13} /> A emissão congela cotação e tarefas. Para selecionar mais de uma tarefa, use ⌘.
                        </p>
                        <button
                            className="button button-primary"
                            type="submit"
                            disabled={orderForm.processing || orderForm.data.task_ids.length === 0}
                        >
                            <ReceiptText size={14} /> Gerar rascunho
                        </button>
                    </form>
                </Surface>
            </div>
        </section>
    );
}

function Metric({ value, label, critical = false }: { value: string; label: string; critical?: boolean }) {
    return (
        <div className={critical ? 'is-critical' : ''}>
            <strong>{value}</strong>
            <span>{label}</span>
        </div>
    );
}

function formatDateTime(value: string | null): string {
    if (!value) return 'horário indisponível';
    return new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }).format(new Date(value));
}

function formatCurrency(cents: number | null): string {
    if (cents === null) return 'valor não informado';
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(cents / 100);
}
