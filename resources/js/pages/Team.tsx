import { Head, useForm } from '@inertiajs/react';
import { AppLayout } from '../layout';
import { FormErrors } from '../components/FormErrors';
import { Crown, BriefcaseBusiness, Clapperboard, WalletCards, Wrench, Users, ShieldCheck, Plus } from 'lucide-react';
import { useState } from 'react';
import { Drawer } from '../components/FormControls';
import { PageHeader } from '../components/ui/PageHeader';
const jobs = [
    { label: 'Direção', icon: Crown },
    { label: 'Comercial', icon: BriefcaseBusiness },
    { label: 'Produção', icon: Clapperboard },
    { label: 'Financeiro', icon: WalletCards },
    { label: 'Técnica', icon: Wrench },
    { label: 'Coordenação', icon: Users },
];

type User = {
    id: number;
    name: string;
    email: string;
    role: string;
    jobTitle: string | null;
    active: boolean;
    canApproveCommercial: boolean;
};

function Member({ user, onSaved }: { user?: User; onSaved?: () => void }) {
    const form = useForm({
        name: user?.name || '',
        email: user?.email || '',
        role: user?.role || 'producer',
        job_title: user?.jobTitle || '',
        is_active: user?.active ?? true,
        password: '',
    });
    const password = useForm({ password: '' });
    const authority = useForm({ password: '', enabled: user?.canApproveCommercial ?? false, reason: '' });

    return (
        <>
            <form
                className="form-grid"
                onSubmit={(e) => {
                    e.preventDefault();
                    user
                        ? form.patch(`/team/${user.id}`, { onSuccess: onSaved })
                        : form.post('/team', {
                              onSuccess: () => {
                                  form.reset();
                                  onSaved?.();
                              },
                          });
                }}
            >
                <label>
                    Nome
                    <input required value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                </label>
                <label>
                    E-mail
                    <input required type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                </label>
                <fieldset className="team-job-picker">
                    <legend>Cargo na operação</legend>
                    <div>
                        {jobs.map(({ label, icon: Icon }) => (
                            <button
                                type="button"
                                key={label}
                                aria-pressed={form.data.job_title === label}
                                onClick={() => form.setData('job_title', label)}
                            >
                                <Icon size={19} />
                                {label}
                            </button>
                        ))}
                    </div>
                </fieldset>
                <label>
                    Cargo personalizado
                    <input
                        maxLength={100}
                        placeholder="Ex.: Direção executiva"
                        value={form.data.job_title}
                        onChange={(e) => form.setData('job_title', e.target.value)}
                    />
                </label>
                <label>
                    Permissão de acesso
                    <select value={form.data.role} onChange={(e) => form.setData('role', e.target.value)}>
                        <option value="producer">Operação — sem administração</option>
                        <option value="admin">Administrador técnico</option>
                    </select>
                </label>
                {user ? (
                    <label>
                        Acesso
                        <select value={String(form.data.is_active)} onChange={(e) => form.setData('is_active', e.target.value === 'true')}>
                            <option value="true">Ativo</option>
                            <option value="false">Desativado</option>
                        </select>
                    </label>
                ) : (
                    <label>
                        Senha inicial (mínimo 12 caracteres)
                        <input
                            required
                            type="password"
                            autoComplete="new-password"
                            minLength={12}
                            value={form.data.password}
                            onChange={(e) => form.setData('password', e.target.value)}
                        />
                    </label>
                )}
                <FormErrors errors={form.errors} />
                <button className="button button-primary" disabled={form.processing}>
                    {user ? 'Salvar acesso' : 'Criar usuário'}
                </button>
            </form>
            {user && (
                <>
                    <form
                        className="form-grid"
                        onSubmit={(e) => {
                            e.preventDefault();
                            password.post(`/team/${user.id}/password`, { onSuccess: () => password.reset() });
                        }}
                    >
                        <label>
                            Redefinir senha
                            <input
                                required
                                type="password"
                                minLength={12}
                                autoComplete="new-password"
                                value={password.data.password}
                                onChange={(e) => password.setData('password', e.target.value)}
                            />
                        </label>
                        <FormErrors errors={password.errors} />
                        <button className="button button-subtle" disabled={password.processing}>
                            Redefinir acesso
                        </button>
                    </form>
                    <form
                        className="form-grid authority-form"
                        onSubmit={(e) => {
                            e.preventDefault();
                            authority.post(`/team/${user.id}/commercial-authority`, {
                                onSuccess: () => authority.reset('password', 'reason'),
                            });
                        }}
                    >
                        <label>
                            Autoridade comercial
                            <select
                                value={String(authority.data.enabled)}
                                onChange={(e) => authority.setData('enabled', e.target.value === 'true')}
                            >
                                <option value="false">Sem aprovação</option>
                                <option value="true">Pode aprovar</option>
                            </select>
                        </label>
                        <label>
                            Confirme sua senha
                            <input
                                required
                                type="password"
                                autoComplete="current-password"
                                value={authority.data.password}
                                onChange={(e) => authority.setData('password', e.target.value)}
                            />
                        </label>
                        <label>
                            Motivo da alteração
                            <textarea
                                required
                                rows={2}
                                value={authority.data.reason}
                                onChange={(e) => authority.setData('reason', e.target.value)}
                            />
                        </label>
                        <FormErrors errors={authority.errors} />
                        <button className="button button-subtle" disabled={authority.processing}>
                            Atualizar autoridade
                        </button>
                    </form>
                </>
            )}
        </>
    );
}

