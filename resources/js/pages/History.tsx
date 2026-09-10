import { Head, Link, router } from '@inertiajs/react';
import { Activity, AlertTriangle, CheckCircle2, ClipboardCheck, Filter, History as HistoryIcon, Search } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { BarSeries } from '../components/charts/BarSeries';
import { AppLayout } from '../layout';
import { CasePageHeader } from '../components/CasePageHeader';
import { PageHeader } from '../components/ui/PageHeader';
import { Surface } from '../components/ui/Surface';

type RecordItem = {
    id: number;
    action: string;
    label?: string;
    module?: string;
    user: string;
    caseId: number | null;
    metadata: Record<string, unknown> | null;
    createdAt: string;
};
type Metrics = {
    activeCases: number;
    openProductionTasks: number;
    overdueWork: number;
    pendingReviews: number;
    workload: { id: number; name: string; openItems: number }[];
    measured: boolean;
};
type Props = {
    records: RecordItem[];
    search: string;
    filters: { module: string; person: number | ''; from: string; to: string };
    users: { id: number; name: string }[];
    case?: { id: number; title: string } | null;
    feedback: { rating: number; category: string; comment?: string | null; createdAt: string }[];
    metrics: Metrics;
};
const actions: Record<string, string> = {
    'opportunity.stage_changed': 'Etapa do caso atualizada',
    'context.preview_confirmed': 'Contexto revisado e aplicado como rascunho',
    'viability.draft_saved': 'Rascunho da Viabilidade atualizado',
    'briefing.reviewed': 'Briefing revisado',
    'briefing.approved': 'Briefing aprovado',
    'budget.item_created': 'Item adicionado ao orçamento',
    'budget.approved': 'Versão do orçamento aprovada',
    'document.reviewed': 'Documento revisado',
    'document.sent': 'Envio de documento registrado',
    'production.task_created': 'Tarefa de produção criada',
};
const fieldLabels: Record<string, string> = {
    from: 'Antes',
    to: 'Depois',
    note: 'Justificativa',
    reason: 'Motivo',
    revision: 'Revisão',
    modules: 'Módulos',
    changes: 'Alterações',
    evidence: 'Evidência',
    opportunity_id: 'Caso',
    document_id: 'Documento',
    budget_id: 'Orçamento',
    task_ids: 'Tarefas relacionadas',
    invalidated: 'Revisão invalidada',
};

