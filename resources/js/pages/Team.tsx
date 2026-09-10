import { Head, useForm } from '@inertiajs/react';
import { AppLayout } from '../layout';
import { FormErrors } from '../components/FormErrors';
import { GlassSurface } from '../components/GlassSurface';

type User = { id: number; name: string; email: string; role: string; active: boolean; canApproveCommercial: boolean };

function Member({ user }: { user?: User }) {
    const form = useForm({
        name: user?.name || '',
        email: user?.email || '',
        role: user?.role || 'producer',
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
                    user ? form.patch(`/team/${user.id}`) : form.post('/team', { onSuccess: () => form.reset() });
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
                <label>
                    Papel
                    <select value={form.data.role} onChange={(e) => form.setData('role', e.target.value)}>
                        <option value="producer">Produtor</option>
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
    return (
        <AppLayout>
            <Head title="Equipe" />
            <header className="topbar">
                <div>
                    <span className="eyebrow">ADMINISTRAÇÃO</span>
                    <h1>Equipe.</h1>
                    <p>Administração técnica não concede aprovação comercial. A atribuição depende da validação das regras da Padrão RD.</p>
                </div>
            </header>
            <section className="workspace-grid">
                <GlassSurface>
                    <h2>Membros</h2>
                    {users.map((user) => (
                        <details key={user.id}>
                            <summary className="client-row">
                                <div>
                                    <strong>{user.name}</strong>
                                    <small>{user.email}</small>
                                </div>
                                <span className="status-pill gray">
                                    {user.active ? 'Ativo' : 'Desativado'} ·{' '}
                                    {user.canApproveCommercial ? 'Aprovador comercial' : 'Sem aprovação comercial'}
                                </span>
                            </summary>
                            <Member user={user} />
                        </details>
                    ))}
                </GlassSurface>
                <GlassSurface>
                    <h2>Novo usuário</h2>
                    <Member />
                </GlassSurface>
            </section>
        </AppLayout>
    );
}
