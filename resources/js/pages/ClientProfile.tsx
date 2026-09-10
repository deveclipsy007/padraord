import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowRight, BriefcaseBusiness, Building2, Mail, Pencil, Phone, Plus, UserRound, UsersRound } from 'lucide-react';
import { useState } from 'react';
import { Drawer, Field, FormErrors } from '../components/FormControls';
import { PageHeader } from '../components/ui/PageHeader';
import { StatusBadge } from '../components/ui/StatusBadge';
import { Surface } from '../components/ui/Surface';
import { AppLayout } from '../layout';

type Contact = { id: number; name: string; email: string | null; phone: string | null; role: string | null };
type Client = {
    id: number;
    name: string;
    industry: string | null;
    notes: string | null;
    contacts: Contact[];
    opportunities: { id: number; title: string; stage: string }[];
};

function ContactForm({ clientId, contact, onSaved }: { clientId: number; contact?: Contact; onSaved: () => void }) {
    const form = useForm({
        name: contact?.name || '',
        email: contact?.email || '',
        phone: contact?.phone || '',
        role: contact?.role || '',
    });
    const submit = () =>
        contact
            ? form.patch(`/clients/${clientId}/contacts/${contact.id}`, { preserveScroll: true, onSuccess: onSaved })
            : form.post(`/clients/${clientId}/contacts`, {
                  preserveScroll: true,
                  onSuccess: () => {
                      form.reset();
                      onSaved();
                  },
              });
    return (
        <form
            className="form-grid"
            onSubmit={(event) => {
                event.preventDefault();
                submit();
            }}
        >
            <Field label="Nome" error={form.errors.name}>
                <input required value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} />
            </Field>
            <Field label="E-mail" error={form.errors.email}>
                <input type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} />
            </Field>
            <Field label="Telefone" error={form.errors.phone}>
                <input value={form.data.phone} onChange={(event) => form.setData('phone', event.target.value)} />
            </Field>
            <Field label="Função" error={form.errors.role}>
                <input value={form.data.role} onChange={(event) => form.setData('role', event.target.value)} />
            </Field>
            <FormErrors errors={form.errors} />
            <button className="button button-primary" disabled={form.processing}>
                {contact ? 'Salvar contato' : 'Adicionar contato'}
            </button>
        </form>
    );
}

