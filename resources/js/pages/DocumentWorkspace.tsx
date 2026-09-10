import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { AppLayout } from '../layout';
import { Field } from '../components/FormControls';
import { CasePageHeader } from '../components/CasePageHeader';
import { ExternalLink, Files } from 'lucide-react';
type Sections = { objective: string; scope: string; conditions: string };
type Props = {
    opportunity: { id: number; title: string; clientName: string };
    document: {
        id: number | null;
        type: string;
        title: string;
        status: string;
        version: number;
        purpose: string;
        notes?: string;
        sections: Sections;
        sources?: { briefing_revision: number; budget_id: number | null; budget_revision: number | null; budget?: { totalCents: number } };
    };
    latestVersion: number;
    stale: boolean;
    canReview: boolean;
    versions: { version: number; status: string; purpose: string; sections: Partial<Sections> }[];
    shareLinks?: { id: number; expiresAt: string; revokedAt?: string | null; views: number; firstViewedAt?: string | null }[];
};
const labels: Record<keyof Sections, string> = { objective: 'Objetivo', scope: 'Escopo', conditions: 'Condições' };
export default function DocumentWorkspace(props: Props) {
    return (
        <AppLayout>
            <Editor key={`${props.document.id}-${props.document.status}`} {...props} />
        </AppLayout>
    );
}
function Editor({ opportunity, document: d, latestVersion, stale, canReview, versions, shareLinks = [] }: Props) {
    const f = useForm({ title: d.title, purpose: d.purpose, expected_version: latestVersion, notes: d.notes || '', sections: d.sections });
    const [notice, setNotice] = useState(''),
        [compare, setCompare] = useState(''),
        [evidence, setEvidence] = useState(''),
        [busy, setBusy] = useState(false);
    const [signerName, setSignerName] = useState(''),
        [signedAt, setSignedAt] = useState(new Date().toISOString().slice(0, 10)),
        [signatureMethod, setSignatureMethod] = useState('plataforma_externa'),
        [signatureEvidence, setSignatureEvidence] = useState('');
    const flash = usePage<{ flash?: { share_url?: string } }>().props.flash;
    const old = versions.find((v) => String(v.version) === compare);
    const base = `/opportunities/${opportunity.id}`;
    function action(kind: string, data: Record<string, string> = {}) {
        setBusy(true);
        router.post(`${base}/documents/${d.id}/${kind}`, data, {
            preserveScroll: true,
            onError: (e) => setNotice(Object.values(e).join(' ')),
            onFinish: () => setBusy(false),
        });
    }
    return (
        <>
            <Head title={`Documentos · ${opportunity.title}`} />
            <CasePageHeader
                id={opportunity.id}
                eyebrow={d.type === 'contract' ? 'Contrato' : d.purpose === 'management' ? 'Proposta de Gestão' : 'Proposta de Viabilidade'}
                title={d.type === 'contract' ? 'Contrato · rascunho' : 'Proposta revisada'}
                client={opportunity.clientName}
                status={`v${d.version} · ${d.status}`}
                actions={
                    <div className="topbar-actions">
                        <Link className="button button-subtle" href={`${base}/documents`}>
                            <Files size={15} /> Central
                        </Link>
                        {d.id && (
                            <a className="button button-subtle" href={`${base}/documents/${d.id}/pdf`} target="_blank" rel="noreferrer">
                                <ExternalLink size={15} /> Abrir PDF
                            </a>
                        )}
                    </div>
                }
            />
            {stale && (
                <p className="rd-panel" role="status">
                    As fontes mudaram ou há versão mais recente. A cópia anterior está preservada; salve um novo rascunho e revise
                    novamente.
                </p>
            )}
            {notice && (
                <p role="alert" className="form-error">
                    {notice}
                </p>
            )}
            <section className="document-grid">
                <article className="rd-panel document-preview">
                    <span className="eyebrow">PRÉVIA EDITÁVEL · NÃO É ENVIO</span>
                    <h2>{f.data.title}</h2>
                    {Object.entries(labels).map(([key, label]) => (
                        <section key={key}>
                            <h3>{label}</h3>
                            <p style={{ whiteSpace: 'pre-wrap', overflowWrap: 'anywhere' }}>
                                {f.data.sections[key as keyof Sections] || 'Ainda não informado'}
                            </p>
                        </section>
                    ))}
                    <h3>Investimento da versão salva</h3>
                    <p>
                        {d.sources?.budget
                            ? new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(
                                  d.sources.budget.totalCents / 100,
                              )
                            : 'Pendente de orçamento revisado'}
                    </p>
                    <p>
                        Briefing r{d.sources?.briefing_revision ?? '—'} · Orçamento #{d.sources?.budget_id ?? '—'} r
                        {d.sources?.budget_revision ?? '—'}
                    </p>
                </article>
                <section className="rd-panel">
                    <h2>Preparar nova versão</h2>
                    <p>Salvar cria um rascunho novo. As versões anteriores não serão sobrescritas.</p>
                    <form
                        className="form-grid"
                        onSubmit={(e) => {
                            e.preventDefault();
                            f.post(`${base}/${d.type}`, { preserveScroll: true });
                        }}
                    >
                        {Object.values(f.errors).map((e, i) => (
                            <p role="alert" className="form-error" key={i}>
                                {e}
                            </p>
                        ))}
                        <Field label="Título">
                            <input required value={f.data.title} onChange={(e) => f.setData('title', e.target.value)} />
                        </Field>
                        <Field label="Finalidade">
                            <select value={f.data.purpose} onChange={(e) => f.setData('purpose', e.target.value)}>
                                <option value="viability">Viabilidade</option>
                                <option value="management">Gestão</option>
                            </select>
                        </Field>
                        {Object.entries(labels).map(([key, label]) => (
                            <Field label={label} key={key}>
                                <textarea
                                    required
                                    rows={5}
                                    value={f.data.sections[key as keyof Sections]}
                                    onChange={(e) => f.setData('sections', { ...f.data.sections, [key]: e.target.value })}
                                />
                            </Field>
                        ))}
                        <Field label="Notas internas (não aparecem no PDF)">
                            <textarea value={f.data.notes} onChange={(e) => f.setData('notes', e.target.value)} />
                        </Field>
                        <button className="button button-primary" disabled={f.processing}>
                            {f.processing ? 'Salvando…' : 'Salvar novo rascunho'}
                        </button>
                    </form>
                    <hr />
                    <h3>Revisão da versão salva</h3>
                    <p>Alterações ainda não salvas não entram na revisão ou no PDF.</p>
                    <button
                        className="button button-subtle"
                        disabled={busy || !d.id || d.type !== 'proposal' || !canReview || stale || d.status !== 'draft' || f.isDirty}
                        onClick={() => action('review')}
                    >
                        Revisar e liberar versão salva
                    </button>
                    {!canReview && <p>Liberação depende de autoridade comercial e regras validadas. Rascunhos continuam disponíveis.</p>}
                    {d.type === 'contract' && <p>Liberação contratual depende de modelo validado e fica fora deste ciclo.</p>}
                    {d.status === 'reviewed' && (
                        <>
                            <Field label="Evidência do envio manual">
                                <textarea
                                    value={evidence}
                                    onChange={(e) => setEvidence(e.target.value)}
                                    placeholder="Destinatário, canal e referência do envio realizado"
                                />
                            </Field>
                            <button
                                className="button button-primary"
                                disabled={busy || stale || evidence.trim().length < 3 || f.isDirty}
                                onClick={() => action('sent', { evidence })}
                            >
                                Registrar envio realizado
                            </button>
                        </>
                    )}
                    {d.status === 'sent' && (
                        <section className="share-link-actions">
                            <h3>Compartilhar com o cliente</h3>
                            <p>
                                Crie um link temporário da versão enviada. O cliente verá apenas o conteúdo público e poderá aceitar ou
                                pedir ajustes.
                            </p>
                            <button className="button button-primary" disabled={busy || stale} onClick={() => action('share')}>
                                Criar link seguro
                            </button>
                        </section>
                    )}
                    {d.type === 'contract' && d.status === 'sent' && (
                        <section className="signature-panel" aria-labelledby="signature-title">
                            <h3 id="signature-title">Registrar assinatura externa</h3>
                            <p className="field-help">
                                A assinatura acontece fora do sistema. Registre apenas após conferir o comprovante; o contrato enviado
                                continuará preservado.
                            </p>
                            <div className="form-grid">
                                <Field label="Nome de quem assinou">
                                    <input required value={signerName} onChange={(e) => setSignerName(e.target.value)} />
                                </Field>
                                <Field label="Data da assinatura">
                                    <input type="date" required value={signedAt} onChange={(e) => setSignedAt(e.target.value)} />
                                </Field>
                                <Field label="Método">
                                    <input
                                        required
                                        value={signatureMethod}
                                        onChange={(e) => setSignatureMethod(e.target.value)}
                                        placeholder="Ex.: plataforma externa"
                                    />
                                </Field>
                                <Field label="Evidência">
                                    <textarea
                                        required
                                        minLength={3}
                                        value={signatureEvidence}
                                        onChange={(e) => setSignatureEvidence(e.target.value)}
                                        placeholder="Link, protocolo ou referência do comprovante"
                                    />
                                </Field>
                                <button
                                    className="button button-primary"
                                    disabled={
                                        busy ||
                                        !signerName.trim() ||
                                        !signedAt ||
                                        !signatureMethod.trim() ||
                                        signatureEvidence.trim().length < 3
                                    }
                                    onClick={() =>
                                        action('external-signature', {
                                            signer_name: signerName,
                                            signed_at: signedAt,
                                            method: signatureMethod,
                                            evidence: signatureEvidence,
                                        })
                                    }
                                >
                                    Confirmar assinatura registrada
                                </button>
                            </div>
                        </section>
                    )}
                    {flash?.share_url && (
                        <section className="rd-panel share-link-panel" aria-labelledby="share-link-title">
                            <h3 id="share-link-title">Link privado da versão enviada</h3>
                            <p>
                                Compartilhe este endereço com o cliente. Ele não mostra notas internas nem permite outra decisão após o
                                primeiro retorno.
                            </p>
                            <div className="share-link-row">
                                <input readOnly value={flash.share_url} aria-label="Link privado para o cliente" />
                                <button
                                    className="button button-subtle"
                                    type="button"
                                    onClick={() => void navigator.clipboard?.writeText(flash.share_url ?? '')}
                                >
                                    Copiar link
                                </button>
                            </div>
                        </section>
                    )}
                    {['sent', 'accepted', 'changes_requested'].includes(d.status) && shareLinks.length > 0 && (
                        <section className="share-link-history" aria-labelledby="share-history-title">
                            <h3 id="share-history-title">Links desta versão</h3>
                            {shareLinks.map((link) => (
                                <div className="share-link-row" key={link.id}>
                                    <span>
                                        Link #{link.id} · {link.views} visualizaç{link.views === 1 ? 'ão' : 'ões'}
                                        {link.revokedAt ? ' · revogado' : ''}
                                    </span>
                                    {!link.revokedAt && d.status === 'sent' && (
                                        <button
                                            className="button button-subtle"
                                            type="button"
                                            onClick={() => router.post(`${base}/documents/${d.id}/share/${link.id}/revoke`)}
                                        >
                                            Revogar
                                        </button>
                                    )}
                                </div>
                            ))}
                        </section>
                    )}
                </section>
            </section>
            <section className="rd-panel">
                <h2>Histórico e comparação</h2>
                <div className="rd-form-actions">
                    {versions.map((v) => (
                        <Link
                            className="button button-subtle"
                            key={v.version}
                            href={`${base}/${d.type}?purpose=${d.purpose}&version=${v.version}`}
                        >
                            v{v.version} · {v.purpose || 'legado'} · {v.status}
                        </Link>
                    ))}
                </div>
                <Field label="Comparar texto com">
                    <select value={compare} onChange={(e) => setCompare(e.target.value)}>
                        <option value="">Selecionar versão</option>
                        {versions
                            .filter((v) => v.version !== d.version)
                            .map((v) => (
                                <option key={v.version} value={v.version}>
                                    Versão {v.version}
                                </option>
                            ))}
                    </select>
                </Field>
                {old &&
                    Object.entries(labels).map(([key, label]) => (
                        <section key={key}>
                            <h3>
                                {label} ·{' '}
                                {old.sections[key as keyof Sections] === f.data.sections[key as keyof Sections]
                                    ? 'sem alteração'
                                    : 'alterado'}
                            </h3>
                            <p style={{ whiteSpace: 'pre-wrap' }}>Anterior: {old.sections[key as keyof Sections] || 'Não informado'}</p>
                            <p style={{ whiteSpace: 'pre-wrap' }}>Atual: {f.data.sections[key as keyof Sections]}</p>
                        </section>
                    ))}
            </section>
        </>
    );
}
