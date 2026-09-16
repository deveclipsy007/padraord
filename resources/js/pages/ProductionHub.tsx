import { Head, Link } from '@inertiajs/react';
import { CalendarDays, Clapperboard, ArrowUpRight, CircleAlert, CheckCheck, Search } from 'lucide-react';
import { useState } from 'react';
import { AppLayout } from '../layout';
import { PageHeader } from '../components/ui/PageHeader';

type Event = {
    id: number;
    title: string;
    client: string;
    date: string | null;
    owner: string | null;
    stage: string;
    tasks: number;
    completed: number;
    blocked: number;
};
const inOperation = (event: Event) =>
    !['closed', 'lost', 'cancelled'].includes(event.stage) &&
    (['pre_production', 'production', 'post_event'].includes(event.stage) || event.tasks > 0);
export default function ProductionHub({ events }: { events: Event[] }) {
    const [query, setQuery] = useState('');
    const [filter, setFilter] = useState('operation');
    const visible = events.filter(
        (event) =>
            (filter === 'all' ||
                (filter === 'blocked'
                    ? event.blocked > 0
                    : filter === 'post'
                      ? ['post_event', 'closed'].includes(event.stage)
                      : inOperation(event))) &&
            `${event.title} ${event.client} ${event.owner ?? ''}`.toLocaleLowerCase('pt-BR').includes(query.toLocaleLowerCase('pt-BR')),
    );
    return (
        <AppLayout>
            <Head title="Central de Produção" />
            <PageHeader
                eyebrow="EXECUÇÃO / PADRÃO RD"
                title="Central de Produção"
                description="Um espaço para preparar, executar e encerrar cada evento."
            />
            <section className="production-command" aria-label="Resumo da produção">
                <div>
                    <Clapperboard size={32} strokeWidth={1.3} />
                    <h2>Do plano ao acontecimento.</h2>
                    <p>O comercial define o combinado. Aqui, a equipe acompanha a entrega.</p>
                </div>
                <dl>
                    <div>
                        <dt>Eventos em operação</dt>
                        <dd>{events.filter(inOperation).length}</dd>
                    </div>
                    <div>
                        <dt>Tarefas bloqueadas</dt>
                        <dd>{events.reduce((sum, e) => sum + e.blocked, 0)}</dd>
                    </div>
                    <div>
                        <dt>Tarefas concluídas</dt>
                        <dd>{events.reduce((sum, e) => sum + e.completed, 0)}</dd>
                    </div>
                </dl>
            </section>
            <div className="production-tools">
                <div className="directory-view-toggle" aria-label="Filtrar eventos">
                    {[
                        ['operation', 'Em operação'],
                        ['blocked', 'Com bloqueios'],
                        ['post', 'Pós-evento'],
                        ['all', 'Todos os projetos'],
                    ].map(([value, label]) => (
                        <button key={value} type="button" aria-pressed={filter === value} onClick={() => setFilter(value)}>
                            {label}
                        </button>
                    ))}
                </div>
                <label className="production-search">
                    <Search size={16} />
                    <span className="sr-only">Buscar evento</span>
                    <input
                        aria-label="Buscar evento"
                        placeholder="Evento, cliente ou responsável"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                    />
                </label>
            </div>
            <section className="production-event-grid" aria-label="Eventos de produção">
                {visible.map((event) => (
                    <article className="production-event" key={event.id}>
                        <header>
                            <span className="eyebrow">{event.client}</span>
                            <span className="status-pill gray">
                                {['lost', 'cancelled'].includes(event.stage)
                                    ? 'Encerrado sem execução'
                                    : ['post_event', 'closed'].includes(event.stage)
                                      ? 'Encerramento'
                                      : inOperation(event)
                                        ? 'Em operação'
                                        : 'Em planejamento'}
                            </span>
                        </header>
                        <h2>
                            <Link href={`/production/events/${event.id}`}>{event.title}</Link>
                        </h2>
                        <p>
                            <CalendarDays size={14} />
                            {event.date || 'Data a definir'} · {event.owner || 'Responsável a definir'}
                        </p>
                        <div className="production-task-progress">
                            <span>
                                <CheckCheck size={16} />
                                {event.completed} de {event.tasks} tarefas concluídas
                            </span>
                            <progress aria-label={`Tarefas concluídas de ${event.title}`} value={event.completed} max={event.tasks || 1} />
                        </div>
                        {event.blocked > 0 && (
                            <p className="production-blocked">
                                <CircleAlert size={15} />
                                {event.blocked} {event.blocked === 1 ? 'bloqueio para resolver' : 'bloqueios para resolver'}
                            </p>
                        )}
                        <footer>
                            <Link className="button button-primary" href={`/production/events/${event.id}`}>
                                Abrir produção <ArrowUpRight size={15} />
                            </Link>
                            <Link className="button button-subtle" href={`/production/events/${event.id}/post-event`}>
                                Pós-evento
                            </Link>
                        </footer>
                    </article>
                ))}
            </section>
            {!visible.length && (
                <div className="directory-empty">
                    <Clapperboard />
                    <strong>Nenhum evento neste recorte</strong>
                    <p>Veja todos os projetos para preparar uma nova operação ou ajuste a busca.</p>
                    <button
                        className="button button-subtle"
                        onClick={() => {
                            setFilter('all');
                            setQuery('');
                        }}
                    >
                        Ver todos os projetos
                    </button>
                </div>
            )}
        </AppLayout>
    );
}
