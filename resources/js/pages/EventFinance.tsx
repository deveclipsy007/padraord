import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { AppLayout } from '../layout';
import { CasePageHeader } from '../components/CasePageHeader';
import { Drawer } from '../components/FormControls';

type Installment = {
    id?: number;
    sequence?: number;
    label: string;
    share_bps: number;
    amount_cents?: number;
    trigger: string;
    offset_days: number | null;
    due_at: string | null;
    milestone: string | null;
};
type Plan = {
    id: number;
    revision: number;
    status: string;
    total_cents: number;
    acceptance_evidence?: string;
    installments: Installment[];
};
type Entry = {
    id: number;
    label: string;
    amount_cents: number;
    paid_cents: number;
    balance_cents: number;
    due_at: string | null;
    overdue: boolean;
    status: string;
    revision: number;
    approved_at?: string | null;
    trigger?: string;
    milestone?: string | null;
    cancellation_reason?: string | null;
};
type Cost = {
    category: string;
    planned_cents: number;
    actual_cents: number;
    paid_cents: number;
    difference_cents: number;
    variance_reason: string | null;
    revision: number;
    reconciled: boolean;
    requires_reason: boolean;
};
type Profit = {
    contracted_revenue_cents: number;
    budget_revenue_cents: number;
    received_cents: number;
    planned_cost_cents: number;
    actual_cost_cents: number;
    planned_margin_cents: number;
    actual_margin_cents: number;
    final: boolean;
    categories: Cost[];
    closure_errors: string[];
};
type Props = {
    opportunity: { id: number; title: string; clientName: string };
    plan: Plan | null;
    receivables: Entry[];
    payables: Entry[];
    profitability: Profit;
    preview: { id: number; items: Entry[] } | null;
    canApprove: boolean;
    origins: { key: string; label: string }[];
    settlements: { id: number; ledger: string; entry_id: number; paid_at: string; amount_cents: number; evidence: string }[];
};
const money = (cents: number) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(cents / 100);
const triggers: Record<string, string> = {
    assinatura: 'Assinatura',
    dias_antes_evento: 'Dias antes do evento',
    dias_apos_evento: 'Dias após o evento',
    entrega: 'Entrega confirmada',
    data_fixa: 'Data fixa',
    marco: 'Marco confirmado',
};
const row = (): Installment => ({
    label: 'Parcela',
    share_bps: 10000,
    trigger: 'assinatura',
    offset_days: null,
    due_at: null,
    milestone: null,
});
function cents(value: string): number {
    if (!/^\d{1,9}(?:[.,]\d{1,2})?$/.test(value.trim())) return -1;
    const [a, b = ''] = value.replace(',', '.').split('.');
    return Number(a) * 100 + Number(b.padEnd(2, '0'));
}
function Errors({ errors }: { errors: object }) {
    const e = Object.values(errors).filter(Boolean);
    return e.length ? (
        <div role="alert" className="finance-errors">
            {e.map((v, i) => (
                <p key={i}>{String(v)}</p>
            ))}
        </div>
    ) : null;
}

