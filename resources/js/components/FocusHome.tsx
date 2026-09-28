import { Link, router, usePage } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
import { localDate } from './control-utils';
export type FocusItem = { id: string; title: string; context: string; href: string; due: string | null; detail: string };
export function FocusHome({ items, onOverview, expanded }: { items: FocusItem[]; onOverview: () => void; expanded: boolean }) {
    const { auth } = usePage<{ auth: { user: { isAdmin: boolean; workspaceFocus: string } } }>().props;
    const focus = auth.user.workspaceFocus;
    const labels: Record<string, string> = {
        management: 'Uma visão de direção.',
        production: 'Seu próximo movimento na produção.',
        finance: 'Valores e conferências no radar.',
        commercial: 'As conversas que fazem avançar.',
    };
    return (
        <section className="focus-panel">
            <div className="focus-panel-heading">
                <div>
                    <span className="rd-eyebrow">SEU FOCO DE TRABALHO</span>
                    <h2>{labels[focus]}</h2>
                    <p>Escolha o que aparece primeiro para você. Essa preferência não altera permissões.</p>
                </div>
                <select
                    aria-label="Foco de trabalho"
                    value={focus}
                    onChange={(e) => router.post('/workspace/focus', { focus: e.target.value })}
                >
                    {auth.user.isAdmin && <option value="management">Direção</option>}
                    <option value="commercial">Comercial</option>
                    <option value="production">Produção</option>
                    <option value="finance">Financeiro</option>
                </select>
            </div>
            {['production', 'finance'].includes(focus) && (
                <>
                    <div className="control-pending-list">
                        {items.map((item) => (
                            <Link className="control-pending-row" key={item.id} href={item.href}>
                                <div>
                                    <strong>{item.title}</strong>
                                    <small>
                                        {item.context} · {item.detail}
                                    </small>
                                </div>
                                <span>{localDate(item.due)}</span>
                                <ArrowUpRight size={17} />
                            </Link>
                        ))}
                    </div>
                    {!items.length && (
                        <p>
                            {focus === 'production'
                                ? 'Você ainda não tem tarefas abertas atribuídas. Consulte a produção para distribuir o trabalho.'
                                : 'Nenhum lançamento aberto para exibir. Consulte o financeiro de um projeto.'}
                        </p>
                    )}
                </>
            )}
            <div className="focus-links">
                <Link href="/operations">
                    Controle operacional <ArrowUpRight size={13} />
                </Link>
                <Link href={focus === 'production' ? '/production' : focus === 'commercial' ? '/pipeline' : '/projects'}>
                    Abrir área de trabalho <ArrowUpRight size={13} />
                </Link>
                <Link href="/orbital">
                    Conheça o Orbital RD <ArrowUpRight size={13} />
                </Link>
                {['production', 'finance'].includes(focus) && (
                    <button className="button" onClick={onOverview}>
                        {expanded ? 'Recolher visão geral' : 'Ver visão geral'}
                    </button>
                )}
            </div>
        </section>
    );
}
