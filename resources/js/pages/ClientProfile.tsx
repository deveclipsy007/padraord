import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowRight, CircleAlert, Mail, Pencil, Phone, Plus, UserRound } from 'lucide-react';
import { useState } from 'react';
import { Drawer, Field, FormErrors } from '../components/FormControls';
import { PageHeader } from '../components/ui/PageHeader';
import { StatusBadge } from '../components/ui/StatusBadge';
import { Surface } from '../components/ui/Surface';
import { AppLayout } from '../layout';

type Contact = {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    role: string | null;
    department: string | null;
    whatsapp: string | null;
    preferred_channel: string;
    is_primary: boolean;
    is_decision_maker: boolean;
    revision: number;
    archived_at: string | null;
};
type BillingAddress = {
    cep?: string;
    logradouro?: string;
    numero?: string;
    complemento?: string;
    bairro?: string;
    cidade?: string;
    uf?: string;
};
type Client = {
    id: number;
    name: string;
    industry: string | null;
    notes: string | null;
    legal_name: string | null;
    tax_id: string | null;
    tax_id_type: string | null;
    state_registration: string | null;
    municipal_registration: string | null;
    billing_email: string | null;
    billing_address: BillingAddress | null;
    default_payment_terms_days: number | null;
    segment: string | null;
    tier: string | null;
    contacts: Contact[];
    opportunities: {
        id: number;
        title: string;
        stage: string;
        event_date?: string | null;
        next_action?: string | null;
        updated_at?: string | null;
    }[];
};
type Props = {
    client: Client;
    relationship: {
        proposals: number;
        receivedCents: number;
        recentActions: { title: string; caseId: number; caseTitle: string | null; at: string | null }[];
    };
    contractReadiness: { ready: boolean; missing: string[] };
    duplicates?: { id: number; name: string; legal_name: string | null }[];
};

