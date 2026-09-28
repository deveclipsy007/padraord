import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { ArrowUpRight, CalendarDays, Check, Clock3, Pause, Play, Users, Workflow } from 'lucide-react';
import { AppLayout } from '../layout';
import { getJson, localDate } from '../components/control-utils';
type Pending = {
    id: number;
    title: string;
    project: string;
    opportunity_id: number;
    owner: string;
    awaiting: string;
    due_date: string | null;
    next_contact_at: string | null;
};
type Capacity = {
    id: number;
    name: string;
    jobTitle: string;
    open: number;
    unscheduled: number;
    hours: number;
    capacityHours: number | null;
    conflicts: string[];
    tasks: { id: number; title: string; project: string; start: string; end: string; href: string }[];
};
type RulePreview = {
    key: string;
    title: string;
    fingerprint: string;
    rows: { source: string; title: string; project: string; reason: string }[];
};
type Props = {
    pending: Pending[];
    capacity: Capacity[];
    rules: { key: string; title: string; record: { enabled: boolean; last_run_at: string | null } | null }[];
    runs: { id: number; title: string; created_at: string; undone_at: string | null }[];
    projects: { id: number; title: string }[];
};
export default function Operations({ pending, capacity, rules, runs, projects }: Props) {
    const { auth, errors } = usePage<{ auth: { user: { isAdmin: boolean } }; errors: Record<string, string> }>().props;
    const [limits, setLimits] = useState<Record<number, string>>({});
    const [tab, setTab] = useState('pending');
    const [side, setSide] = useState('all');
    const [preview, setPreview] = useState<RulePreview | null>(null);
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);
    async function load(key: string) {
        setBusy(true);
        setError('');
        try {
            setPreview(await getJson<RulePreview>('/operations/rules/' + key + '/preview'));
        } catch (e) {
            setError((e as Error).message);
        } finally {
            setBusy(false);
        }
    }
    return (
        <AppLayout>
            <Head title="Controle operacional" />
            <header className="control-hero">
                <div>
                    <span className="rd-eyebrow">A OPERAÇÃO, EM PERSPECTIVA</span>
                    <h1>
                        Menos pontas soltas.
                        <br />
                        <em>Mais clareza para agir.</em>
                    </h1>
                    <p>Pendências, pessoas e rotinas conectadas ao trabalho de cada projeto.</p>
                </div>
                <div className="control-hero-symbol">
                    <Workflow size={62} strokeWidth={1} />
                </div>
            </header>
            <nav className="control-tabs" aria-label="Áreas do controle">
                {[
                    ['pending', 'Pendências'],
                    ['capacity', 'Equipe e capacidade'],
                    ['rules', 'Automações'],
                ].map(([key, label]) => (
                    <button key={key} aria-pressed={tab === key} onClick={() => setTab(key)}>
                        {label}
                    </button>
                ))}
            </nav>
            {Object.values(errors || {}).map((e, i) => (
                <p className="control-error" key={i} role="alert">
                    {e}
                </p>
            ))}
            {error && (
                <p className="control-error" role="alert">
                    {error}
                </p>
            )}
            {tab === 'pending' && (
                <section className="control-panel">
                    <div className="control-section-heading">
                        <div>
                            <span className="rd-eyebrow">QUEM PRECISA RESPONDER?</span>
                            <h2>O próximo retorno.</h2>
                        </div>
                        <select aria-label="Filtrar pendências" value={side} onChange={(e) => setSide(e.target.value)}>
                            <option value="all">Cliente e equipe</option>
                            <option value="client">Aguardando cliente</option>
                            <option value="team">Aguardando equipe</option>
                        </select>
                    </div>
                    <div className="control-pending-list">
                        {pending
                            .filter((p) => side === 'all' || p.awaiting === side)
                            .map((p) => (
                                <Link href={'/opportunities/' + p.opportunity_id + '/control'} className="control-pending-row" key={p.id}>
                                    <span className={'control-status ' + (p.awaiting === 'client' ? 'lavender' : 'sand')}>
                                        {p.awaiting === 'client' ? 'Cliente' : 'Equipe'}
                                    </span>
                                    <div>
                                        <strong>{p.title}</strong>
                                        <small>
                                            {p.project} · {p.owner}
                                        </small>
                                    </div>
                                    <div>
                                        <span>{localDate(p.due_date)}</span>
                                        <small>Próximo contato: {localDate(p.next_contact_at)}</small>
                                    </div>
                                    <ArrowUpRight size={18} />
                                </Link>
                            ))}
                    </div>
                    {!pending.filter((p) => side === 'all' || p.awaiting === side).length && (
                        <div className="control-empty">
                            <Check size={24} />
                            <h3>Nenhuma pendência neste filtro.</h3>
                            <p>Abra o controle de um projeto para registrar o próximo retorno.</p>
                        </div>
                    )}
                    <details className="control-project-picker">
                        <summary>Abrir controle de um projeto</summary>
                        {projects.map((p) => (
                            <Link key={p.id} href={'/opportunities/' + p.id + '/control'}>
                                {p.title}
                                <ArrowUpRight size={14} />
                            </Link>
                        ))}
                    </details>
                </section>
            )}
            {tab === 'capacity' && (
                <section>
                    <div className="control-section-heading">
                        <div>
                            <span className="rd-eyebrow">PRÓXIMOS SETE DIAS</span>
                            <h2>A equipe, antes de distribuir.</h2>
                            <p>Horas agendadas não equivalem à disponibilidade. Tarefas sem horário ficam destacadas para planejamento.</p>
                        </div>
                        <Users size={30} />
                    </div>
                    <div className="control-capacity-grid">
                        {capacity.map((p) => (
                            <article key={p.id} className="control-panel">
                                <div className="control-person">
                                    <span>
                                        {p.name
                                            .split(' ')
                                            .map((n) => n[0])
                                            .slice(0, 2)
                                            .join('')}
                                    </span>
                                    <div>
                                        <h3>{p.name}</h3>
                                        <small>{p.jobTitle}</small>
                                    </div>
                                </div>
                                <div className="control-capacity-stats">
                                    <div>
                                        <strong>{p.hours}h</strong>
                                        <small>agendadas na semana</small>
                                    </div>
                                    <div>
                                        <strong>{p.unscheduled}</strong>
                                        <small>sem horário</small>
                                    </div>
                                    <div>
                                        <strong>{p.open}</strong>
                                        <small>tarefas abertas</small>
                                    </div>
                                </div>
                                {p.capacityHours !== null ? (
                                    <div className="control-capacity-limit">
                                        <div className="control-capacity-bar">
                                            <span style={{ width: `${Math.min(100, (p.hours / p.capacityHours) * 100)}%` }} />
                                        </div>
                                        <p>
                                            {p.hours > p.capacityHours
                                                ? `Acima da capacidade em ${(p.hours - p.capacityHours).toFixed(1)}h`
                                                : `${(p.capacityHours - p.hours).toFixed(1)}h restantes da capacidade configurada`}{' '}
                                            · {p.capacityHours}h/semana
                                        </p>
                                        {p.unscheduled > 0 && <small>As tarefas sem horário ainda não entram nessa conta.</small>}
                                    </div>
                                ) : (
                                    <p className="control-note">Capacidade semanal ainda não definida.</p>
                                )}
                                {auth.user.isAdmin && (
                                    <details className="control-capacity-config">
                                        <summary>Definir capacidade semanal</summary>
                                        <form
                                            onSubmit={(e) => {
                                                e.preventDefault();
                                                router.post('/operations/capacity/' + p.id, {
                                                    hours: limits[p.id] ?? p.capacityHours ?? '',
                                                });
                                            }}
                                        >
                                            <label>
                                                Horas por semana
                                                <input
                                                    type="number"
                                                    min="0.5"
                                                    max="168"
                                                    step="0.5"
                                                    required
                                                    value={limits[p.id] ?? p.capacityHours ?? ''}
                                                    onChange={(e) => setLimits({ ...limits, [p.id]: e.target.value })}
                                                />
                                            </label>
                                            <button className="button">Salvar capacidade</button>
                                        </form>
                                    </details>
                                )}
                                {p.conflicts.map((c, i) => (
                                    <p className="control-error" key={i}>
                                        <Clock3 size={14} /> Conflito: {c}
                                    </p>
                                ))}
                                {!p.conflicts.length && <small className="control-note">Nenhum conflito nos horários registrados.</small>}
                                <div className="control-schedule">
                                    {p.tasks.map((t) => (
                                        <Link key={t.id} href={t.href}>
                                            <CalendarDays size={15} />
                                            <span>
                                                <strong>{t.title}</strong>
                                                <small>
                                                    {localDate(t.start)} ·{' '}
                                                    {new Date(t.start).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}–
                                                    {new Date(t.end).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}
                                                </small>
                                            </span>
                                        </Link>
                                    ))}
                                </div>
                            </article>
                        ))}
                    </div>
                </section>
            )}
            {tab === 'rules' && (
                <section className="control-panel">
                    <span className="rd-eyebrow">ROTINAS COM CONTROLE</span>
                    <h2>Pequenas regras. Menos esquecimentos.</h2>
                    <p>
                        As regras criam tarefas internas. Ative após conferir a prévia. A execução agendada depende do agendador do
                        servidor; use “Executar agora” para uma verificação imediata.
                    </p>
                    {rules.map((rule) => (
                        <article className="control-rule" key={rule.key}>
                            <Workflow size={24} />
                            <div>
                                <h3>{rule.title}</h3>
                                <p>
                                    {rule.key === 'overdue'
                                        ? 'Uma tarefa de acompanhamento por pendência vencida.'
                                        : 'Uma tarefa para revisar o checklist de produção quando houver contrato assinado.'}
                                </p>
                                <small>
                                    {rule.record?.enabled ? 'Ativa' : 'Pausada'} · Última execução:{' '}
                                    {rule.record?.last_run_at ? localDate(rule.record.last_run_at) : 'Ainda não executada'}
                                </small>
                            </div>
                            {auth.user.isAdmin ? (
                                <div className="control-actions">
                                    <button className="button" disabled={busy} onClick={() => load(rule.key)}>
                                        Ver prévia
                                    </button>
                                    {!!rule.record?.enabled && (
                                        <button className="button" onClick={() => router.post('/operations/rules/' + rule.key + '/run')}>
                                            <Play size={14} /> Executar agora
                                        </button>
                                    )}
                                </div>
                            ) : (
                                <span className="control-status">Gerenciada pelo administrador</span>
                            )}
                        </article>
                    ))}
                    {preview && (
                        <div className="control-preview" role="region" aria-label="Prévia da regra">
                            <h3>{preview.title}</h3>
                            <p>{preview.rows.length} novas tarefas seriam criadas com os dados atuais.</p>
                            {preview.rows.map((row) => (
                                <div key={row.source}>
                                    <strong>{row.title}</strong>
                                    <p>
                                        {row.project} · {row.reason}
                                    </p>
                                </div>
                            ))}
                            <div className="control-actions">
                                <button
                                    className="button primary"
                                    onClick={() =>
                                        router.post(
                                            '/operations/rules/' + preview.key + '/enable',
                                            { enabled: true, fingerprint: preview.fingerprint },
                                            { onSuccess: () => setPreview(null) },
                                        )
                                    }
                                >
                                    <Check size={15} /> Confirmar ativação
                                </button>
                                <button
                                    className="button"
                                    onClick={() =>
                                        router.post(
                                            '/operations/rules/' + preview.key + '/enable',
                                            { enabled: false, fingerprint: preview.fingerprint },
                                            { onSuccess: () => setPreview(null) },
                                        )
                                    }
                                >
                                    <Pause size={15} /> Pausar regra
                                </button>
                                <button className="button" onClick={() => setPreview(null)}>
                                    Fechar prévia
                                </button>
                            </div>
                        </div>
                    )}
                    <h3>Histórico recente</h3>
                    {!runs.length && <p>A execução de regras aparecerá aqui.</p>}
                    {runs.map((run) => (
                        <div className="control-history-row" key={run.id}>
                            <div>
                                <strong>{run.title}</strong>
                                <small>
                                    {localDate(run.created_at)} · {run.undone_at ? 'Desfeita' : 'Criada pela regra'}
                                </small>
                            </div>
                            {auth.user.isAdmin && !run.undone_at && (
                                <button className="button" onClick={() => router.post('/operations/runs/' + run.id + '/undo')}>
                                    Desfazer
                                </button>
                            )}
                        </div>
                    ))}
                </section>
            )}
        </AppLayout>
    );
}
