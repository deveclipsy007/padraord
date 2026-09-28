import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import {
    ArrowRight,
    ArrowUpRight,
    Check,
    CheckCircle2,
    Clock3,
    Download,
    FileCheck2,
    Globe2,
    Layers3,
    LockKeyhole,
    Plus,
    ShieldCheck,
} from 'lucide-react';
import { AppLayout } from '../layout';
import { compareVersions, getJson, localDate, presentValue, type StoredVersion } from '../components/control-utils';
type Pending = {
    id: number;
    title: string;
    kind: string;
    awaiting: string;
    owner: string;
    due_date: string | null;
    next_contact_at: string | null;
    status: string;
    revision: number;
    resolution: string | null;
};
type Impact = { note: string; fingerprint: string; groups: { title: string; count: number; detail: string; href: string }[] };
type Props = {
    opportunity: { id: number; title: string; client_name: string };
    pending: Pending[];
    users: { id: number; name: string }[];
    readiness: { key: string; title: string; ready: boolean; detail: string; href: string }[];
    versions: StoredVersion[];
    portalLinks: { id: number; created_at: string; expires_at: string; revoked_at: string | null }[];
    portalResponses: {
        id: number;
        client_portal_link_id: number;
        item_key: string;
        name: string;
        decision: string;
        message: string | null;
        created_at: string;
    }[];
    portalUploads: { id: number; name: string; original_name: string; created_at: string }[];
    documents: { id: number; title: string; version: number }[];
    portalUrl: string | null;
    canPublish: boolean;
};
const labels: Record<string, string> = {
    objective: 'Objetivo',
    scope: 'Escopo',
    inclusions: 'Incluído',
    exclusions: 'Fora do escopo',
    conditions: 'Condições',
    clauses: 'Cláusulas',
    audience_profile: 'Perfil do público',
    scope_summary: 'Resumo do escopo',
    event_name: 'Nome do evento',
    starts_at: 'Início',
    ends_at: 'Término',
    budget_declared_cents: 'Investimento informado',
    location_note: 'Local',
    requirements: 'Requisitos',
    program: 'Programação',
    sources: 'Fontes',
};
export default function ProjectControl({
    opportunity: o,
    pending,
    users,
    readiness,
    versions,
    portalLinks,
    portalResponses,
    portalUploads,
    documents,
    portalUrl,
    canPublish,
}: Props) {
    const base = '/opportunities/' + o.id + '/control';
    const { auth, errors } = usePage<{ auth: { user: { id: number; isAdmin: boolean } }; errors: Record<string, string> }>().props;
    const [tab, setTab] = useState('pending');
    const [add, setAdd] = useState(false);
    const [impact, setImpact] = useState<Impact | null>(null);
    const [change, setChange] = useState('date');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [kind, setKind] = useState('briefing');
    const [before, setBefore] = useState('');
    const [after, setAfter] = useState('');
    const [portalPreview, setPortalPreview] = useState(false);
    const [deliveries, setDeliveries] = useState('');
    const form = useForm({
        title: '',
        kind: 'answer',
        awaiting: 'client',
        owner_id: String(auth.user.id),
        due_date: '',
        next_contact_at: '',
    });
    const portal = useForm({
        message: 'Acompanhe aqui os próximos passos do seu projeto.',
        milestones: '',
        deliverables: [] as string[],
        document_ids: [] as number[],
        expires_days: 7,
    });
    const available = versions.filter((v) => v.kind === kind);
    const old = available.find((v) => v.id === before) || available.at(-2);
    const latest = available.find((v) => v.id === after) || available.at(-1);
    const diff = useMemo(() => (old && latest ? compareVersions(old, latest) : []), [old, latest]);
    async function previewImpact() {
        setLoading(true);
        setError('');
        try {
            setImpact(await getJson<Impact>(base + '/impact?change=' + change));
        } catch (e) {
            setError((e as Error).message);
        } finally {
            setLoading(false);
        }
    }
    return (
        <AppLayout>
            <Head title={'Controle · ' + o.title} />
            <header className="control-hero control-hero--compact">
                <div>
                    <span className="rd-eyebrow">CONTROLE DO PROJETO / {o.client_name}</span>
                    <h1>O que precisa acontecer.</h1>
                    <p>{o.title} · Decisões claras, do planejamento à entrega.</p>
                </div>
                <ShieldCheck size={45} strokeWidth={1.2} />
            </header>
            <nav className="control-tabs" aria-label="Controle do projeto">
                {[
                    ['pending', 'Pendências'],
                    ['readiness', 'Prontidão'],
                    ['impact', 'Impacto de mudanças'],
                    ['versions', 'Comparar versões'],
                    ['portal', 'Portal do cliente'],
                ].map(([k, l]) => (
                    <button key={k} aria-pressed={tab === k} onClick={() => setTab(k)}>
                        {l}
                    </button>
                ))}
            </nav>
            {Object.values(errors || {}).map((e, i) => (
                <p className="control-error" key={i} role="alert">
                    {e}
                </p>
            ))}
            {error && (
                <p className="control-error" role="alert">
                    {error}
                </p>
            )}
            {tab === 'pending' && (
                <section className="control-panel">
                    <div className="control-section-heading">
                        <div>
                            <span className="rd-eyebrow">COMBINADOS E PRÓXIMOS CONTATOS</span>
                            <h2>De quem é o próximo passo?</h2>
                        </div>
                        <button className="button primary" onClick={() => setAdd(!add)}>
                            <Plus size={16} /> Nova pendência
                        </button>
                    </div>
                    {add && (
                        <form
                            className="control-form"
                            onSubmit={(e) => {
                                e.preventDefault();
                                form.post(base + '/pending', {
                                    onSuccess: () => {
                                        form.reset();
                                        setAdd(false);
                                    },
                                });
                            }}
                        >
                            <label>
                                O que falta?
                                <input
                                    required
                                    maxLength={180}
                                    value={form.data.title}
                                    onChange={(e) => form.setData('title', e.target.value)}
                                />
                            </label>
                            <label>
                                Tipo
                                <select value={form.data.kind} onChange={(e) => form.setData('kind', e.target.value)}>
                                    <option value="answer">Resposta</option>
                                    <option value="approval">Aprovação</option>
                                    <option value="document">Documento</option>
                                    <option value="payment">Pagamento</option>
                                </select>
                            </label>
                            <label>
                                Aguardando
                                <select value={form.data.awaiting} onChange={(e) => form.setData('awaiting', e.target.value)}>
                                    <option value="client">Cliente</option>
                                    <option value="team">Equipe</option>
                                </select>
                            </label>
                            <label>
                                Responsável pelo acompanhamento
                                <select value={form.data.owner_id} onChange={(e) => form.setData('owner_id', e.target.value)}>
                                    {users.map((u) => (
                                        <option key={u.id} value={u.id}>
                                            {u.name}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <label>
                                Prazo
                                <input type="date" value={form.data.due_date} onChange={(e) => form.setData('due_date', e.target.value)} />
                            </label>
                            <label>
                                Próximo contato
                                <input
                                    type="date"
                                    value={form.data.next_contact_at}
                                    onChange={(e) => form.setData('next_contact_at', e.target.value)}
                                />
                            </label>
                            <button className="button primary" disabled={form.processing}>
                                Registrar pendência
                            </button>
                        </form>
                    )}
                    {!pending.length && (
                        <div className="control-empty">
                            <FileCheck2 size={28} />
                            <h3>Deixe cada combinado visível.</h3>
                            <p>Registre o que falta, quem acompanha e quando retomar a conversa.</p>
                        </div>
                    )}
                    {pending.map((p) => (
                        <article className={'control-pending-row ' + (p.status === 'resolved' ? 'is-resolved' : '')} key={p.id}>
                            <span className="control-status">
                                {p.status === 'resolved' ? 'Resolvida' : p.awaiting === 'client' ? 'Cliente' : 'Equipe'}
                            </span>
                            <div>
                                <strong>{p.title}</strong>
                                <small>
                                    {p.owner} · Prazo: {localDate(p.due_date)}
                                </small>
                                <small>Próximo contato: {localDate(p.next_contact_at)}</small>
                                {p.resolution && <p>{p.resolution}</p>}
                            </div>
                            <button
                                className="button"
                                onClick={() =>
                                    router.post(base + '/pending/' + p.id, {
                                        revision: p.revision,
                                        status: p.status === 'resolved' ? 'open' : 'resolved',
                                    })
                                }
                            >
                                {p.status === 'resolved' ? 'Reabrir' : 'Concluir'}
                            </button>
                        </article>
                    ))}
                </section>
            )}
            {tab === 'readiness' && (
                <section>
                    <div className="control-section-heading">
                        <div>
                            <span className="rd-eyebrow">EVIDÊNCIAS DA OPERAÇÃO</span>
                            <h2>
                                {readiness.filter((r) => r.ready).length} de {readiness.length} verificações atendidas.
                            </h2>
                            <p>
                                Esta lista confere registros do sistema. A liberação final do evento continua sendo uma decisão da equipe.
                            </p>
                        </div>
                    </div>
                    <div className="control-readiness">
                        {readiness.map((r) => (
                            <Link
                                key={r.key}
                                href={r.href}
                                className={'control-panel control-readiness-item ' + (r.ready ? 'is-ready' : '')}
                            >
                                <span className="control-check">{r.ready ? <CheckCircle2 size={21} /> : <Clock3 size={21} />}</span>
                                <div>
                                    <small>{r.ready ? 'Registro conferido' : 'A conferir'}</small>
                                    <h3>{r.title}</h3>
                                    <p>{r.detail}</p>
                                </div>
                                <ArrowUpRight size={17} />
                            </Link>
                        ))}
                    </div>
                </section>
            )}
            {tab === 'impact' && (
                <section className="control-panel">
                    <span className="rd-eyebrow">ANTES DE MUDAR</span>
                    <h2>Enxergue o efeito da decisão.</h2>
                    <p>Escolha o tipo de mudança para localizar os registros que merecem revisão.</p>
                    <div className="control-actions">
                        <select
                            aria-label="Tipo de mudança"
                            value={change}
                            onChange={(e) => {
                                setChange(e.target.value);
                                setImpact(null);
                            }}
                        >
                            <option value="date">Data do evento</option>
                            <option value="audience">Público</option>
                            <option value="scope">Escopo</option>
                            <option value="location">Local</option>
                        </select>
                        <button disabled={loading} className="button primary" onClick={previewImpact}>
                            {loading ? 'Conferindo…' : 'Conferir impacto'}
                        </button>
                    </div>
                    {impact && (
                        <>
                            <p className="control-note">{impact.note}</p>
                            <div className="control-impact-grid">
                                {impact.groups.map((g) => (
                                    <Link className="control-impact-card" key={g.title} href={g.href}>
                                        <strong>{g.count}</strong>
                                        <h3>{g.title}</h3>
                                        <p>{g.detail}</p>
                                        <ArrowUpRight size={16} />
                                    </Link>
                                ))}
                            </div>
                            <Link href={'/opportunities/' + o.id + '/edit'} className="button">
                                Abrir dados do projeto <ArrowRight size={16} />
                            </Link>
                        </>
                    )}
                </section>
            )}
            {tab === 'versions' && (
                <section className="control-panel">
                    <span className="rd-eyebrow">CONTEXTO PRESERVADO</span>
                    <h2>O que mudou entre as versões?</h2>
                    <div className="control-version-select">
                        <label>
                            Documento
                            <select
                                value={kind}
                                onChange={(e) => {
                                    setKind(e.target.value);
                                    setBefore('');
                                    setAfter('');
                                }}
                            >
                                <option value="briefing">Briefing</option>
                                <option value="budget">Orçamento</option>
                                <option value="proposal">Proposta</option>
                                <option value="contract">Contrato</option>
                            </select>
                        </label>
                        <label>
                            Antes
                            <select value={old?.id || ''} onChange={(e) => setBefore(e.target.value)}>
                                {available.map((v) => (
                                    <option key={v.id} value={v.id}>
                                        {v.label}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label>
                            Depois
                            <select value={latest?.id || ''} onChange={(e) => setAfter(e.target.value)}>
                                {available.map((v) => (
                                    <option key={v.id} value={v.id}>
                                        {v.label}
                                    </option>
                                ))}
                            </select>
                        </label>
                    </div>
                    {available.length < 2 ? (
                        <div className="control-empty">
                            <Layers3 size={26} />
                            <p>A comparação fica disponível quando houver duas versões registradas deste documento.</p>
                        </div>
                    ) : (
                        <>
                            <p>{diff.length} campos diferentes. Valores monetários identificados por “cents” estão em centavos.</p>
                            <div className="control-diff">
                                {diff.map((d) => (
                                    <article key={d.key}>
                                        <h3>
                                            {labels[d.key] || d.key.replaceAll('_', ' ')} <small>{d.type}</small>
                                        </h3>
                                        <div>
                                            <section>
                                                <span>ANTES</span>
                                                <pre>{presentValue(d.before)}</pre>
                                            </section>
                                            <section>
                                                <span>DEPOIS</span>
                                                <pre>{presentValue(d.after)}</pre>
                                            </section>
                                        </div>
                                    </article>
                                ))}
                            </div>
                        </>
                    )}
                </section>
            )}
            {tab === 'portal' && (
                <section className="control-panel">
                    <span className="rd-eyebrow">UMA JANELA PARA O CLIENTE</span>
                    <h2>Compartilhe só o que está pronto.</h2>
                    <p>
                        Monte a publicação, confira a prévia e gere um link com validade. Cada publicação preserva o conteúdo escolhido;
                        atualizações internas não alteram o que já foi publicado.
                    </p>
                    {portalUrl && (
                        <div className="control-preview">
                            <strong>Seu portal foi publicado</strong>
                            <p>Guarde este link. Ele só aparece após a publicação.</p>
                            <a href={portalUrl} target="_blank" rel="noreferrer" className="control-portal-url">
                                {portalUrl}
                            </a>
                        </div>
                    )}
                    <form
                        className="control-form"
                        onSubmit={(e) => {
                            e.preventDefault();
                            if (!canPublish) return;
                            setPortalPreview(true);
                        }}
                    >
                        <label className="control-full">
                            Mensagem ao cliente
                            <textarea
                                required
                                rows={3}
                                maxLength={3000}
                                value={portal.data.message}
                                onChange={(e) => {
                                    portal.setData('message', e.target.value);
                                    setPortalPreview(false);
                                }}
                            />
                        </label>
                        <label className="control-full">
                            Etapas e situação atual — uma por linha
                            <textarea
                                required
                                rows={4}
                                maxLength={3000}
                                placeholder={'Briefing recebido e em conferência\nProposta em preparação'}
                                value={portal.data.milestones}
                                onChange={(e) => {
                                    portal.setData('milestones', e.target.value);
                                    setPortalPreview(false);
                                }}
                            />
                        </label>
                        <label className="control-full">
                            Entregas para aprovação — uma por linha
                            <textarea
                                rows={3}
                                placeholder="Descreva exatamente a entrega que será revisada"
                                value={deliveries}
                                onChange={(e) => {
                                    setDeliveries(e.target.value);
                                    portal.setData(
                                        'deliverables',
                                        e.target.value
                                            .split('\n')
                                            .map((s) => s.trim())
                                            .filter(Boolean),
                                    );
                                    setPortalPreview(false);
                                }}
                            />
                        </label>
                        <fieldset className="control-full">
                            <legend>Propostas enviadas para consulta</legend>
                            {!documents.length && <p>Nenhuma proposta enviada e liberada para compartilhar.</p>}
                            {documents.map((d) => (
                                <label className="control-checkbox" key={d.id}>
                                    <input
                                        type="checkbox"
                                        checked={portal.data.document_ids.includes(d.id)}
                                        onChange={(e) => {
                                            portal.setData(
                                                'document_ids',
                                                e.target.checked
                                                    ? [...portal.data.document_ids, d.id]
                                                    : portal.data.document_ids.filter((id) => id !== d.id),
                                            );
                                            setPortalPreview(false);
                                        }}
                                    />
                                    {d.title} · V{d.version}
                                    <Link href={'/opportunities/' + o.id + '/documents'}>Conferir versão</Link>
                                </label>
                            ))}
                        </fieldset>
                        <label>
                            Validade
                            <select
                                value={portal.data.expires_days}
                                onChange={(e) => {
                                    portal.setData('expires_days', Number(e.target.value));
                                    setPortalPreview(false);
                                }}
                            >
                                <option value={7}>7 dias</option>
                                <option value={14}>14 dias</option>
                                <option value={30}>30 dias</option>
                            </select>
                        </label>
                        <div className="control-actions">
                            <button className="button" type="submit" disabled={!canPublish}>
                                <Globe2 size={16} /> Conferir prévia
                            </button>
                        </div>
                    </form>
                    {portalPreview && (
                        <div className="control-preview">
                            <span className="rd-eyebrow">CONTEÚDO QUE SERÁ PUBLICADO</span>
                            <h3>{o.title}</h3>
                            <p className="control-prewrap">{portal.data.message}</p>
                            <ol>
                                {portal.data.milestones
                                    .split('\n')
                                    .filter(Boolean)
                                    .map((m, i) => (
                                        <li key={i}>{m}</li>
                                    ))}
                            </ol>
                            {portal.data.deliverables.map((d, i) => (
                                <p key={i}>
                                    <FileCheck2 size={15} /> Para revisão: {d}
                                </p>
                            ))}
                            <p>
                                {portal.data.document_ids.length} propostas selecionadas serão incluídas com as seções e o valor da versão
                                liberada. Validade de {portal.data.expires_days} dias.
                            </p>
                            <p>
                                <LockKeyhole size={14} /> Custos, margens e notas internas ficam fora desta publicação. Qualquer pessoa com
                                o link poderá consultar este conteúdo.
                            </p>
                            <button
                                className="button primary"
                                disabled={portal.processing}
                                onClick={() => portal.post(base + '/portal', { onSuccess: () => setPortalPreview(false) })}
                            >
                                Confirmar e publicar portal
                            </button>
                        </div>
                    )}
                    {!canPublish && (
                        <p className="control-note">
                            A publicação e os arquivos são gerenciados pelo responsável do projeto ou por um administrador.
                        </p>
                    )}
                    <h3>Publicações</h3>
                    {portalLinks.map((p) => (
                        <div className="control-history-row" key={p.id}>
                            <div>
                                <strong>Publicação #{p.id}</strong>
                                <small>{p.revoked_at ? 'Revogada' : 'Validade: ' + localDate(p.expires_at)}</small>
                            </div>
                            {!p.revoked_at && canPublish && (
                                <button className="button" onClick={() => router.post(base + '/portal/' + p.id + '/revoke')}>
                                    Revogar link
                                </button>
                            )}
                        </div>
                    ))}
                    <h3>Retornos do cliente</h3>
                    {!portalResponses.length && <p>As respostas às entregas aparecerão aqui.</p>}
                    {portalResponses.map((r) => (
                        <article className="control-history-row" key={r.id}>
                            <div>
                                <strong>
                                    {r.name} · {r.decision === 'approved' ? 'Entrega aprovada' : 'Ajustes solicitados'}
                                </strong>
                                <small>
                                    Publicação #{r.client_portal_link_id} · {r.item_key} · {localDate(r.created_at)}
                                </small>
                                <p>{r.message}</p>
                            </div>
                        </article>
                    ))}
                    <h3>Materiais recebidos</h3>
                    {!portalUploads.length && <p>Os arquivos enviados pelo cliente ficarão disponíveis aqui.</p>}
                    {portalUploads.map((u) => (
                        <a className="control-history-row" key={u.id} href={canPublish ? base + '/uploads/' + u.id : undefined}>
                            <span>
                                {u.original_name}
                                <small>
                                    {u.name} · {localDate(u.created_at)}
                                </small>
                            </span>
                            <Download size={17} />
                        </a>
                    ))}
                </section>
            )}
        </AppLayout>
    );
}