export default function History({ records, search, filters, users, case: currentCase, feedback, metrics }: Props) {
    const [form, setForm] = useState({
        q: search,
        module: filters.module,
        person: String(filters.person),
        from: filters.from,
        to: filters.to,
    });
    const base = currentCase ? `/opportunities/${currentCase.id}/history` : '/history';
    function find(event: FormEvent) {
        event.preventDefault();
        router.get(base, form, { preserveState: true, replace: true });
    }
    const header = currentCase ? (
        <CasePageHeader id={currentCase.id} eyebrow="Memória rastreável" title="Histórico do caso" client={currentCase.title} />
    ) : (
        <PageHeader
            eyebrow="DECISÕES E RESPONSABILIDADE"
            title="Histórico operacional"
            description="Alterações, revisões e aprovações da equipe."
        />
    );
    return (
        <AppLayout>
            <Head title="Histórico operacional" />
            {header}
            <div className="history-metrics" aria-label="Indicadores operacionais">
                <Surface className="history-metric">
                    <span className="history-metric__icon">
                        <CheckCircle2 size={17} />
                    </span>
                    <div>
                        <strong>{metrics.activeCases}</strong>
                        <span>casos ativos</span>
                    </div>
                </Surface>
                <Surface className="history-metric">
                    <span className="history-metric__icon">
                        <ClipboardCheck size={17} />
                    </span>
                    <div>
                        <strong>{metrics.pendingReviews}</strong>
                        <span>revisões pendentes</span>
                    </div>
                </Surface>
                <Surface className="history-metric">
                    <span className="history-metric__icon history-metric__icon--warning">
                        <AlertTriangle size={17} />
                    </span>
                    <div>
                        <strong>{metrics.overdueWork}</strong>
                        <span>trabalhos atrasados</span>
                    </div>
                </Surface>
                <Surface className="history-metric">
                    <span className="history-metric__icon">
                        <Activity size={17} />
                    </span>
                    <div>
                        <strong>{metrics.openProductionTasks}</strong>
                        <span>tarefas de produção</span>
                    </div>
                </Surface>
            </div>
            <Surface className="history-filter-panel">
                <form className="history-filters" onSubmit={find}>
                    <label>
                        <span>Buscar</span>
                        <div className="input-with-icon">
                            <Search size={15} />
                            <input
                                value={form.q}
                                onChange={(event) => setForm({ ...form, q: event.target.value })}
                                placeholder="Decisão, módulo ou ação"
                            />
                        </div>
                    </label>
                    <label>
                        <span>Módulo</span>
                        <select value={form.module} onChange={(event) => setForm({ ...form, module: event.target.value })}>
                            <option value="">Todos</option>
                            <option value="briefing">Briefing</option>
                            <option value="viability">Viabilidade</option>
                            <option value="budget">Orçamento</option>
                            <option value="document">Documentos</option>
                            <option value="production">Produção</option>
                            <option value="context">IA e contexto</option>
                            <option value="opportunity">Comercial</option>
                            <option value="activity">Tarefas</option>
                        </select>
                    </label>
                    <label>
                        <span>Pessoa</span>
                        <select value={form.person} onChange={(event) => setForm({ ...form, person: event.target.value })}>
                            <option value="">Todas</option>
                            {users.map((user) => (
                                <option key={user.id} value={user.id}>
                                    {user.name}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label>
                        <span>De</span>
                        <input type="date" value={form.from} onChange={(event) => setForm({ ...form, from: event.target.value })} />
                    </label>
                    <label>
                        <span>Até</span>
                        <input type="date" value={form.to} onChange={(event) => setForm({ ...form, to: event.target.value })} />
                    </label>
                    <button className="button button-subtle">
                        <Filter size={15} /> Filtrar
                    </button>
                </form>
            </Surface>
            <Surface className="human-history">
                <div className="panel-heading">
                    <div>
                        <span className="eyebrow">LINHA DO TEMPO</span>
                        <h2>{records.length} registros encontrados</h2>
                    </div>
                    <HistoryIcon size={18} />
                </div>
                {!records.length && (
                    <div className="empty-state">
                        <HistoryIcon size={24} />
                        <h3>Nenhuma decisão encontrada</h3>
                        <p>Ajuste os filtros ou continue trabalhando no caso.</p>
                    </div>
                )}
                {records.map((record) => (
                    <details className="human-history__item" key={record.id}>
                        <summary>
                            <span className="timeline-dot" />
                            <span>
                                <strong>{record.label ?? actions[record.action] ?? record.action.replaceAll('.', ' · ')}</strong>
                                <small>
                                    {record.module ? `${record.module} · ` : ''}
                                    {record.user} · {record.createdAt}
                                </small>
                            </span>
                            <span className="status-badge status-badge--neutral">Ver detalhes</span>
                        </summary>
                        <div className="human-history__details">
                            {record.metadata ? (
                                Object.entries(record.metadata).map(([key, value]) => (
                                    <div key={key}>
                                        <span>{fieldLabels[key] ?? key}</span>
                                        <strong>
                                            {Array.isArray(value)
                                                ? value.join(', ')
                                                : typeof value === 'object'
                                                  ? JSON.stringify(value)
                                                  : String(value)}
                                        </strong>
                                    </div>
                                ))
                            ) : (
                                <p>Sem metadados adicionais.</p>
                            )}
                            {record.caseId && (
                                <Link className="button button-subtle" href={`/opportunities/${record.caseId}`}>
                                    Abrir caso
                                </Link>
                            )}
                        </div>
                    </details>
                ))}
            </Surface>
            {metrics.workload.length > 0 && (
                <Surface className="history-workload">
                    <div className="panel-heading">
                        <div>
                            <span className="eyebrow">DISTRIBUIÇÃO</span>
                            <h2>Trabalho em aberto por pessoa</h2>
                        </div>
                        <Activity size={18} />
                    </div>
                    <BarSeries
                        title="Trabalho em aberto por pessoa"
                        data={metrics.workload.map((person) => ({ label: person.name, value: person.openItems }))}
                        format={(value) => `${value} ${value === 1 ? 'item' : 'itens'}`}
                        emptyMessage="Nenhum item em aberto atribuído."
                    />
                </Surface>
            )}
            {feedback.length > 0 && (
                <Surface>
                    <h2>Feedback do piloto</h2>
                    <p>Separado da auditoria operacional.</p>
                    {feedback.map((item, index) => (
                        <article className="feedback-history-row" key={index}>
                            <strong>
                                {item.rating}/5 · {item.category}
                            </strong>
                            <p>{item.comment || 'Sem comentário'}</p>
                            <small>{item.createdAt}</small>
                        </article>
                    ))}
                </Surface>
            )}
        </AppLayout>
    );
}
