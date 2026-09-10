import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, ShieldCheck } from 'lucide-react';
import { FormEvent } from 'react';

export default function ResetPassword({ token, email }: { token: string; email: string }) {
    const { data, setData, post, processing, errors } = useForm({ token, email, password: '', password_confirmation: '' });

    function submit(event: FormEvent) {
        event.preventDefault();
        post('/reset-password', { onFinish: () => setData((current) => ({ ...current, password: '', password_confirmation: '' })) });
    }

    return <><Head title="Definir nova senha" />
        <main className="login-shell login-shell-v2">
            <section className="login-context">
                <div className="login-context__brand"><span aria-hidden="true">✦</span><strong>Padrão RD</strong></div>
                <div className="login-context__copy">
                    <span className="eyebrow">NOVA SENHA</span>
                    <h1>Defina uma senha só sua.</h1>
                    <p>Mínimo de 10 caracteres, com letras e números, e que não apareça em vazamentos conhecidos. Ao confirmar, todas as sessões abertas nesta conta são encerradas.</p>
                </div>
            </section>
            <section className="login-card login-card-v2">
                <div className="login-heading">
                    <span className="eyebrow">REDEFINIR SENHA</span>
                    <h2>Definir nova senha</h2>
                    <p>Este link só pode ser usado uma vez.</p>
                </div>
                <form className="form-grid" onSubmit={submit}>
                    <label>E-mail
                        <input type="email" required autoComplete="username" value={data.email} onChange={(event) => setData('email', event.target.value)} />
                        {errors.email && <small className="field-error" role="alert">{errors.email}</small>}
                    </label>
                    <label>Nova senha
                        <input type="password" required autoComplete="new-password" value={data.password} onChange={(event) => setData('password', event.target.value)} />
                        {errors.password && <small className="field-error" role="alert">{errors.password}</small>}
                    </label>
                    <label>Confirme a nova senha
                        <input type="password" required autoComplete="new-password" value={data.password_confirmation} onChange={(event) => setData('password_confirmation', event.target.value)} />
                    </label>
                    <button className="button button-primary login-submit" type="submit" disabled={processing}>
                        {processing ? 'Salvando…' : 'Salvar nova senha'}<ArrowRight size={16} />
                    </button>
                </form>
                <p className="demo-credential"><ShieldCheck size={14} /> Guardamos apenas o resumo criptográfico da senha.</p>
                <Link className="button button-subtle" href="/login"><ArrowLeft size={15} /> Voltar para o login</Link>
            </section>
        </main></>;
}
