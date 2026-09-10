import { Head, Link, useForm } from '@inertiajs/react';
import { AppLayout } from '../layout';
import { GlassSurface } from '../components/GlassSurface';
import { FormErrors } from '../components/FormErrors';
type Props = {
    opportunity: {
        id: number;
        title: string;
        client_id: number | null;
        client_name: string;
        contact_id: number | null;
        contact_name: string | null;
        contact_email: string | null;
        owner_id: number | null;
        event_date: string | null;
        location: string | null;
        objective: string | null;
        next_action: string | null;
    };
    clients: { id: number; name: string; contacts: { id: number; name: string }[] }[];
    users: { id: number; name: string }[];
};
export default function OpportunityEdit({ opportunity: o, clients, users }: Props) {
    const form = useForm({
        title: o.title,
        client_id: o.client_id?.toString() || '',
        client_name: o.client_name,
        contact_id: o.contact_id?.toString() || '',
        contact_name: o.contact_name || '',
        contact_email: o.contact_email || '',
        owner_id: o.owner_id?.toString() || '',
        event_date: o.event_date?.slice(0, 10) || '',
        location: o.location || '',
        objective: o.objective || '',
        next_action: o.next_action || '',
    });
    const contacts = clients.find((c) => String(c.id) === form.data.client_id)?.contacts || [];
    return (
        <AppLayout>
            <Head title="Editar caso" />
            <header className="topbar">
                <div>
                    <Link href={`/opportunities/${o.id}`}>← Voltar ao caso</Link>
                    <h1>Dados do trabalho.</h1>
                </div>
            </header>
            <GlassSurface>
                <form
                    className="form-grid"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.patch(`/opportunities/${o.id}`);
                    }}
                >
                    <label>
                        Título
                        <input required value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                    </label>
                    <label>
                        Cliente
                        <select
                            value={form.data.client_id}
                            onChange={(e) =>
                                form.setData({
                                    ...form.data,
                                    client_id: e.target.value,
                                    contact_id: '',
                                    contact_name: '',
                                    contact_email: '',
                                })
                            }
                        >
                            <option value="">{o.client_name} (registro anterior)</option>
                            {clients.map((c) => (
                                <option value={c.id} key={c.id}>
                                    {c.name}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label>
                        Contato
                        <select
                            value={form.data.contact_id}
                            onChange={(e) =>
                                form.setData({ ...form.data, contact_id: e.target.value, contact_name: '', contact_email: '' })
                            }
                        >
                            <option value="">Definir depois</option>
                            {contacts.map((c) => (
                                <option value={c.id} key={c.id}>
                                    {c.name}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label>
                        Responsável
                        <select value={form.data.owner_id} onChange={(e) => form.setData('owner_id', e.target.value)}>
                            <option value="">Usuário atual</option>
                            {users.map((u) => (
                                <option value={u.id} key={u.id}>
                                    {u.name}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label>
                        Data
                        <input type="date" value={form.data.event_date} onChange={(e) => form.setData('event_date', e.target.value)} />
                    </label>
                    {(['location', 'objective', 'next_action'] as const).map((key, i) => (
                        <label key={key}>
                            {['Local', 'Objetivo', 'Próxima ação'][i]}
                            <input value={form.data[key]} onChange={(e) => form.setData(key, e.target.value)} />
                        </label>
                    ))}
                    <FormErrors errors={form.errors} />
                    <button className="button button-primary" disabled={form.processing}>
                        Salvar alterações
                    </button>
                </form>
            </GlassSurface>
        </AppLayout>
    );
}
