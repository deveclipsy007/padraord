import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowUpRight, Building2, Plus, Search, UsersRound } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Drawer, Field, FormErrors } from '../components/FormControls';
import { PageHeader } from '../components/ui/PageHeader';
import { Surface } from '../components/ui/Surface';
import { AppLayout } from '../layout';

type ClientSummary = { id: number; name: string; industry?: string | null; opportunitiesCount: number };
type Props = { clients: ClientSummary[] };

const initials = (name: string) => name.split(/\s+/).slice(0, 2).map((part) => part[0]).join('').toUpperCase();

export default function Clients({ clients }: Props) {
    const [query, setQuery] = useState('');
    const [creating, setCreating] = useState(false);
    const form = useForm({ name: '', industry: '', notes: '' });
    const filtered = useMemo(() => clients.filter((client) => `${client.name} ${client.industry ?? ''}`.toLowerCase().includes(query.trim().toLowerCase())), [clients, query]);

    const submit = () => form.post('/clients', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            setCreating(false);
        },
    });

    return <AppLayout>
        <Head title="Clientes" />
        <PageHeader eyebrow="Relacionamento" title="Clientes" description="Contexto, contatos e oportunidades em uma base única para a equipe." primaryAction={<button className="button button-primary" type="button" onClick={() => setCreating(true)}><Plus size={17} /> Novo cliente</button>} />

        <Surface className="client-directory" padding="none">
            <div className="client-directory__toolbar">
                <div className="client-directory__title"><span className="client-directory__icon"><UsersRound size={18} /></span><div><strong>Base de clientes</strong><small>{clients.length} {clients.length === 1 ? 'relacionamento ativo' : 'relacionamentos ativos'}</small></div></div>
                <label className="directory-search"><Search size={17} /><span className="sr-only">Buscar clientes</span><input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Buscar por nome ou segmento" /></label>
            </div>

            <div className="client-directory__list">
                {filtered.map((client) => <Link className="client-directory__row" key={client.id} href={`/clients/${client.id}`} viewTransition>
                    <span className="client-directory__avatar">{initials(client.name)}</span>
                    <span className="client-directory__identity"><strong>{client.name}</strong><small><Building2 size={13} /> {client.industry || 'Segmento ainda não informado'}</small></span>
                    <span className="client-directory__cases"><strong>{client.opportunitiesCount}</strong><small>{client.opportunitiesCount === 1 ? 'caso' : 'casos'}</small></span>
                    <span className="client-directory__open"><ArrowUpRight size={17} /></span>
                </Link>)}
                {!filtered.length && <div className="directory-empty"><Search size={21} /><strong>{query ? 'Nenhum cliente encontrado' : 'Sua base começa aqui'}</strong><p>{query ? 'Revise a busca ou procure por outro segmento.' : 'Cadastre o primeiro cliente para conectar contatos e oportunidades.'}</p>{!query && <button className="button button-primary" type="button" onClick={() => setCreating(true)}><Plus size={16} /> Cadastrar cliente</button>}</div>}
            </div>
        </Surface>

        <Drawer title="Novo cliente" open={creating} onClose={() => setCreating(false)}>
            <p className="drawer-intro">Comece apenas com o essencial. O contexto pode ser ampliado depois, manualmente ou pelo assistente.</p>
            <form className="form-grid" onSubmit={(event) => { event.preventDefault(); submit(); }}>
                <Field label="Nome" error={form.errors.name}><input autoFocus required value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} placeholder="Nome da empresa ou cliente" /></Field>
                <Field label="Segmento" error={form.errors.industry}><input value={form.data.industry} onChange={(event) => form.setData('industry', event.target.value)} placeholder="Ex.: tecnologia, varejo, educação" /></Field>
                <Field label="Contexto inicial" error={form.errors.notes}><textarea rows={5} value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} placeholder="O que a equipe já precisa saber sobre este relacionamento?" /></Field>
                <FormErrors errors={form.errors} />
                <button className="button button-primary" disabled={form.processing}>{form.processing ? 'Criando…' : 'Criar cliente'}</button>
            </form>
        </Drawer>
    </AppLayout>;
}
