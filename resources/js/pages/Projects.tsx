import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { AppLayout } from '../layout';
type Props = {
    cases: {
        id: number;
        title: string;
        owner: string;
        revision: number;
        viability: string;
        management: string;
        outcome: string | null;
        next: string;
    }[];
    opportunities: { id: number; title: string; client: string; owner: string | null; stage: string; next: string | null }[];
};
export default function Projects({ cases, opportunities }: Props) {
    const [creating, setCreating] = useState(false);
    const [query, setQuery] = useState('');
    const visible = opportunities.filter((item) =>
        [item.title, item.client, item.owner ?? '', item.stage]
            .join(' ')
            .toLocaleLowerCase('pt-BR')
            .includes(query.toLocaleLowerCase('pt-BR')),
    );
    return (
        <AppLayout>
            <Head title="Projetos" />
            <header className="topbar projects-header">
                <div>
                    <span className="eyebrow">VISÃO DA OPERAÇÃO</span>
                    <h1>Projetos</h1>
                    <p>Contexto, responsáveis e próximas decisões em um só lugar.</p>
                </div>
                <Link className="button button-primary" href="/pipeline">
                    Abrir Kanban →
                </Link>
            </header>
            <div className="projects-toolbar">
                <div>
                    <strong>{opportunities.length}</strong>
                    <span>casos da equipe</span>
                </div>
                <label className="projects-search">
                    <span className="sr-only">Buscar projeto</span>
                    <input
                        aria-label="Buscar projeto"
                        placeholder="Buscar projeto, cliente ou responsável"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                    />
                </label>
            </div>
            <section className="project-portfolio" aria-label="Casos existentes da equipe">
                {visible.map((record) => (
                    <Link className="project-portfolio__card" key={record.id} href={'/opportunities/' + record.id}>
                        <div className="project-portfolio__top">
                            <span>{record.client}</span>
                            <span aria-hidden="true">↗</span>
                        </div>
                        <h2>{record.title}</h2>
                        <span className="project-portfolio__stage">{record.stage}</span>
                        <div className="project-portfolio__next">
                            <span>PRÓXIMA DECISÃO</span>
                            <p>{record.next || 'Definir a próxima ação'}</p>
                        </div>
                        <footer>
                            <span>{record.owner || 'Responsável a definir'}</span>
                            <strong>Abrir projeto →</strong>
                        </footer>
                    </Link>
                ))}
                {!visible.length && (
                    <div className="directory-empty">
                        <strong>{query ? 'Nenhum projeto encontrado' : 'Sua operação começa aqui'}</strong>
                        <p>
                            {query ? 'Tente outro título, cliente ou responsável.' : 'Cadastre uma oportunidade no Comercial para começar.'}
                        </p>
                    </div>
                )}
            </section>
            <details className="project-laboratory">
                <summary>Laboratório de demonstração</summary>
                <p>Experimente a jornada completa com dados fictícios, separados dos casos da equipe.</p>
                <button
                    className="button button-subtle"
                    disabled={creating}
                    onClick={() => router.post('/prototype', {}, { onStart: () => setCreating(true), onFinish: () => setCreating(false) })}
                >
                    {creating ? 'Preparando…' : '+ Experimentar Conferência Horizonte'}
                </button>
                <section className="prototype-card-grid">
                    {cases.map((record) => (
                        <Link className="prototype-project" key={record.id} href={'/prototype/' + record.id + '/overview'}>
                            <span className="eyebrow">
                                DEMO-{record.id} · v{record.revision}
                            </span>
                            <h3>{record.title}</h3>
                            <p>{record.owner}</p>
                            <p>{record.next}</p>
                            <strong>Continuar teste →</strong>
                        </Link>
                    ))}
                </section>
            </details>
        </AppLayout>
    );
}
