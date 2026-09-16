import { EventBriefEditor, type EventBriefEditorProps } from '../components/EventBriefEditor';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { AppLayout } from '../layout';
import { aiLabels } from './AiSettings';
import { AudioContext, AudioContextData } from '../components/AudioContext';
import { CasePageHeader } from '../components/CasePageHeader';
import { FileAudio, ListChecks, Sparkles, MessageSquareText, ArrowUpRight } from 'lucide-react';

const labels: Record<string, string> = {
    objective: 'Objetivo',
    audience: 'Público',
    event_date: 'Data',
    location: 'Local',
    budget: 'Investimento disponível',
    scope: 'Escopo',
    restrictions: 'Restrições',
    references: 'Referências',
};
type Run = {
    id: number;
    status: string;
    mode: string;
    sourceId: number;
    error: string | null;
    decisions: Record<string, unknown>;
    payload: {
        summary?: string;
        facts?: { key: string; value: string; evidence: string; kind?: string }[];
        risks?: string[];
        suggested_changes?: { field: string; current: string | null; suggested: string; reason: string }[];
    } | null;
};
type Props = Omit<EventBriefEditorProps, 'caseId'> & {
    audio: AudioContextData;
    opportunity: { id: number; title: string; clientName: string; briefingStatus: string };
    messages: { id: number; body: string; role: string; createdAt: string }[];
    briefing: { fields: Record<string, string>; revision: number; approved: boolean; gaps: { field: string; question: string }[] };
    runs: Run[];
};