function ContactForm({ clientId, contact, onSaved }: { clientId: number; contact?: Contact; onSaved: () => void }) {
    const form = useForm({
        name: contact?.name || '',
        email: contact?.email || '',
        phone: contact?.phone || '',
        role: contact?.role || '',
        department: contact?.department || '',
        whatsapp: contact?.whatsapp || '',
        preferred_channel: contact?.preferred_channel || 'email',
        is_primary: contact?.is_primary ?? false,
        is_decision_maker: contact?.is_decision_maker ?? false,
        revision: contact?.revision ?? 0,
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
            <Field label="Departamento" error={form.errors.department}>
                <input value={form.data.department} onChange={(event) => form.setData('department', event.target.value)} />
            </Field>
            <Field label="WhatsApp" error={form.errors.whatsapp}>
                <input type="tel" value={form.data.whatsapp} onChange={(event) => form.setData('whatsapp', event.target.value)} />
            </Field>
            <Field label="Canal preferido" error={form.errors.preferred_channel}>
                <select value={form.data.preferred_channel} onChange={(event) => form.setData('preferred_channel', event.target.value)}>
                    <option value="email">E-mail</option>
                    <option value="phone">Telefone</option>
                    <option value="whatsapp">WhatsApp</option>
                </select>
            </Field>
            <label>
                <input
                    type="checkbox"
                    checked={form.data.is_primary}
                    onChange={(event) => form.setData('is_primary', event.target.checked)}
                />{' '}
                Contato principal
            </label>
            <small>Selecionar este contato substitui o principal anterior.</small>
            <label>
                <input
                    type="checkbox"
                    checked={form.data.is_decision_maker}
                    onChange={(event) => form.setData('is_decision_maker', event.target.checked)}
                />{' '}
                Participa da decisão de contratação
            </label>
            <small>Remover este papel reabre as qualificações que dependem deste decisor.</small>
            <FormErrors errors={form.errors} />
            <button className="button button-primary" disabled={form.processing}>
                {contact ? 'Salvar contato' : 'Adicionar contato'}
            </button>
        </form>
    );
}

export default function ClientProfile({ client, relationship, contractReadiness, duplicates = [] }: Props) {
    const [editingClient, setEditingClient] = useState(false);
    const [editingContact, setEditingContact] = useState<Contact | null | undefined>(undefined);
    const [creatingOpportunity, setCreatingOpportunity] = useState(false);
    const address = client.billing_address ?? {};
    const form = useForm({
        name: client.name,
        industry: client.industry || '',
        notes: client.notes || '',
        legal_name: client.legal_name || '',
        tax_id: client.tax_id || '',
        tax_id_type: client.tax_id_type || 'cnpj',
        state_registration: client.state_registration || '',
        municipal_registration: client.municipal_registration || '',
        billing_email: client.billing_email || '',
        default_payment_terms_days: client.default_payment_terms_days ?? 30,
        segment: client.segment || 'corporativo',
        tier: client.tier || 'prospect',
        billing_address: {
            cep: address.cep || '',
            logradouro: address.logradouro || '',
            numero: address.numero || '',
            complemento: address.complemento || '',
            bairro: address.bairro || '',
            cidade: address.cidade || '',
            uf: address.uf || '',
        },
    });
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

            {duplicates.length > 0 && (
                <Surface className="client-alert client-alert--duplicate" role="status">
                    <CircleAlert size={17} />
                    <div>
                        <strong>Outro cadastro usa o mesmo documento fiscal.</strong>
                        <p>
                            Confira antes de seguir para evitar histórico dividido em dois lugares:{' '}
                            {duplicates.map((duplicate, index) => (
                                <span key={duplicate.id}>
                                    {index > 0 && ', '}
                                    <Link href={`/clients/${duplicate.id}`}>{duplicate.legal_name || duplicate.name}</Link>
                                </span>
                            ))}
                            .
                        </p>
                    </div>
                </Surface>
            )}

            {!contractReadiness.ready && (
                <Surface className="client-alert" role="status">
                    <CircleAlert size={17} />
                    <div>
                        <strong>Cadastro incompleto para emitir contrato.</strong>
                        <p>
                            Falta {contractReadiness.missing.join(', ')}. O cliente segue utilizável em proposta e orçamento; a pendência só
                            bloqueia o registro da assinatura.
                        </p>
                    </div>
                </Surface>
            )}

            <section className="client-dossier" aria-label="Panorama do relacionamento">
                <div>
                    <span className="eyebrow">PASTA DO RELACIONAMENTO</span>
                    <h2>{client.tier === 'prospect' ? 'Em aproximação' : 'Uma relação em movimento.'}</h2>
                    <p>
                        Este cadastro guarda as pessoas. Cada caso abaixo reúne uma negociação ou evento; a produção nasce quando o trabalho
                        avança.
                    </p>
                </div>
                <div className="client-dossier__counts">
                    <span>
                        <strong>{client.contacts.length}</strong> {client.contacts.length === 1 ? 'contato' : 'contatos'}
                    </span>
                    <span>
                        <strong>{client.opportunities.length}</strong> {client.opportunities.length === 1 ? 'caso' : 'casos'}
                    </span>
                    <span>
                        <strong>
                            {client.opportunities.filter((item) => !['lost', 'cancelled', 'closed'].includes(item.stage)).length}
                        </strong>{' '}
                        em andamento
                    </span>
                    <span>
                        <strong>{client.opportunities.filter((item) => ['closed'].includes(item.stage)).length}</strong> concluídos
                    </span>
                    <span>
                        <strong>{relationship.proposals}</strong> propostas enviadas
                    </span>
                    <span>
                        <strong>
                            {new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL', maximumFractionDigits: 0 }).format(
                                relationship.receivedCents / 100,
                            )}
                        </strong>{' '}
                        recebido
                    </span>
                </div>
            </section>
            {relationship.recentActions.length > 0 && (
                <section className="client-activity" aria-label="Últimos movimentos deste cliente">
                    <div>
                        <span className="eyebrow">HISTÓRICO RECENTE</span>
                        <h2>O relacionamento continua aqui.</h2>
                    </div>
                    <div>
                        {relationship.recentActions.map((action, index) => (
                            <Link key={`${action.caseId}-${index}`} href={`/opportunities/${action.caseId}`}>
                                <strong>{action.title}</strong>
                                <span>
                                    {action.caseTitle} · {action.at || 'Data não registrada'}
                                </span>
                                <ArrowRight size={15} />
                            </Link>
                        ))}
                    </div>
                </section>
            )}

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
                            {client.contacts
                                .filter((contact) => !contact.archived_at)
                                .map((contact) => (
                                    <button
                                        className="contact-card"
                                        type="button"
                                        key={contact.id}
                                        onClick={() => setEditingContact(contact)}
                                    >
                                        <span className="contact-card__avatar">
                                            <UserRound size={17} />
                                        </span>
                                        <span>
                                            <strong>{contact.name}</strong>
                                            <small>{contact.role || 'Função não informada'}</small>
                                            {contact.department && <small>{contact.department}</small>}
                                            {(contact.is_primary || contact.is_decision_maker) && (
                                                <small>
                                                    {[contact.is_primary && 'Principal', contact.is_decision_maker && 'Decisor']
                                                        .filter(Boolean)
                                                        .join(' · ')}
                                                </small>
                                            )}
                                            <small>
                                                Prefere{' '}
                                                {
                                                    (
                                                        { email: 'e-mail', phone: 'telefone', whatsapp: 'WhatsApp' } as Record<
                                                            string,
                                                            string
                                                        >
                                                    )[contact.preferred_channel]
                                                }
                                            </small>
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
                                        <small>
                                            {item.next_action ? `Próximo passo: ${item.next_action}` : 'Próximo passo ainda não definido'}
                                            {item.event_date
                                                ? ` · Evento ${item.event_date.slice(0, 10).split('-').reverse().join('/')}`
                                                : ''}
                                        </small>
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
                    <Field label="Razão social" error={form.errors.legal_name}>
                        <input
                            value={form.data.legal_name}
                            onChange={(event) => form.setData('legal_name', event.target.value)}
                            placeholder="Como consta no contrato social"
                        />
                    </Field>
                    <div className="two-fields">
                        <Field label="Tipo de documento" error={form.errors.tax_id_type}>
                            <select value={form.data.tax_id_type} onChange={(event) => form.setData('tax_id_type', event.target.value)}>
                                <option value="cnpj">CNPJ</option>
                                <option value="cpf">CPF</option>
                                <option value="estrangeiro">Estrangeiro</option>
                            </select>
                        </Field>
                        <Field label="Documento" error={form.errors.tax_id}>
                            <input
                                value={form.data.tax_id}
                                onChange={(event) => form.setData('tax_id', event.target.value)}
                                placeholder={form.data.tax_id_type === 'cpf' ? '000.000.000-00' : '00.000.000/0000-00'}
                                inputMode="numeric"
                            />
                        </Field>
                    </div>
                    <div className="two-fields">
                        <Field label="Inscrição estadual" error={form.errors.state_registration}>
                            <input
                                value={form.data.state_registration}
                                onChange={(event) => form.setData('state_registration', event.target.value)}
                                placeholder="ISENTO, se for o caso"
                            />
                        </Field>
                        <Field label="E-mail de faturamento" error={form.errors.billing_email}>
                            <input
                                type="email"
                                value={form.data.billing_email}
                                onChange={(event) => form.setData('billing_email', event.target.value)}
                            />
                        </Field>
                    </div>
                    <Field label="Inscrição municipal" error={form.errors.municipal_registration}>
                        <input
                            value={form.data.municipal_registration}
                            onChange={(event) => form.setData('municipal_registration', event.target.value)}
                        />
                    </Field>
                    <div className="two-fields">
                        <Field label="Segmento do cliente" error={form.errors.segment}>
                            <select value={form.data.segment} onChange={(event) => form.setData('segment', event.target.value)}>
                                <option value="corporativo">Corporativo</option>
                                <option value="social">Social</option>
                                <option value="institucional">Institucional</option>
                                <option value="cultural">Cultural</option>
                                <option value="esportivo">Esportivo</option>
                                <option value="religioso">Religioso</option>
                                <option value="governo">Governo</option>
                                <option value="terceiro_setor">Terceiro setor</option>
                            </select>
                        </Field>
                        <Field label="Relação" error={form.errors.tier}>
                            <select value={form.data.tier} onChange={(event) => form.setData('tier', event.target.value)}>
                                <option value="prospect">Prospect</option>
                                <option value="ativo">Ativo</option>
                                <option value="recorrente">Recorrente</option>
                                <option value="inativo">Inativo</option>
                            </select>
                        </Field>
                    </div>
                    <fieldset className="rd-fieldset">
                        <legend>Endereço de faturamento</legend>
                        <div className="two-fields">
                            <Field label="CEP">
                                <input
                                    value={form.data.billing_address.cep}
                                    onChange={(event) =>
                                        form.setData('billing_address', { ...form.data.billing_address, cep: event.target.value })
                                    }
                                    inputMode="numeric"
                                />
                            </Field>
                            <Field label="Número">
                                <input
                                    value={form.data.billing_address.numero}
                                    onChange={(event) =>
                                        form.setData('billing_address', { ...form.data.billing_address, numero: event.target.value })
                                    }
                                />
                            </Field>
                        </div>
                        <Field label="Logradouro">
                            <input
                                value={form.data.billing_address.logradouro}
                                onChange={(event) =>
                                    form.setData('billing_address', { ...form.data.billing_address, logradouro: event.target.value })
                                }
                            />
                        </Field>
                        <div className="two-fields">
                            <Field label="Cidade">
                                <input
                                    value={form.data.billing_address.cidade}
                                    onChange={(event) =>
                                        form.setData('billing_address', { ...form.data.billing_address, cidade: event.target.value })
                                    }
                                />
                            </Field>
                            <Field label="Estado">
                                <input
                                    maxLength={2}
                                    value={form.data.billing_address.uf}
                                    onChange={(event) =>
                                        form.setData('billing_address', { ...form.data.billing_address, uf: event.target.value })
                                    }
                                />
                            </Field>
                        </div>
                    </fieldset>
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