export default function Team({ users }: { users: User[] }) {
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<User | null>(null);
    return (
        <AppLayout>
            <Head title="Equipe" />
            <PageHeader
                eyebrow="ADMINISTRAÇÃO / PESSOAS"
                title="Equipe e responsabilidades"
                description="As pessoas certas, cada uma com seu papel na operação."
                primaryAction={
                    <button className="button button-primary" onClick={() => setCreating(true)}>
                        <Plus size={17} />
                        Adicionar pessoa
                    </button>
                }
            />
            <section className="team-intro">
                <div>
                    <Crown size={30} strokeWidth={1.3} />
                    <h2>Uma equipe. Muitos talentos.</h2>
                    <p>Defina cargos, organize os responsáveis e gerencie o acesso de cada pessoa.</p>
                </div>
                <dl>
                    <div>
                        <dt>Pessoas ativas</dt>
                        <dd>{users.filter((u) => u.active).length}</dd>
                    </div>
                    <div>
                        <dt>Administradores</dt>
                        <dd>{users.filter((u) => u.active && ['admin', 'administrator'].includes(u.role)).length}</dd>
                    </div>
                </dl>
            </section>
            <section className="team-role-legend" aria-label="Papéis da operação">
                {jobs.map(({ label, icon: Icon }) => (
                    <div key={label}>
                        <Icon size={20} />
                        <span>{label}</span>
                    </div>
                ))}
            </section>
            <section className="team-member-grid" aria-label="Pessoas da equipe">
                {users.map((user) => {
                    const Icon = jobs.find((job) => job.label === user.jobTitle)?.icon || Users;
                    return (
                        <article className="team-member-card" key={user.id}>
                            <header>
                                <span className="team-role-icon">
                                    <Icon size={24} />
                                </span>
                                <span className="status-pill gray">{user.active ? 'Ativo' : 'Desativado'}</span>
                            </header>
                            <span className="eyebrow">{user.jobTitle || 'Cargo a definir'}</span>
                            <h2>{user.name}</h2>
                            <p>{user.email}</p>
                            <div className="team-permissions">
                                <span>
                                    <ShieldCheck size={14} />
                                    {['admin', 'administrator'].includes(user.role) ? 'Administração técnica' : 'Acesso à operação'}
                                </span>
                                <span>{user.canApproveCommercial ? 'Aprovador comercial' : 'Sem aprovação comercial'}</span>
                            </div>
                            <button className="button button-subtle" onClick={() => setEditing(user)}>
                                Gerenciar {user.name}
                            </button>
                        </article>
                    );
                })}
            </section>
            <p className="team-access-note">
                O cargo identifica a responsabilidade na equipe. Permissões administrativas e aprovação comercial são configuradas
                separadamente.
            </p>
            <Drawer title="Adicionar pessoa" open={creating} onClose={() => setCreating(false)}>
                <Member onSaved={() => setCreating(false)} />
            </Drawer>
            <Drawer
                title={editing ? `Gerenciar ${editing.name}` : 'Gerenciar pessoa'}
                open={Boolean(editing)}
                onClose={() => setEditing(null)}
            >
                {editing && <Member key={editing.id} user={editing} onSaved={() => setEditing(null)} />}
            </Drawer>
        </AppLayout>
    );
}