export default function Briefing(props: Props) {
    const { opportunity, messages, briefing, runs, audio } = props;
    const base = `/opportunities/${opportunity.id}/briefing`;
    const { ai } = usePage<{ ai: { mode: string; status: string } }>().props;
    const entry = useForm({ body: '' });
    const [reviewErrors, setReviewErrors] = useState<string[]>([]);
    const [reviewing, setReviewing] = useState(false);
    const sectionKey = `rd-briefing-section-${opportunity.id}`;
    const sectionFromHash = () => {
        const hash = window.location.hash;
        if (hash === '#briefing-estruturado') return 'details';
        if (hash === '#briefing-revisao' || hash.startsWith('#message-')) return 'review';
        if (hash === '#audio' || hash === '#inicio') return 'capture';
        try {
            const saved = window.sessionStorage.getItem(sectionKey);
            if (saved === 'details' || saved === 'review') return saved;
        } catch {
            /* Navigation remains available when storage is disabled. */
        }
        return 'capture';
    };
    const [section, setSection] = useState(sectionFromHash);
    useEffect(() => {
        const sync = () => {
            setSection(sectionFromHash());
            requestAnimationFrame(() => {
                const target = document.getElementById(window.location.hash.slice(1));
                if (target?.id.startsWith('message-')) target.closest('details')?.setAttribute('open', '');
                target?.scrollIntoView({ block: 'start' });
            });
        };
        window.addEventListener('hashchange', sync);
        return () => window.removeEventListener('hashchange', sync);
    }, [sectionKey]);
    useEffect(() => {
        try {
            window.sessionStorage.setItem(sectionKey, section);
        } catch {
            /* Optional session preference. */
        }
    }, [section, sectionKey]);
    function navigateSection(value: string) {
        setSection(value);
        window.history.replaceState(
            window.history.state,
            '',
            value === 'details' ? '#briefing-estruturado' : value === 'review' ? '#briefing-revisao' : '#inicio',
        );
    }
    useEffect(() => {
        if (opportunity.briefingStatus !== 'processing') return;
        const timer = window.setInterval(() => router.reload({ only: ['opportunity', 'runs', 'messages', 'briefing'] }), 4000);
        return () => window.clearInterval(timer);
    }, [opportunity.briefingStatus]);
    const latest = runs.find((r) => r.status === 'success');
    function decide(action: string, run?: Run, indices?: number[]) {
        setReviewErrors([]);
        setReviewing(true);
        router.post(
            `${base}/review`,
            { action, revision: briefing.revision, run_id: run?.id, indices },
            { preserveScroll: true, onError: (e) => setReviewErrors(Object.values(e)), onFinish: () => setReviewing(false) },
        );
    }
    return (
        <AppLayout>
            <Head title={`Briefing · ${opportunity.title}`} />
            <CasePageHeader
                id={opportunity.id}
                eyebrow="Contexto conectado"
                title="Entender antes de produzir"
                client={opportunity.clientName}
                status={aiLabels[ai.status] ?? 'Operação manual'}
                actions={
                    <div className="topbar-actions">
                        <a className="button button-subtle" href="#audio">
                            <FileAudio size={15} /> Enviar áudio
                        </a>
                        <a className="button button-subtle" href="#briefing-estruturado">
                            <ListChecks size={15} /> Ver entendimento
                        </a>
                    </div>
                }
            />
            <div className="briefing-workspace">
                <section className="briefing-intro">
                    <div>
                        <span className="eyebrow">DA CONVERSA À DIREÇÃO</span>
                        <h2>
                            Uma boa ideia.
                            <br />
                            <em>Um briefing à altura.</em>
                        </h2>
                        <p>
                            Conte o que imagina. Reúna as informações, confira o entendimento e transforme a conversa em um plano claro para
                            o evento.
                        </p>
                        <button
                            className="button button-primary"
                            onClick={() => {
                                navigateSection('capture');
                                requestAnimationFrame(() =>
                                    document.getElementById('briefing-entrada')?.scrollIntoView({ block: 'start' }),
                                );
                            }}
                        >
                            <Sparkles size={16} /> Criar briefing inteligente
                        </button>
                        <small>
                            {ai.mode === 'manual'
                                ? 'Modo manual: você organiza e confirma as informações.'
                                : 'O agente sugere. Você revisa e confirma cada decisão.'}
                        </small>
                    </div>
                    <div className="briefing-intro__sheet" aria-label="Situação do briefing">
                        <ListChecks size={25} strokeWidth={1.4} />
                        <span>O seu ponto de partida</span>
                        <strong>
                            {props.eventBrief.completeness_score}
                            <small>%</small>
                        </strong>
                        <p>dos campos essenciais preenchidos</p>
                        <div className="briefing-intro__line">
                            <span style={{ width: `${props.eventBrief.completeness_score}%` }} />
                        </div>
                        <small>
                            {props.eventBrief.status === 'approved' ? 'Briefing aprovado' : 'Preenchimento não significa aprovação'}
                        </small>
                    </div>
                </section>
                <nav className="briefing-flow-nav" aria-label="Etapas do briefing">
                    {[
                        {
                            id: 'capture',
                            number: '01',
                            title: 'Reunir contexto',
                            text: 'Áudio, texto ou suas próprias anotações',
                            icon: FileAudio,
                        },
                        {
                            id: 'review',
                            number: '02',
                            title: 'Conferir sugestões',
                            text: 'Entendimento, fontes e perguntas',
                            icon: Sparkles,
                        },
                        {
                            id: 'details',
                            number: '03',
                            title: 'Completar e aprovar',
                            text: 'Dados, requisitos e programação',
                            icon: ListChecks,
                        },
                    ].map(({ id, number, title, text, icon: Icon }) => (
                        <button key={id} aria-current={section === id ? 'step' : undefined} onClick={() => navigateSection(id)}>
                            <span className="briefing-flow-nav__icon">
                                <Icon size={20} />
                            </span>
                            <span>
                                <small>
                                    {number} · {title}
                                </small>
                                <strong>{text}</strong>
                            </span>
                            <ArrowUpRight size={16} />
                        </button>
                    ))}
                </nav>
                <div id="briefing-entrada" hidden={section !== 'capture'}>
                    <header className="briefing-area-heading">
                        <div>
                            <span className="eyebrow">01 · REUNIR CONTEXTO</span>
                            <h2>Comece do seu jeito.</h2>
                        </div>
                        <p>Envie uma gravação, cole uma conversa ou vá direto aos dados do evento.</p>
                    </header>
                    <div className="briefing-capture-grid">
                        <div id="audio">
                            <AudioContext base={base} audio={audio} />
                        </div>
                        <section className="glass-surface assistance-panel briefing-text-entry">
                            <MessageSquareText size={24} strokeWidth={1.5} />
                            <h2>Adicionar contexto</h2>
                            <p>Cole a transcrição da reunião, uma conversa ou apenas uma atualização.</p>
                            <form
                                className="form-grid"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    entry.post(`${base}/messages`, { preserveScroll: true, onSuccess: () => entry.reset() });
                                }}
                            >
                                <label htmlFor="briefing-context-text">
                                    Texto ou transcrição
                                    <textarea
                                        id="briefing-context-text"
                                        aria-label="Texto ou transcrição"
                                        rows={6}
                                        maxLength={20000}
                                        value={entry.data.body}
                                        onChange={(e) => entry.setData('body', e.target.value)}
                                        placeholder="O cliente quer reunir a equipe…"
                                    />
                                </label>
                                {entry.errors.body && <p role="alert">{entry.errors.body}</p>}
                                <button className="button button-primary" disabled={entry.processing || entry.data.body.trim().length < 3}>
                                    {entry.processing ? 'Salvando…' : ai.mode === 'manual' ? 'Salvar contexto' : 'Organizar contexto'}
                                </button>
                            </form>
                            <p className="briefing-entry-hint">
                                O texto original fica preservado para consulta. Revise as sugestões antes de completar os dados oficiais do
                                evento.
                            </p>
                        </section>
                    </div>
                    <div className="briefing-next">
                        <div>
                            <strong>Prefere preencher diretamente?</strong>
                            <p>Organize datas, público, investimento e requisitos por etapa.</p>
                        </div>
                        <button className="button button-subtle" onClick={() => navigateSection('details')}>
                            Preencher dados do evento <ArrowUpRight size={16} />
                        </button>
                    </div>
                    <div className="briefing-next">
                        <div>
                            <strong>Já enviou o contexto?</strong>
                            <p>Confira o entendimento e as perguntas que ainda precisam de resposta.</p>
                        </div>
                        <button className="button button-primary" onClick={() => navigateSection('review')}>
                            Conferir sugestões <ArrowUpRight size={16} />
                        </button>
                    </div>
                </div>
                <div id="briefing-revisao" hidden={section !== 'review'}>
                    <header className="briefing-area-heading">
                        <div>
                            <span className="eyebrow">02 · CONFERIR SUGESTÕES</span>
                            <h2>Clareza antes de seguir.</h2>
                        </div>
                        <p>Sugestões são rascunhos. Confira as fontes e resolva as dúvidas antes de aprovar.</p>
                    </header>
                    {opportunity.briefingStatus === 'processing' && <p role="status">Organizando seu contexto…</p>}
                    <div className="assistance-grid">
                        <section className="glass-surface assistance-panel">
                            <h2>O que entendemos</h2>
                            <p>
                                {latest?.payload?.summary ??
                                    'Ainda não há uma síntese. Reúna o contexto na primeira etapa ou preencha diretamente os dados em Completar e aprovar.'}
                            </p>
                            {runs[0]?.error && (
                                <p className="assistance-error" role="status">
                                    {runs[0].error}
                                </p>
                            )}
                            {reviewErrors.map((e, i) => (
                                <p key={i} role="alert" className="assistance-error">
                                    {e}
                                </p>
                            ))}
                            {runs
                                .filter((r) => r.status === 'success')
                                .map((run) => {
                                    const pending = (run.payload?.suggested_changes ?? [])
                                        .map((c, i) => ({ ...c, index: i }))
                                        .filter((c) => !run.decisions[c.index]);
                                    return (
                                        <div key={run.id}>
                                            {pending.length > 0 && (
                                                <>
                                                    <p>
                                                        <strong>
                                                            {run.mode === 'demo' ? 'Demonstração' : 'Sugestões'} · fonte: mensagem #
                                                            {run.sourceId}
                                                        </strong>
                                                    </p>
                                                    {pending.map((c) => (
                                                        <article className="assistance-diff" key={c.index}>
                                                            <strong>{labels[c.field]}</strong>
                                                            <del>Atual: {briefing.fields[c.field] || 'Não informado'}</del>
                                                            <ins>Rascunho: {c.suggested}</ins>
                                                            <p>{c.reason}</p>
                                                            <div className="assistance-actions">
                                                                <button
                                                                    className="button button-subtle"
                                                                    disabled={reviewing}
                                                                    onClick={() => decide('accept', run, [c.index])}
                                                                >
                                                                    Usar rascunho
                                                                </button>
                                                                <button
                                                                    className="button button-subtle"
                                                                    disabled={reviewing}
                                                                    onClick={() => decide('reject', run, [c.index])}
                                                                >
                                                                    Descartar
                                                                </button>
                                                            </div>
                                                        </article>
                                                    ))}
                                                    {pending.length > 1 && (
                                                        <button
                                                            className="button button-subtle"
                                                            disabled={reviewing}
                                                            onClick={() =>
                                                                decide(
                                                                    'accept',
                                                                    run,
                                                                    pending.map((c) => c.index),
                                                                )
                                                            }
                                                        >
                                                            Usar {pending.length} sugestões no rascunho
                                                        </button>
                                                    )}
                                                </>
                                            )}
                                            {(run.payload?.facts?.length ?? 0) > 0 && (
                                                <details>
                                                    <summary>Fatos e evidências · mensagem #{run.sourceId}</summary>
                                                    {run.payload?.facts?.map((f, i) => (
                                                        <p key={i}>
                                                            <strong>
                                                                {f.kind === 'hypothesis'
                                                                    ? 'Hipótese a confirmar · '
                                                                    : f.kind === 'conflict'
                                                                      ? 'Conflito · '
                                                                      : ''}
                                                                {f.key}: {f.value}
                                                            </strong>
                                                            <br />
                                                            Fonte: {f.evidence}
                                                        </p>
                                                    ))}
                                                </details>
                                            )}
                                        </div>
                                    );
                                })}
                            <details>
                                <summary>Conversa original · {messages.filter((m) => m.role === 'user').length} mensagens</summary>
                                {messages
                                    .filter((m) => m.role === 'user')
                                    .map((m) => (
                                        <article className="assistance-record" id={`message-${m.id}`} key={m.id}>
                                            <strong>
                                                Mensagem #{m.id} · {m.createdAt}
                                            </strong>
                                            <p style={{ whiteSpace: 'pre-wrap' }}>{m.body}</p>
                                        </article>
                                    ))}
                            </details>
                        </section>
                        <aside className="glass-surface assistance-panel">
                            <h2>{briefing.gaps.length ? 'Próximas perguntas' : 'Confira os dados do evento'}</h2>
                            {briefing.gaps.slice(0, 3).map((g) => (
                                <p key={g.field}>{g.question}</p>
                            ))}
                            {briefing.gaps.length > 3 && (
                                <details>
                                    <summary>Ver outras {briefing.gaps.length - 3} pendências</summary>
                                    {briefing.gaps.slice(3).map((g) => (
                                        <p key={g.field}>{g.question}</p>
                                    ))}
                                </details>
                            )}
                            {latest?.payload?.risks?.map((risk, i) => (
                                <p key={i} className="assistance-error">
                                    {risk}
                                </p>
                            ))}
                            <a className="button button-subtle" href="#briefing-estruturado">
                                Revisar dados do evento
                            </a>
                            <p>
                                O briefing estruturado reúne datas, público, investimento, requisitos e origem. Aprove quando os campos
                                essenciais estiverem completos.
                            </p>
                        </aside>
                    </div>
                </div>
                <div hidden={section !== 'details'}>
                    <header className="briefing-area-heading">
                        <div>
                            <span className="eyebrow">03 · COMPLETAR E APROVAR</span>
                            <h2>Cada detalhe, no seu lugar.</h2>
                        </div>
                        <p>Preencha por assunto, salve suas alterações e aprove quando as informações estiverem confirmadas.</p>
                    </header>
                    <EventBriefEditor {...props} caseId={opportunity.id} />
                    {props.eventBrief.status === 'approved' && (
                        <Link className="button button-primary" href={`/opportunities/${opportunity.id}/budget`}>
                            Preparar orçamento <ArrowUpRight size={16} />
                        </Link>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
