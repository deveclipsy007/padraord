import { Head, Link, router, useForm } from '@inertiajs/react';
import { Archive, ArrowUpRight, Building2, ChevronLeft, ChevronRight, Plus, Search, UsersRound, X } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { Drawer, Field, FormErrors } from '../components/FormControls';
import { PageHeader } from '../components/ui/PageHeader';
import { Surface } from '../components/ui/Surface';
import { AppLayout } from '../layout';

type ClientSummary = { id: number; name: string; industry?: string | null; opportunitiesCount: number; archived?: boolean };
type PaginatorLink = { url?: string | null; label?: string; active?: boolean };
type Paginator = { data: ClientSummary[]; links?: { prev?: string | null; next?: string | null } | PaginatorLink[]; prev_page_url?: string | null; next_page_url?: string | null; meta?: { current_page?: number; last_page?: number; total?: number } };
type Props = { clients: Paginator | ClientSummary[]; filters?: { q?: string; status?: 'active' | 'archived' | 'all' } };
const initials = (name: string) => name.split(/\s+/).slice(0, 2).map((part) => part[0]).join('').toUpperCase();

export default function Clients({ clients: payload, filters = {} }: Props) {
    const clients = Array.isArray(payload) ? payload : payload.data;
    const [query, setQuery] = useState(filters.q ?? '');
    const [status, setStatus] = useState(filters.status ?? 'active');
    const [creating, setCreating] = useState(false);
    const [archiveTarget, setArchiveTarget] = useState<ClientSummary | null>(null);
    const [archiveReason, setArchiveReason] = useState('');
    const form = useForm({ name: '', industry: '', notes: '' });
    const paginator = Array.isArray(payload) ? null : payload;

    const submitSearch = (event: FormEvent) => { event.preventDefault(); router.get('/clients', { q: query, status }, { preserveState: true, replace: true }); };
    const submit = () => form.post('/clients', { preserveScroll: true, onSuccess: () => { form.reset(); setCreating(false); } });
    const archive = () => { if (!archiveTarget || !archiveReason.trim()) return; router.post(`/clients/${archiveTarget.id}/archive`, { reason: archiveReason.trim() }, { preserveScroll: true, onSuccess: () => { setArchiveTarget(null); setArchiveReason(''); } }); };
    const pageUrl = (direction: 'prev' | 'next') => {
        if (!paginator) return null;
        if (Array.isArray(paginator.links)) {
            const match = paginator.links.find((link) => link.label?.toLowerCase().includes(direction === 'prev' ? 'previous' : 'next'));
            return match?.url ?? null;
        }
        return direction === 'prev' ? (paginator.links?.prev ?? paginator.prev_page_url ?? null) : (paginator.links?.next ?? paginator.next_page_url ?? null);
    };
    const go = (url?: string | null) => { if (url) router.visit(url, { preserveState: true, preserveScroll: true }); };

    return <AppLayout>
        <Head title="Clientes" />
        <PageHeader eyebrow="Relacionamento" title="Clientes" description="Contexto, contatos e oportunidades em uma base única para a equipe." primaryAction={<button className="button button-primary" type="button" onClick={() => setCreating(true)}><Plus size={17} /> Novo cliente</button>} />
        <Surface className="client-directory" padding="none">
            <div className="client-directory__toolbar"><div className="client-directory__title"><span className="client-directory__icon"><UsersRound size={18} /></span><div><strong>Base de clientes</strong><small>{paginator?.meta?.total ?? clients.length} relacionamento(s) {status === 'archived' ? 'arquivado(s)' : 'encontrado(s)'}</small></div></div><form className="directory-search" onSubmit={submitSearch}><Search size={17} /><span className="sr-only">Buscar clientes</span><input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Buscar por nome ou segmento" /><button type="submit" className="button button-subtle">Buscar</button></form></div>
            <div className="client-directory__filters"><label><span>Situação</span><select value={status} onChange={(event) => { const value = event.target.value as 'active' | 'archived' | 'all'; setStatus(value); router.get('/clients', { q: query, status: value }, { preserveState: true, replace: true }); }}><option value="active">Ativos</option><option value="archived">Arquivados</option><option value="all">Todos</option></select></label><span className="filter-hint">Cadastros arquivados permanecem disponíveis no histórico.</span></div>
            <div className="client-directory__list">{clients.map((client) => <div className={`client-directory__row${client.archived ? ' is-archived' : ''}`} key={client.id}><Link className="client-directory__row-link" href={`/clients/${client.id}`} viewTransition><span className="client-directory__avatar">{initials(client.name)}</span><span className="client-directory__identity"><strong>{client.name}</strong><small><Building2 size={13} /> {client.industry || 'Segmento ainda não informado'}</small></span><span className="client-directory__cases"><strong>{client.opportunitiesCount}</strong><small>{client.opportunitiesCount === 1 ? 'caso' : 'casos'}</small></span></Link><span className="client-directory__actions">{client.archived ? <button className="icon-button" type="button" onClick={() => router.post(`/clients/${client.id}/restore`, {}, { preserveScroll: true })} aria-label={`Restaurar ${client.name}`}><Archive size={16} /></button> : <button className="icon-button" type="button" onClick={() => setArchiveTarget(client)} aria-label={`Arquivar ${client.name}`}><Archive size={16} /></button>}<Link className="client-directory__open" href={`/clients/${client.id}`} aria-label={`Abrir ${client.name}`}><ArrowUpRight size={17} /></Link></span></div>)}{!clients.length && <div className="directory-empty"><Search size={21} /><strong>{query ? 'Nenhum cliente encontrado' : status === 'archived' ? 'Nenhum cliente arquivado' : 'Sua base começa aqui'}</strong><p>{query ? 'Revise a busca ou procure por outro segmento.' : 'Cadastre o primeiro cliente para conectar contatos e oportunidades.'}</p>{!query && status !== 'archived' && <button className="button button-primary" type="button" onClick={() => setCreating(true)}><Plus size={16} /> Cadastrar cliente</button>}</div>}</div>
            {paginator && <div className="pagination-row"><span>Página {paginator.meta?.current_page ?? 1} de {paginator.meta?.last_page ?? 1}</span><div><button className="icon-button" type="button" onClick={() => go(pageUrl('prev'))} disabled={!pageUrl('prev')} aria-label="Página anterior"><ChevronLeft size={16} /></button><button className="icon-button" type="button" onClick={() => go(pageUrl('next'))} disabled={!pageUrl('next')} aria-label="Próxima página"><ChevronRight size={16} /></button></div></div>}
        </Surface>
        <Drawer title="Novo cliente" open={creating} onClose={() => setCreating(false)}><p className="drawer-intro">Comece apenas com o essencial. O contexto pode ser ampliado depois, manualmente ou pelo assistente.</p><form className="form-grid" onSubmit={(event) => { event.preventDefault(); submit(); }}><Field label="Nome" error={form.errors.name}><input autoFocus required value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} placeholder="Nome da empresa ou cliente" /></Field><Field label="Segmento" error={form.errors.industry}><input value={form.data.industry} onChange={(event) => form.setData('industry', event.target.value)} placeholder="Ex.: tecnologia, varejo, educação" /></Field><Field label="Contexto inicial" error={form.errors.notes}><textarea rows={5} value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} placeholder="O que a equipe já precisa saber sobre este relacionamento?" /></Field><FormErrors errors={form.errors} /><button className="button button-primary" disabled={form.processing}>{form.processing ? 'Criando…' : 'Criar cliente'}</button></form></Drawer>
        {archiveTarget && <div className="modal-backdrop" role="presentation"><div className="modal" role="dialog" aria-modal="true" aria-labelledby="archive-client-title"><div className="modal-heading"><div><span className="eyebrow">PRESERVAR HISTÓRICO</span><h2 id="archive-client-title">Arquivar cliente?</h2></div><button className="icon-button" type="button" onClick={() => setArchiveTarget(null)} aria-label="Fechar"><X size={17} /></button></div><p className="drawer-intro">{archiveTarget.name} continuará visível no histórico. Clientes com oportunidades ativas precisam ser encerrados antes do arquivamento.</p><label className="rd-field"><span>Justificativa obrigatória</span><textarea rows={4} value={archiveReason} onChange={(event) => setArchiveReason(event.target.value)} placeholder="Por que este cadastro não deve aparecer nos novos trabalhos?" /></label><div className="form-actions"><button className="button button-subtle" type="button" onClick={() => setArchiveTarget(null)}>Cancelar</button><button className="button button-primary" type="button" disabled={!archiveReason.trim()} onClick={archive}>Arquivar cliente</button></div></div></div>}
    </AppLayout>;
}
