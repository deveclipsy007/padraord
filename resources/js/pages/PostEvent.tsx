import { Head, useForm } from '@inertiajs/react';
import { BookOpen, Check, CircleAlert, RefreshCw, Sparkles } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { AppLayout } from '../layout';
import { GlassSurface } from '../components/GlassSurface';
import { CasePageHeader } from '../components/CasePageHeader';

type Occurrence = { description: string; solution?: string | null; extraCents?: number | null; at?: string | null };
type ClosureItem = {
    title: string;
    status: 'pending' | 'assigned' | 'resolved';
    assigned_to?: number | null;
    due_at?: string | null;
    justification?: string | null;
};
type SupplierEvaluation = { supplier: string; rating: number; notes?: string | null };

type Props = {
    opportunity: { id: number; title: string; clientName: string };
    report: {
        summary?: string | null;
        learnings?: string | null;
        occurrences?: Occurrence[];
        actualTotalCents?: number | null;
        plannedTotalCents?: number | null;
        supplierEvaluations?: SupplierEvaluation[];
        closureItems?: ClosureItem[];
        status: string;
        closedAt?: string | null;
    };
};

const money = (cents: number | null | undefined) => `R$ ${((cents || 0) / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;

export default function PostEvent({ opportunity, report }: Props) {
    const [reopenReason, setReopenReason] = useState('');
    const {
        data,
        setData,
        post,
        processing,
        errors,
        transform: formTransform,
    } = useForm({
        summary: report.summary || '',
        learnings: report.learnings || '',
        planned_total: report.plannedTotalCents ? (report.plannedTotalCents / 100).toFixed(2).replace('.', ',') : '',
        actual_total: report.actualTotalCents ? (report.actualTotalCents / 100).toFixed(2).replace('.', ',') : '',
        occurrence_description: '',
        occurrence_solution: '',
        occurrence_extra_total: '',
        supplier_evaluations: report.supplierEvaluations || [],
        evaluation_supplier: '',
        evaluation_rating: '5',
        evaluation_notes: '',
        closure_items: report.closureItems || [],
        closure_title: '',
        closure_status: 'pending',
        closure_due_at: '',
        closure_justification: '',
        status: report.status,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        const closureItems = [...data.closure_items];
        if (data.closure_title.trim()) {
            closureItems.push({
                title: data.closure_title.trim(),
                status: data.closure_status as ClosureItem['status'],
                due_at: data.closure_due_at || null,
                justification: data.closure_justification || null,
            });
        }
        const evaluations = [...data.supplier_evaluations];
        if (data.evaluation_supplier.trim()) {
            evaluations.push({
                supplier: data.evaluation_supplier.trim(),
                rating: Number(data.evaluation_rating),
                notes: data.evaluation_notes || null,
            });
        }
        formTransform((current) => ({ ...current, closure_items: closureItems, supplier_evaluations: evaluations }));
        post(`/opportunities/${opportunity.id}/post-event`, { preserveScroll: true });
    }

    function reopen(event: FormEvent) {
        event.preventDefault();
        formTransform((current) => ({ ...current, reason: reopenReason }));
        post(`/opportunities/${opportunity.id}/post-event/reopen`, { preserveScroll: true, onSuccess: () => setReopenReason('') });
    }

    return (
        <AppLayout>
            <Head title={`Pós-evento · ${opportunity.title}`} />
            <CasePageHeader
                id={opportunity.id}
                eyebrow="Memória do evento"
                title="Preservar o aprendizado"
                client={opportunity.clientName}
                status={report.status === 'closed' ? 'Encerrado' : 'Em revisão'}
                actions={
                    <span className="status-pill green">
                        <BookOpen size={12} /> {report.status === 'closed' ? 'Ciclo encerrado' : 'Memória revisável'}
                    </span>
                }
            />
            <section className="post-event-grid">
                <GlassSurface className="post-event-hero">
                    <Sparkles size={24} />
                    <span className="eyebrow">SÍNTESE ASSISTIDA</span>
                    <h2>O que acontece aqui melhora o próximo evento.</h2>
                    <p>
                        A IA pode ajudar a resumir ocorrências e aprendizados, mas a memória só entra no sistema depois da revisão da
                        equipe.
                    </p>
                    <span className="status-pill violet">{report.status === 'closed' ? 'Memória encerrada' : 'Rascunho revisável'}</span>
                    {report.closedAt && <small>Encerrado em {new Date(report.closedAt).toLocaleString('pt-BR')}</small>}
                    {report.status === 'closed' && (
                        <form className="inline-form" onSubmit={reopen}>
                            <input
                                required
                                value={reopenReason}
                                onChange={(event) => setReopenReason(event.target.value)}
                                placeholder="Por que reabrir?"
                            />
                            <button className="button button-subtle" type="submit" disabled={processing}>
                                <RefreshCw size={14} /> Reabrir para revisão
                            </button>
                        </form>
                    )}
                </GlassSurface>
                <GlassSurface>
                    <div className="panel-heading">
                        <div>
                            <span className="eyebrow">REGISTRO FINAL</span>
                            <h2>Fechar ciclo</h2>
                        </div>
                        <Check size={17} className="muted-icon" />
                    </div>
                    <form className="form-grid compact-form" onSubmit={submit}>
                        <label>
                            Resumo do evento
                            <textarea
                                rows={5}
                                disabled={report.status === 'closed'}
                                value={data.summary}
                                onChange={(event) => setData('summary', event.target.value)}
                                placeholder="O que aconteceu?"
                            />
                        </label>
                        <label>
                            Aprendizados reutilizáveis
                            <textarea
                                rows={5}
                                disabled={report.status === 'closed'}
                                value={data.learnings}
                                onChange={(event) => setData('learnings', event.target.value)}
                                placeholder="O que devemos repetir ou evitar?"
                            />
                        </label>
                        <div className="two-fields">
                            <label>
                                Previsto (R$)
                                <input
                                    disabled={report.status === 'closed'}
                                    inputMode="decimal"
                                    value={data.planned_total}
                                    onChange={(event) => setData('planned_total', event.target.value)}
                                    placeholder="48.500,00"
                                />
                            </label>
                            <label>
                                Realizado (R$)
                                <input
                                    disabled={report.status === 'closed'}
                                    inputMode="decimal"
                                    value={data.actual_total}
                                    onChange={(event) => setData('actual_total', event.target.value)}
                                    placeholder="48.500,00"
                                />
                            </label>
                        </div>
                        <label>
                            Status
                            <select
                                disabled={report.status === 'closed'}
                                value={data.status}
                                onChange={(event) => setData('status', event.target.value)}
                            >
                                <option value="draft">Rascunho</option>
                                <option value="review">Em revisão</option>
                                <option value="closed">Encerrar evento</option>
                            </select>
                        </label>
                        <div className="post-event-occurrence">
                            <span className="eyebrow">NOVA OCORRÊNCIA</span>
                            <label>
                                O que aconteceu?
                                <input
                                    disabled={report.status === 'closed'}
                                    value={data.occurrence_description}
                                    onChange={(event) => setData('occurrence_description', event.target.value)}
                                    placeholder="Ex.: fornecedor chegou atrasado"
                                />
                            </label>
                            <label>
                                Solução / decisão
                                <input
                                    disabled={report.status === 'closed'}
                                    value={data.occurrence_solution}
                                    onChange={(event) => setData('occurrence_solution', event.target.value)}
                                    placeholder="Como a equipe resolveu"
                                />
                            </label>
                            <label>
                                Extra (R$)
                                <input
                                    disabled={report.status === 'closed'}
                                    inputMode="decimal"
                                    value={data.occurrence_extra_total}
                                    onChange={(event) => setData('occurrence_extra_total', event.target.value)}
                                    placeholder="0,00"
                                />
                            </label>
                        </div>
                        <div className="post-event-occurrence">
                            <span className="eyebrow">PENDÊNCIA FINAL (OPCIONAL)</span>
                            <label>
                                O que falta?
                                <input
                                    disabled={report.status === 'closed'}
                                    value={data.closure_title}
                                    onChange={(event) => setData('closure_title', event.target.value)}
                                    placeholder="Ex.: anexar comprovante"
                                />
                            </label>
                            <div className="two-fields">
                                <label>
                                    Situação
                                    <select
                                        disabled={report.status === 'closed'}
                                        value={data.closure_status}
                                        onChange={(event) => setData('closure_status', event.target.value)}
                                    >
                                        <option value="pending">Pendente</option>
                                        <option value="assigned">Atribuída</option>
                                        <option value="resolved">Resolvida</option>
                                    </select>
                                </label>
                                <label>
                                    Prazo
                                    <input
                                        disabled={report.status === 'closed'}
                                        type="date"
                                        value={data.closure_due_at}
                                        onChange={(event) => setData('closure_due_at', event.target.value)}
                                    />
                                </label>
                            </div>
                            <label>
                                Justificativa / encaminhamento
                                <textarea
                                    disabled={report.status === 'closed'}
                                    rows={2}
                                    value={data.closure_justification}
                                    onChange={(event) => setData('closure_justification', event.target.value)}
                                />
                            </label>
                        </div>
                        <div className="post-event-occurrence">
                            <span className="eyebrow">AVALIAÇÃO DE FORNECEDOR (OPCIONAL)</span>
                            <div className="two-fields">
                                <label>
                                    Fornecedor
                                    <input
                                        disabled={report.status === 'closed'}
                                        value={data.evaluation_supplier}
                                        onChange={(event) => setData('evaluation_supplier', event.target.value)}
                                    />
                                </label>
                                <label>
                                    Nota
                                    <select
                                        disabled={report.status === 'closed'}
                                        value={data.evaluation_rating}
                                        onChange={(event) => setData('evaluation_rating', event.target.value)}
                                    >
                                        {[1, 2, 3, 4, 5].map((rating) => (
                                            <option key={rating} value={rating}>
                                                {rating} / 5
                                            </option>
                                        ))}
                                    </select>
                                </label>
                            </div>
                            <label>
                                Observação
                                <textarea
                                    disabled={report.status === 'closed'}
                                    rows={2}
                                    value={data.evaluation_notes}
                                    onChange={(event) => setData('evaluation_notes', event.target.value)}
                                />
                            </label>
                        </div>
                        {errors.status && (
                            <div className="form-error">
                                <CircleAlert size={14} /> {errors.status}
                            </div>
                        )}
                        <button className="button button-primary" type="submit" disabled={processing || report.status === 'closed'}>
                            <Check size={15} /> {processing ? 'Salvando…' : 'Salvar memória'}
                        </button>
                    </form>
                </GlassSurface>
            </section>
            <GlassSurface className="post-event-occurrences">
                <div className="panel-heading">
                    <div>
                        <span className="eyebrow">REGISTRO OPERACIONAL</span>
                        <h2>Ocorrências e decisões</h2>
                    </div>
                    <CircleAlert size={17} className="muted-icon" />
                </div>
                {report.occurrences?.length ? (
                    report.occurrences.map((occurrence, index) => (
                        <article className="prototype-message" key={`${occurrence.at || index}-${index}`}>
                            <strong>{occurrence.description}</strong>
                            <p>
                                {occurrence.solution || 'Solução não registrada'} · Extra {money(occurrence.extraCents)}
                            </p>
                        </article>
                    ))
                ) : (
                    <p className="muted-copy">Nenhuma ocorrência registrada ainda.</p>
                )}
                {report.closureItems?.length ? (
                    <>
                        <h3>Pendências de encerramento</h3>
                        {report.closureItems.map((item, index) => (
                            <article className="prototype-message" key={`${item.title}-${index}`}>
                                <strong>{item.title}</strong>
                                <p>
                                    {item.status === 'resolved'
                                        ? 'Resolvida'
                                        : item.status === 'assigned'
                                          ? `Atribuída · prazo ${item.due_at || 'não definido'}`
                                          : 'Pendente'}
                                </p>
                                {item.justification && <small>{item.justification}</small>}
                            </article>
                        ))}
                    </>
                ) : null}
                {report.supplierEvaluations?.length ? (
                    <>
                        <h3>Avaliações de fornecedores</h3>
                        {report.supplierEvaluations.map((evaluation, index) => (
                            <article className="prototype-message" key={`${evaluation.supplier}-${index}`}>
                                <strong>
                                    {evaluation.supplier} · {evaluation.rating}/5
                                </strong>
                                <p>{evaluation.notes || 'Sem observação'}</p>
                            </article>
                        ))}
                    </>
                ) : null}
            </GlassSurface>
        </AppLayout>
    );
}
