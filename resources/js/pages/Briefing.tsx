import { EventBriefEditor, type EventBriefEditorProps } from '../components/EventBriefEditor';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { AppLayout } from '../layout';
import { aiLabels } from './AiSettings';
import { AssistanceSteps } from '../components/AssistanceSteps';
import { AudioContext, AudioContextData } from '../components/AudioContext';
import { CasePageHeader } from '../components/CasePageHeader';
import { FileAudio, ListChecks } from 'lucide-react';

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
            <EventBriefEditor {...props} caseId={opportunity.id} />
            <AssistanceSteps current={briefing.approved ? 4 : messages.length === 0 ? 0 : briefing.gaps.length ? 2 : 1} />
            <section className="assistance-banner">
                <div>
                    <strong>
                        {opportunity.briefingStatus === 'processing'
                            ? 'Organizando seu contexto…'
                            : briefing.approved
                              ? 'Briefing revisado. Vamos preparar a entrega?'
                              : latest
                                ? 'Confira o entendimento e resolva as lacunas.'
                                : 'Comece com o que você já sabe.'}
                    </strong>
                    <p>
                        Não precisa preencher tudo de novo. A mensagem original fica preservada e nenhuma sugestão aprova valores ou
                        fornecedores.
                    </p>
                </div>
                {briefing.approved && (
                    <Link className="button button-primary" href={`/opportunities/${opportunity.id}/budget`}>
                        Preparar orçamento
                    </Link>
                )}
            </section>
            <div className="assistance-grid">
                <section className="glass-surface assistance-panel">
                    <h2>Adicionar contexto</h2>
                    <p>Cole a transcrição da reunião, uma conversa ou apenas uma atualização.</p>
                    <form
                        className="form-grid"
                        onSubmit={(e) => {
                            e.preventDefault();
                            entry.post(`${base}/messages`, { preserveScroll: true, onSuccess: () => entry.reset() });
                        }}
                    >
                        <label>
                            Texto ou transcrição
                            <textarea
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
                    <div id="audio">
                        <AudioContext base={base} audio={audio} />
                    </div>
                    <h2>O que entendemos</h2>
                    <p>{latest?.payload?.summary ?? 'Ainda sem síntese. Você pode organizar o briefing manualmente abaixo.'}</p>
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
                                                    {run.mode === 'demo' ? 'Demonstração' : 'Sugestões'} · fonte: mensagem #{run.sourceId}
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
                    <h2>{briefing.gaps.length ? 'Próximas perguntas' : 'Pronto para revisão'}</h2>
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
                        O briefing estruturado reúne datas, público, investimento, requisitos e origem. Aprove quando os campos essenciais
                        estiverem completos.
                    </p>
                </aside>
            </div>
        </AppLayout>
    );
}
