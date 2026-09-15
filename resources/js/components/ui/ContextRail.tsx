import { Link } from '@inertiajs/react';
import { AlertTriangle, ArrowUpRight, CheckCircle2 } from 'lucide-react';

export type ContextRailItem = { label: string; href: string; active?: boolean; status?: string; pending?: number };

export function ContextRail({
    title,
    status,
    owner,
    progress,
    nextStep,
    items,
}: {
    title: string;
    status?: string;
    owner?: string;
    progress?: number;
    nextStep?: string;
    items: ContextRailItem[];
}) {
    return (
        <aside className="context-rail" aria-label={`Contexto de ${title}`}>
            <div className="context-rail__identity">
                <span className="context-rail__eyebrow">Contexto atual</span>
                <h2>{title}</h2>
                {status && (
                    <span className="context-rail__status">
                        <i />
                        {status}
                    </span>
                )}
            </div>
            {(owner || typeof progress === 'number') && (
                <div className="context-rail__summary">
                    {owner && (
                        <div>
                            <small>Responsável</small>
                            <strong>{owner}</strong>
                        </div>
                    )}
                    {typeof progress === 'number' && (
                        <div>
                            <small>Prontidão</small>
                            <strong>{progress}%</strong>
                            <span className="context-progress">
                                <i style={{ width: `${Math.min(100, Math.max(0, progress))}%` }} />
                            </span>
                        </div>
                    )}
                </div>
            )}
            <nav className="context-rail__nav" aria-label="Módulos do contexto">
                {items.map((item) => (
                    <Link href={item.href} key={item.href} className={item.active ? 'is-active' : ''} viewTransition>
                        <span>
                            <i className={`context-module-dot context-module-dot--${item.status || 'unknown'}`} />
                            {item.label}
                        </span>
                        {item.pending ? <b>{item.pending}</b> : <ArrowUpRight size={13} />}
                    </Link>
                ))}
            </nav>
            {nextStep && (
                <section className="context-rail__next" role="region" aria-label="Próxima decisão do caso">
                    <span>
                        <CheckCircle2 size={14} /> Próxima decisão
                    </span>
                    <p>{nextStep}</p>
                </section>
            )}
            {!nextStep && (
                <section className="context-rail__next context-rail__next--muted" role="region" aria-label="Próxima decisão do caso">
                    <span>
                        <AlertTriangle size={14} /> Próxima decisão
                    </span>
                    <p>Definir a próxima ação do contexto.</p>
                </section>
            )}
        </aside>
    );
}