export default function EventFinance({
    opportunity,
    plan,
    receivables,
    payables,
    profitability: p,
    preview,
    canApprove,
    origins,
    settlements,
}: Props) {
    const globalErrors = usePage().props.errors;
    const base = `/opportunities/${opportunity.id}/finance`;
    const [active, setActive] = useState<'plan' | 'payable' | 'settle' | 'cancel' | 'cost' | 'due' | null>(null);
    const [entry, setEntry] = useState<{ ledger: string; row: Entry } | null>(null);
    const draft = useForm({
        revision: plan?.revision ?? 0,
        total: plan ? String(plan.total_cents / 100) : '',
        installments: plan?.installments ?? [row()],
    });
    const accept = useForm({ revision: plan?.revision ?? 0, evidence: '' });
    const operation = useForm({
        revision: 0,
        amount: '',
        paid_at: new Date().toISOString().slice(0, 10),
        evidence: '',
        request_key: '',
        reason: '',
        due_at: '',
    });
    const payable = useForm({ origin: '', label: '', amount: '', due_at: '' });
    const cost = useForm({ revision: 0, category: '', actual: '', variance_reason: '' });
    useEffect(() => {
        accept.setData('revision', plan?.revision ?? 0);
    }, [plan?.revision]);
    const openEntry = (ledger: string, r: Entry, action: 'settle' | 'cancel' | 'due') => {
        setEntry({ ledger, row: r });
        operation.setData({
            revision: r.revision,
            amount: String(r.balance_cents / 100),
            paid_at: new Date().toISOString().slice(0, 10),
            evidence: '',
            request_key: crypto.randomUUID(),
            reason: '',
            due_at: r.due_at ?? '',
        });
        operation.clearErrors();
        setActive(action);
    };
    const openCost = (r?: Cost) => {
        cost.setData({
            revision: r?.revision ?? 0,
            category: r?.category ?? '',
            actual: r ? String(r.actual_cents / 100) : '',
            variance_reason: r?.variance_reason ?? '',
        });
        cost.clearErrors();
        setActive('cost');
    };
    function updateInstallment(i: number, key: keyof Installment, value: string | number | null) {
        draft.setData(
            'installments',
            draft.data.installments.map((r, n) => (n === i ? { ...r, [key]: value } : r)),
        );
    }
    const ledger = (title: string, key: string, rows: Entry[]) => (
        <section className="finance-section">
            <header>
                <div>
                    <span className="eyebrow">{key === 'receivables' ? 'ENTRADAS' : 'SAÍDAS'}</span>
                    <h2>{title}</h2>
                </div>
                {key === 'payables' && (
                    <button className="button button--primary" onClick={() => setActive('payable')}>
                        Novo pagável
                    </button>
                )}
            </header>
            {!rows.length ? (
                <p className="empty-state">
                    {key === 'receivables'
                        ? 'Aceite um plano e confirme a prévia para criar recebíveis.'
                        : 'Vincule despesas a um orçamento aprovado ou uma cotação selecionada.'}
                </p>
            ) : (
                <div className="finance-ledger">
                    {rows.map((r) => (
                        <article key={r.id}>
                            <div>
                                <h3>{r.label}</h3>
                                <p>
                                    {r.due_at
                                        ? `Vencimento ${r.due_at.split('-').reverse().join('/')}`
                                        : `Aguardando ${triggers[r.trigger ?? ''] ?? 'data'}${r.milestone ? `: ${r.milestone}` : ''}`}
                                </p>
                                <span className={r.overdue ? 'finance-overdue' : 'text-muted'}>
                                    {r.status === 'cancelled'
                                        ? 'Cancelado'
                                        : r.status === 'paid'
                                          ? 'Quitado'
                                          : r.overdue
                                            ? 'Em atraso'
                                            : r.paid_cents
                                              ? 'Baixa parcial'
                                              : 'Em aberto'}
                                </span>
                                {r.cancellation_reason && <p>{r.cancellation_reason}</p>}
                            </div>
                            <div className="finance-amount">
                                <strong>{money(r.balance_cents)}</strong>
                                <small>
                                    {money(r.paid_cents)} baixados · total {money(r.amount_cents)}
                                </small>
                            </div>
                            <div className="finance-actions">
                                {r.status === 'open' && (
                                    <>
                                        {key === 'payables' && !r.approved_at ? (
                                            <button
                                                className="button"
                                                disabled={!canApprove}
                                                onClick={() =>
                                                    router.post(
                                                        `${base}/payables/${r.id}/approve`,
                                                        { revision: r.revision },
                                                        { preserveScroll: true },
                                                    )
                                                }
                                            >
                                                Aprovar pagamento
                                            </button>
                                        ) : (
                                            <button className="button" onClick={() => openEntry(key, r, 'settle')}>
                                                Registrar baixa
                                            </button>
                                        )}
                                        {key === 'receivables' && !r.due_at && (
                                            <button className="button" onClick={() => openEntry(key, r, 'due')}>
                                                Confirmar vencimento
                                            </button>
                                        )}
                                    </>
                                )}
                                {r.status !== 'cancelled' && (
                                    <button className="button button--ghost" onClick={() => openEntry(key, r, 'cancel')}>
                                        Cancelar lançamento
                                    </button>
                                )}
                            </div>
                        </article>
                    ))}
                </div>
            )}
        </section>
    );
    return (
        <AppLayout>
            <Head title={`Financeiro · ${opportunity.title}`} />
            <CasePageHeader
                id={opportunity.id}
                title="Controle financeiro do evento"
                eyebrow="FINANCEIRO"
                client={opportunity.clientName}
                status={p.final ? 'Resultado final' : 'Resultado provisório'}
                actions={
                    <Link className="button" href={`/opportunities/${opportunity.id}/post-event`}>
                        Conferir pós-evento
                    </Link>
                }
            />
            <div className="finance-page">
                <Errors errors={globalErrors} />
                <section className="finance-metrics" aria-label="Resumo financeiro">
                    {[
                        ['Receita contratada', p.contracted_revenue_cents],
                        ['Custo previsto', p.planned_cost_cents],
                        ['Custo realizado', p.actual_cost_cents],
                        ['Margem realizada', p.actual_margin_cents],
                    ].map(([label, value]) => (
                        <article key={label}>
                            <span>{label}</span>
                            <strong>{money(Number(value))}</strong>
                            {label === 'Margem realizada' && (
                                <small>{p.final ? 'Conferida no encerramento' : 'Provisória · depende da conferência dos custos'}</small>
                            )}
                        </article>
                    ))}
                </section>
                <p className="finance-note">
                    Recebido: {money(p.received_cents)} · Margem prevista: {money(p.planned_margin_cents)}. Baixas registram comprovantes;
                    não movimentam contas bancárias.
                </p>
                <section className="finance-section">
                    <header>
                        <div>
                            <span className="eyebrow">CONDIÇÕES COMERCIAIS</span>
                            <h2>Plano de pagamento</h2>
                        </div>
                        {plan?.status !== 'accepted' && (
                            <button
                                className="button button--primary"
                                onClick={() => {
                                    draft.setData({
                                        revision: plan?.revision ?? 0,
                                        total: plan ? String(plan.total_cents / 100) : '',
                                        installments: plan?.installments ?? [row()],
                                    });
                                    setActive('plan');
                                }}
                            >
                                {plan ? 'Editar plano' : 'Criar plano'}
                            </button>
                        )}
                    </header>
                    {plan ? (
                        <>
                            <p>
                                <strong>{money(plan.total_cents)}</strong> · {plan.installments.length} parcelas · revisão {plan.revision} ·{' '}
                                {plan.status === 'accepted' ? 'Aceite registrado' : 'Aguardando aceite'}
                            </p>
                            <ol className="finance-plan">
                                {plan.installments.map((i, n) => (
                                    <li key={n}>
                                        <span>
                                            {i.label} · {i.share_bps / 100}%
                                        </span>
                                        <strong>{money(i.amount_cents ?? 0)}</strong>
                                        <small>
                                            {triggers[i.trigger]}
                                            {i.offset_days !== null ? ` · ${i.offset_days} dias` : ''} {i.due_at ?? i.milestone}
                                        </small>
                                    </li>
                                ))}
                            </ol>
                            {plan.status === 'accepted' ? (
                                <>
                                    <p className="finance-note">{plan.acceptance_evidence}</p>
                                    <button
                                        className="button"
                                        onClick={() => router.post(`${base}/plan/${plan.id}/preview`, {}, { preserveScroll: true })}
                                    >
                                        Preparar recebíveis
                                    </button>
                                </>
                            ) : (
                                <form
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        accept.post(`${base}/plan/${plan.id}/accept`, { preserveScroll: true });
                                    }}
                                >
                                    <label className="finance-field">
                                        Evidência do aceite do plano
                                        <textarea
                                            required
                                            minLength={3}
                                            value={accept.data.evidence}
                                            onChange={(e) => accept.setData('evidence', e.target.value)}
                                        />
                                    </label>
                                    <Errors errors={accept.errors} />
                                    <button className="button" disabled={!canApprove || accept.processing}>
                                        Registrar aceite e preservar condições
                                    </button>
                                    {!canApprove && <p>A autoridade comercial deve registrar o aceite.</p>}
                                </form>
                            )}
                        </>
                    ) : (
                        <p className="empty-state">
                            Defina parcelas e gatilhos para transformar as condições da proposta em valores rastreáveis.
                        </p>
                    )}
                    {preview && (
                        <aside className="finance-preview">
                            <h3>Confira antes de gerar recebíveis</h3>
                            {preview.items.map((i, n) => (
                                <p key={n}>
                                    {i.label} · {money(i.amount_cents)} · {i.due_at ?? 'Vencimento ainda depende do gatilho'}
                                </p>
                            ))}
                            <button
                                className="button button--primary"
                                onClick={() => router.post(`${base}/preview/${preview.id}/confirm`, {}, { preserveScroll: true })}
                            >
                                Confirmar geração dos recebíveis
                            </button>
                        </aside>
                    )}
                </section>
                {ledger('Contas a receber', 'receivables', receivables)}
                {ledger('Contas a pagar', 'payables', payables)}
                <section className="finance-section">
                    <header>
                        <div>
                            <span className="eyebrow">PREVISTO × REALIZADO</span>
                            <h2>Conferência por categoria</h2>
                        </div>
                        <button className="button" onClick={() => openCost()}>
                            Conferir outra categoria
                        </button>
                    </header>
                    <p>
                        Desvios acima de 10% precisam de justificativa antes do encerramento. Custos pagos e registros conferidos são
                        preservados.
                    </p>
                    {p.categories.map((r) => (
                        <article className="finance-cost" key={r.category}>
                            <div>
                                <h3>{r.category}</h3>
                                <p>
                                    {money(r.planned_cents)} previstos → {money(r.actual_cents)} realizados
                                </p>
                                <span>
                                    {r.reconciled ? 'Conferido' : 'Aguardando conferência'} · diferença {money(r.difference_cents)}
                                </span>
                                {r.variance_reason && <p>{r.variance_reason}</p>}
                            </div>
                            <button className="button" onClick={() => openCost(r)}>
                                Conferir {r.category}
                            </button>
                        </article>
                    ))}
                    {!!p.closure_errors.length && (
                        <aside className="finance-preview">
                            <h3>Antes de encerrar</h3>
                            {p.closure_errors.map((e) => (
                                <p key={e}>{e}</p>
                            ))}
                        </aside>
                    )}
                </section>
                {!!settlements.length && (
                    <details className="finance-section">
                        <summary>Histórico de baixas · {settlements.length}</summary>
                        {settlements.map((s) => (
                            <p key={s.id}>
                                {s.paid_at} · {s.ledger === 'receivables' ? 'Recebimento' : 'Pagamento'} #{s.entry_id} ·{' '}
                                {money(s.amount_cents)} · {s.evidence}
                            </p>
                        ))}
                    </details>
                )}
            </div>
            <Drawer open={active === 'plan'} title="Plano de pagamento" onClose={() => setActive(null)}>
                <form
                    className="finance-form"
                    onSubmit={(e) => {
                        e.preventDefault();
                        draft.transform((d) => ({ revision: d.revision, total_cents: cents(d.total), installments: d.installments }));
                        draft.post(`${base}/plan`, { preserveScroll: true, onSuccess: () => setActive(null) });
                    }}
                >
                    <label>
                        Total do plano (R$)
                        <input
                            value={draft.data.total}
                            onChange={(e) => draft.setData('total', e.target.value)}
                            inputMode="decimal"
                            required
                        />
                    </label>
                    {draft.data.installments.map((i, n) => (
                        <fieldset key={n}>
                            <legend>Parcela {n + 1}</legend>
                            <label>
                                Nome da parcela
                                <input value={i.label} onChange={(e) => updateInstallment(n, 'label', e.target.value)} required />
                            </label>
                            <label>
                                Porcentagem
                                <input
                                    type="number"
                                    min="0.01"
                                    max="100"
                                    step="0.01"
                                    value={i.share_bps / 100}
                                    onChange={(e) => updateInstallment(n, 'share_bps', Math.round(Number(e.target.value) * 100))}
                                />
                            </label>
                            <label>
                                Gatilho do vencimento
                                <select value={i.trigger} onChange={(e) => updateInstallment(n, 'trigger', e.target.value)}>
                                    {Object.entries(triggers).map(([k, v]) => (
                                        <option key={k} value={k}>
                                            {v}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            {i.trigger === 'data_fixa' && (
                                <label>
                                    Data de vencimento
                                    <input
                                        type="date"
                                        required
                                        value={i.due_at ?? ''}
                                        onChange={(e) => updateInstallment(n, 'due_at', e.target.value)}
                                    />
                                </label>
                            )}
                            {['dias_antes_evento', 'dias_apos_evento'].includes(i.trigger) && (
                                <label>
                                    Quantidade de dias
                                    <input
                                        type="number"
                                        min="0"
                                        max="3650"
                                        required
                                        value={i.offset_days ?? ''}
                                        onChange={(e) => updateInstallment(n, 'offset_days', Number(e.target.value))}
                                    />
                                </label>
                            )}
                            {['marco', 'entrega'].includes(i.trigger) && (
                                <label>
                                    Marco ou entrega
                                    <input
                                        required
                                        value={i.milestone ?? ''}
                                        onChange={(e) => updateInstallment(n, 'milestone', e.target.value)}
                                    />
                                </label>
                            )}
                            {draft.data.installments.length > 1 && (
                                <button
                                    type="button"
                                    className="button button--ghost"
                                    onClick={() =>
                                        draft.setData(
                                            'installments',
                                            draft.data.installments.filter((_, idx) => idx !== n),
                                        )
                                    }
                                >
                                    Remover parcela {n + 1}
                                </button>
                            )}
                        </fieldset>
                    ))}
                    <p>Total distribuído: {draft.data.installments.reduce((a, i) => a + i.share_bps, 0) / 100}%</p>
                    <button
                        type="button"
                        className="button"
                        onClick={() => draft.setData('installments', [...draft.data.installments, row()])}
                    >
                        Adicionar parcela
                    </button>
                    <Errors errors={draft.errors} />
                    <button className="button button--primary" disabled={draft.processing}>
                        Salvar plano
                    </button>
                </form>
            </Drawer>
            <Drawer open={active === 'payable'} title="Novo pagável" onClose={() => setActive(null)}>
                <form
                    className="finance-form"
                    onSubmit={(e) => {
                        e.preventDefault();
                        payable.transform((d) => ({
                            origin_type: d.origin.split(':')[0],
                            origin_id: Number(d.origin.split(':')[1]),
                            label: d.label,
                            amount_cents: cents(d.amount),
                            due_at: d.due_at || null,
                        }));
                        payable.post(`${base}/payables`, {
                            preserveScroll: true,
                            onSuccess: () => {
                                setActive(null);
                                payable.reset();
                            },
                        });
                    }}
                >
                    <label>
                        Origem aprovada
                        <select required value={payable.data.origin} onChange={(e) => payable.setData('origin', e.target.value)}>
                            <option value="">Selecione</option>
                            {origins.map((o) => (
                                <option value={o.key} key={o.key}>
                                    {o.label}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label>
                        Descrição do pagamento
                        <input required value={payable.data.label} onChange={(e) => payable.setData('label', e.target.value)} />
                    </label>
                    <label>
                        Valor a pagar (R$)
                        <input
                            required
                            inputMode="decimal"
                            value={payable.data.amount}
                            onChange={(e) => payable.setData('amount', e.target.value)}
                        />
                    </label>
                    <label>
                        Vencimento
                        <input type="date" value={payable.data.due_at} onChange={(e) => payable.setData('due_at', e.target.value)} />
                    </label>
                    <Errors errors={payable.errors} />
                    <button className="button button--primary" disabled={payable.processing}>
                        Registrar para aprovação
                    </button>
                </form>
            </Drawer>
            <Drawer
                open={['settle', 'cancel', 'due'].includes(active ?? '')}
                title={active === 'cancel' ? 'Cancelar lançamento' : active === 'due' ? 'Confirmar vencimento' : 'Registrar baixa'}
                onClose={() => setActive(null)}
            >
                <form
                    className="finance-form"
                    onSubmit={(e) => {
                        e.preventDefault();
                        if (!entry) return;
                        operation.transform((d) => ({ ...d, amount_cents: cents(d.amount) }));
                        operation.post(`${base}/${entry.ledger}/${entry.row.id}/${active}`, {
                            preserveScroll: true,
                            onSuccess: () => setActive(null),
                        });
                    }}
                >
                    <p>
                        {entry?.row.label} · saldo {money(entry?.row.balance_cents ?? 0)}
                    </p>
                    {active === 'cancel' ? (
                        <>
                            <p>
                                As baixas anteriores ficam no histórico. O cancelamento não registra devolução nem altera o valor
                                contratado.
                            </p>
                            <label>
                                Justificativa do cancelamento
                                <textarea
                                    required
                                    minLength={3}
                                    value={operation.data.reason}
                                    onChange={(e) => operation.setData('reason', e.target.value)}
                                />
                            </label>
                        </>
                    ) : active === 'due' ? (
                        <>
                            <label>
                                Data confirmada
                                <input
                                    type="date"
                                    required
                                    value={operation.data.due_at}
                                    onChange={(e) => operation.setData('due_at', e.target.value)}
                                />
                            </label>
                            <label>
                                Evidência do gatilho
                                <textarea
                                    required
                                    minLength={3}
                                    value={operation.data.evidence}
                                    onChange={(e) => operation.setData('evidence', e.target.value)}
                                />
                            </label>
                        </>
                    ) : (
                        <>
                            <label>
                                Valor da baixa (R$)
                                <input
                                    required
                                    inputMode="decimal"
                                    value={operation.data.amount}
                                    onChange={(e) => operation.setData('amount', e.target.value)}
                                />
                            </label>
                            <label>
                                Data do pagamento
                                <input
                                    required
                                    type="date"
                                    value={operation.data.paid_at}
                                    onChange={(e) => operation.setData('paid_at', e.target.value)}
                                />
                            </label>
                            <label>
                                Comprovante ou evidência
                                <textarea
                                    required
                                    minLength={3}
                                    value={operation.data.evidence}
                                    onChange={(e) => operation.setData('evidence', e.target.value)}
                                />
                            </label>
                        </>
                    )}
                    <Errors errors={operation.errors} />
                    <button disabled={operation.processing} className="button button--primary">
                        {active === 'cancel' ? 'Confirmar cancelamento' : active === 'due' ? 'Salvar vencimento' : 'Confirmar baixa'}
                    </button>
                </form>
            </Drawer>
            <Drawer open={active === 'cost'} title="Conferir custo realizado" onClose={() => setActive(null)}>
                <form
                    className="finance-form"
                    onSubmit={(e) => {
                        e.preventDefault();
                        cost.transform((d) => ({
                            revision: d.revision,
                            category: d.category,
                            actual_cents: cents(d.actual),
                            variance_reason: d.variance_reason,
                        }));
                        cost.post(`${base}/costs`, { preserveScroll: true, onSuccess: () => setActive(null) });
                    }}
                >
                    <label>
                        Categoria
                        <input
                            required
                            readOnly={cost.data.revision > 0}
                            value={cost.data.category}
                            onChange={(e) => cost.setData('category', e.target.value)}
                        />
                    </label>
                    <label>
                        Custo realizado (R$)
                        <input
                            required
                            inputMode="decimal"
                            value={cost.data.actual}
                            onChange={(e) => cost.setData('actual', e.target.value)}
                        />
                    </label>
                    <label>
                        Justificativa do desvio
                        <textarea value={cost.data.variance_reason} onChange={(e) => cost.setData('variance_reason', e.target.value)} />
                    </label>
                    <Errors errors={cost.errors} />
                    <button className="button button--primary" disabled={cost.processing}>
                        Salvar conferência
                    </button>
                </form>
            </Drawer>
        </AppLayout>
    );
}
