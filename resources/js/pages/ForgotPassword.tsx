import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, MailCheck } from 'lucide-react';
import { FormEvent } from 'react';

export default function ForgotPassword() {
    const { data, setData, post, processing, errors } = useForm({ email: '' });
    const flash = usePage<{ flash?: { success?: string } }>().props.flash;

    function submit(event: FormEvent) {
        event.preventDefault();
        post('/forgot-password', { onSuccess: () => setData('email', '') });
    }

    return <><Head title="Recuperar acesso" />
        <main className="login-shell login-shell-v2">
            <section className="login-context">
                <div className="login-context__brand"><span aria-hidden="true">✦</span><strong>Padrão RD</strong></div>
                <div className="login-context__copy">
                    <span className="eyebrow">RECUPERAR ACESSO</span>
                    <h1>Perder a senha não pode parar a operação.</h1>
                    <p>Enviamos um link de uso único para o e-mail cadastrado. Ele vale por 60 minutos e encerra as sessões abertas quando a nova senha é definida.</p>
                </div>
            </section>
            <section className="login-card login-card-v2">
                <div className="login-heading">
                    <span className="eyebrow">REDEFINIR SENHA</span>
                    <h2>Enviar link de redefinição</h2>
                    <p>Informe o e-mail do seu acesso interno.</p>
                </div>
                {flash?.success && <p className="assistance-banner" role="status"><MailCheck size={16} /> {flash.success}</p>}
                <form className="form-grid" onSubmit={submit}>
                    <label>E-mail
                        <input type="email" required autoComplete="email" value={data.email} onChange={(event) => setData('email', event.target.value)} />
                        {errors.email && <small className="field-error" role="alert">{errors.email}</small>}
                    </label>
                    <button className="button button-primary login-submit" type="submit" disabled={processing}>
                        {processing ? 'Enviando…' : 'Enviar link'}<ArrowRight size={16} />
                    </button>
                </form>
                <Link className="button button-subtle" href="/login"><ArrowLeft size={15} /> Voltar para o login</Link>
            </section>
        </main></>;
}