export default function ClientProfile({ client }: { client: Client }) {
    const [editingClient, setEditingClient] = useState(false);
    const [editingContact, setEditingContact] = useState<Contact | null | undefined>(undefined);
    const [creatingOpportunity, setCreatingOpportunity] = useState(false);
    const form = useForm({ name: client.name, industry: client.industry || '', notes: client.notes || '' });
    const opportunity = useForm({ title: '', client_id: client.id, contact_id: '' });

    return (
        <AppLayout>
            <Head title={client.name} />
            <PageHeader
                eyebrow="Cliente"
                title={client.name}
                breadcrumbs={<Link href="/clients">Clientes /</Link>}
                description={client.industry || 'Segmento ainda não informado'}
                secondaryActions={
                    <button className="button button-subtle" type="button" onClick={() => setEditingClient(true)}>
                        <Pencil size={15} /> Editar perfil
                    </button>
                }
                primaryAction={
                    <button className="button button-primary" type="button" onClick={() => setCreatingOpportunity(true)}>
                        <Plus size={16} /> Nova oportunidade
                    </button>
                }
            />

            <section className="client-profile-metrics">
                <Surface>
                    <UsersRound size={17} />
                    <span>
                        <strong>{client.contacts.length}</strong>
                        <small>{client.contacts.length === 1 ? 'contato' : 'contatos'}</small>
                    </span>
                </Surface>
                <Surface>
                    <BriefcaseBusiness size={17} />
                    <span>
                        <strong>{client.opportunities.length}</strong>
                        <small>{client.opportunities.length === 1 ? 'caso relacionado' : 'casos relacionados'}</small>
                    </span>
                </Surface>
                <Surface>
                    <Building2 size={17} />
                    <span>
                        <strong>{client.industry || 'A definir'}</strong>
                        <small>segmento</small>
                    </span>
                </Surface>
            </section>

            <section className="client-profile-grid">
                <div className="client-profile-main">
                    <Surface className="profile-section">
                        <div className="profile-section__heading">
                            <div>
                                <span className="eyebrow">PESSOAS</span>
                                <h2>Contatos</h2>
                                <p>Quem participa das conversas e decisões deste cliente.</p>
                            </div>
                            <button className="button button-subtle" type="button" onClick={() => setEditingContact(null)}>
                                <Plus size={15} /> Novo contato
                            </button>
                        </div>
                        <div className="contact-list">
                            {client.contacts.map((contact) => (
                                <button className="contact-card" type="button" key={contact.id} onClick={() => setEditingContact(contact)}>
                                    <span className="contact-card__avatar">
                                        <UserRound size={17} />
                                    </span>
                                    <span>
                                        <strong>{contact.name}</strong>
                                        <small>{contact.role || 'Função não informada'}</small>
                                    </span>
                                    <span className="contact-card__channels">
                                        {contact.email && <Mail size={15} />}
                                        {contact.phone && <Phone size={15} />}
                                    </span>
                                    <Pencil size={14} />
                                </button>
                            ))}
                            {!client.contacts.length && (
                                <div className="inline-empty">
                                    Nenhum contato cadastrado. Adicione a primeira pessoa deste relacionamento.
                                </div>
                            )}
                        </div>
                    </Surface>

                    <Surface className="profile-section">
                        <div className="profile-section__heading">
                            <div>
                                <span className="eyebrow">OPERAÇÃO</span>
                                <h2>Casos relacionados</h2>
                                <p>O histórico comercial e operacional conectado a este cliente.</p>
                            </div>
                        </div>
                        <div className="related-case-list">
                            {client.opportunities.map((item) => (
                                <Link href={`/opportunities/${item.id}`} key={item.id} viewTransition>
                                    <span>
                                        <strong>{item.title}</strong>
                                        <small>Abra o workspace para continuar o próximo movimento.</small>
                                    </span>
                                    <StatusBadge tone="neutral">{item.stage}</StatusBadge>
                                    <ArrowRight size={16} />
                                </Link>
                            ))}
                            {!client.opportunities.length && <div className="inline-empty">Ainda não há oportunidades relacionadas.</div>}
                        </div>
                    </Surface>
                </div>

                <Surface className="client-context-card" tone="elevated">
                    <span className="eyebrow">MEMÓRIA DO CLIENTE</span>
                    <h2>Contexto compartilhado</h2>
                    <p>{client.notes || 'Registre informações importantes para que a equipe não dependa de conversas paralelas.'}</p>
                    <button className="button button-subtle" type="button" onClick={() => setEditingClient(true)}>
                        <Pencil size={15} /> Atualizar contexto
                    </button>
                </Surface>
            </section>

            <Drawer title="Editar cliente" open={editingClient} onClose={() => setEditingClient(false)}>
                <form
                    className="form-grid"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.patch(`/clients/${client.id}`, { preserveScroll: true, onSuccess: () => setEditingClient(false) });
                    }}
                >
                    <Field label="Nome" error={form.errors.name}>
                        <input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} />
                    </Field>
                    <Field label="Segmento" error={form.errors.industry}>
                        <input value={form.data.industry} onChange={(event) => form.setData('industry', event.target.value)} />
                    </Field>
                    <Field label="Contexto" error={form.errors.notes}>
                        <textarea rows={7} value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} />
                    </Field>
                    <FormErrors errors={form.errors} />
                    <button className="button button-primary" disabled={form.processing}>
                        Salvar cliente
                    </button>
                </form>
            </Drawer>

            <Drawer
                title={editingContact ? 'Editar contato' : 'Novo contato'}
                open={editingContact !== undefined}
                onClose={() => setEditingContact(undefined)}
            >
                {editingContact !== undefined && (
                    <ContactForm clientId={client.id} contact={editingContact || undefined} onSaved={() => setEditingContact(undefined)} />
                )}
            </Drawer>

            <Drawer title="Nova oportunidade" open={creatingOpportunity} onClose={() => setCreatingOpportunity(false)}>
                <p className="drawer-intro">
                    O cliente já está conectado. Informe apenas o nome do trabalho e, se possível, o contato principal.
                </p>
                <form
                    className="form-grid"
                    onSubmit={(event) => {
                        event.preventDefault();
                        opportunity.post('/opportunities', { onSuccess: () => setCreatingOpportunity(false) });
                    }}
                >
                    <Field label="Nome do trabalho" error={opportunity.errors.title}>
                        <input
                            required
                            value={opportunity.data.title}
                            onChange={(event) => opportunity.setData('title', event.target.value)}
                        />
                    </Field>
                    <Field label="Contato principal" error={opportunity.errors.contact_id}>
                        <select
                            value={opportunity.data.contact_id}
                            onChange={(event) => opportunity.setData('contact_id', event.target.value)}
                        >
                            <option value="">Definir depois</option>
                            {client.contacts.map((contact) => (
                                <option key={contact.id} value={contact.id}>
                                    {contact.name}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <FormErrors errors={opportunity.errors} />
                    <button className="button button-primary" disabled={opportunity.processing}>
                        Criar oportunidade
                    </button>
                </form>
            </Drawer>
        </AppLayout>
    );
}
