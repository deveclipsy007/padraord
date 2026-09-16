import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { AppLayout } from '../layout';
import { Drawer, Field, FilterBar, FormErrors } from '../components/FormControls';
export type Supplier = {
    id: number;
    name: string;
    service: string | null;
    phone: string | null;
    email: string | null;
    notes: string | null;
    revision: number;
    status: string;
};
export type Quote = {
    id: number;
    supplier_id: number;
    supplier?: Supplier;
    opportunity_id: number;
    service: string;
    unit_cost_cents: number;
    valid_until: string;
    conditions: string | null;
    evidence: string;
    price_basis: string | null;
    quantity: string | null;
    unit: string | null;
    supersedes_id: number | null;
    is_current_revision?: boolean;
    is_valid?: boolean;
};
type Pagination<T> = { data: T[]; current_page: number; last_page: number; prev_page_url: string | null; next_page_url: string | null };
const money = (n: number) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(n / 100);
const comparableTotal = (quote: Quote) => {
    if (quote.price_basis === 'total') return quote.unit_cost_cents;
    const quantity = Number(quote.quantity);
    return quote.price_basis === 'unit' && Number.isFinite(quantity) ? Math.round(quote.unit_cost_cents * quantity) : null;
};
const dateTime = (value: string | null) =>
    value ? new Intl.DateTimeFormat('pt-BR', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '—';
type Comparison = {
    id: number;
    opportunity_id: number;
    title: string;
    status: 'draft' | 'decided';
    scope_difference: string | null;
    justification: string | null;
    decision_quote_id: number | null;
    created_at: string | null;
    decided_at: string | null;
    items: { quote_id: number; supplier_name: string; service: string; normalized_total_cents: number | null; can_be_decided: boolean }[];
};
export function SupplierForm({ supplier, onSaved }: { supplier?: Supplier; onSaved: () => void }) {
    const f = useForm({
        name: supplier?.name ?? '',
        service: supplier?.service ?? '',
        email: supplier?.email ?? '',
        phone: supplier?.phone ?? '',
        notes: supplier?.notes ?? '',
        status: supplier?.status ?? 'active',
        revision: supplier?.revision ?? 0,
    });
    const [duplicates, setDuplicates] = useState<{ id: number; name: string }[]>([]);
    useEffect(() => {
        const controller = new AbortController();
        const timer = setTimeout(() => {
            const query = new URLSearchParams({
                name: f.data.name,
                email: f.data.email,
                phone: f.data.phone,
                exclude: String(supplier?.id || ''),
            });
            fetch('/suppliers/duplicates?' + query, { headers: { Accept: 'application/json' }, signal: controller.signal })
                .then((r) => (r.ok ? r.json() : []))
                .then(setDuplicates)
                .catch(() => {});
        }, 500);
        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [f.data.name, f.data.email, f.data.phone, supplier?.id]);
    return (
        <form
            className="form-grid"
            onSubmit={(e) => {
                e.preventDefault();
                const options = { preserveScroll: true, onSuccess: onSaved };
                supplier ? f.patch('/suppliers/' + supplier.id, options) : f.post('/suppliers', options);
            }}
        >
            {(['name', 'service', 'email', 'phone'] as const).map((k, i) => (
                <Field key={k} label={['Nome', 'Serviço', 'E-mail', 'Telefone'][i]}>
                    <input
                        required={k === 'name'}
                        type={k === 'email' ? 'email' : 'text'}
                        value={f.data[k]}
                        onChange={(e) => f.setData(k, e.target.value)}
                    />
                </Field>
            ))}
            <Field label="Observações">
                <textarea rows={4} value={f.data.notes} onChange={(e) => f.setData('notes', e.target.value)} />
            </Field>
            <Field label="Situação">
                <select value={f.data.status} onChange={(e) => f.setData('status', e.target.value)}>
                    <option value="active">Ativo</option>
                    <option value="inactive">Inativo</option>
                </select>
            </Field>
            {duplicates.length > 0 && (
                <div role="status">
                    <strong>Confira possíveis duplicidades antes de salvar</strong>
                    {duplicates.map((d) => (
                        <p key={d.id}>
                            <Link href={'/suppliers/' + d.id}>{d.name}</Link>
                        </p>
                    ))}
                    <small>Nenhum cadastro será unido automaticamente.</small>
                </div>
            )}
            <FormErrors errors={f.errors} />
            <button className="button button-primary" disabled={f.processing}>
                {f.processing ? 'Salvando…' : 'Salvar fornecedor'}
            </button>
        </form>
    );
}
function Pages({ page }: { page: Pagination<unknown> }) {
    return (
        <div className="rd-form-actions">
            {page.prev_page_url && (
                <Link className="button button-subtle" href={page.prev_page_url} preserveScroll>
                    Anterior
                </Link>
            )}
            <span>
                Página {page.current_page} de {page.last_page}
            </span>
            {page.next_page_url && (
                <Link className="button button-subtle" href={page.next_page_url} preserveScroll>
                    Próxima
                </Link>
            )}
        </div>
    );
}
type Props = {
    suppliers: Pagination<Supplier>;
    quotes: Pagination<Quote>;
    inquiries: { id: number; supplier_id: number; supplier_name: string; opportunity_id: number; service: string }[];
    comparisons: Comparison[];
    supplierOptions: { id: number; name: string }[];
    opportunities: { id: number; title: string }[];
    services: string[];
    filters: Record<string, string>;
};
export default function Suppliers({ suppliers, quotes, inquiries, comparisons, supplierOptions, opportunities, services, filters }: Props) {
    const [drawer, setDrawer] = useState<'supplier' | 'quote' | 'inquiry' | 'comparison' | null>(null);
    const [edit, setEdit] = useState<Supplier>();
    const [selected, setSelected] = useState<number[]>([]);
    const search = useForm({
        q: filters.q ?? '',
        service: filters.service ?? '',
        status: filters.status ?? '',
        tab: filters.tab ?? 'suppliers',
        opportunity_id: filters.opportunity_id ?? '',
        supplier_id: filters.supplier_id ?? '',
        quote_status: filters.quote_status ?? '',
    });
    const f = useForm({
        supplier_id: String(filters.supplier_id ?? ''),
        opportunity_id: String(filters.opportunity_id ?? ''),
        service: '',
        unit_cost: '',
        valid_until: '',
        conditions: '',
        evidence: '',
        price_basis: '',
        quantity: '',
        unit: '',
        supersedes_id: '',
        inquiry_id: '',
    });
    const comparison = useForm({
        title: '',
        quote_ids: [] as number[],
        scope_difference: '',
        decision_quote_id: '',
        justification: '',
    });
    const tab = filters.tab ?? 'suppliers';
    function switchTab(tab: string) {
        router.get('/suppliers', { ...filters, tab }, { preserveScroll: true });
    }
    function newQuote(q?: Quote) {
        f.setData({
            supplier_id: String(q?.supplier_id ?? filters.supplier_id ?? ''),
            opportunity_id: String(q?.opportunity_id ?? filters.opportunity_id ?? ''),
            service: q?.service ?? '',
            unit_cost: q ? (q.unit_cost_cents / 100).toFixed(2) : '',
            valid_until: q?.valid_until.slice(0, 10) ?? '',
            conditions: q?.conditions ?? '',
            evidence: '',
            price_basis: q?.price_basis ?? '',
            quantity: q?.quantity ?? '',
            unit: q?.unit ?? '',
            supersedes_id: q ? String(q.id) : '',
            inquiry_id: '',
        });
        setDrawer('quote');
    }
    const compared = quotes.data.filter((q) => selected.includes(q.id));
    const sameCase = new Set(compared.map((q) => q.opportunity_id)).size <= 1;
    const hasScopeDifference = new Set(compared.map((q) => q.service.trim().replace(/\s+/g, ' ').toLocaleLowerCase('pt-BR'))).size > 1;
    function registerComparison() {
        if (!sameCase || compared.length < 2) return;
        comparison.setData({
            title: `Alternativas para ${opportunities.find((o) => o.id === compared[0].opportunity_id)?.title ?? 'o caso'}`,
            quote_ids: compared.map((q) => q.id),
            scope_difference: '',
            decision_quote_id: '',
            justification: '',
        });
        setDrawer('comparison');
    }
    return (
        <AppLayout>
            <Head title="Fornecedores e cotações" />
            <header className="topbar">
                <div>
                    <span className="eyebrow">REDE DE PRODUÇÃO</span>
                    <h1>Fornecedores e cotações</h1>
                    <p>Do contato à cotação, com contexto e sem redigitação.</p>
                </div>
                <button
                    className="button button-primary"
                    onClick={() => {
                        if (tab === 'suppliers') {
                            setEdit(undefined);
                            setDrawer('supplier');
                        } else newQuote();
                    }}
                >
                    {tab === 'suppliers' ? 'Novo fornecedor' : 'Registrar cotação'}
                </button>
            </header>
            <nav className="rd-tabs">
                <button className="button button-subtle" aria-pressed={tab === 'suppliers'} onClick={() => switchTab('suppliers')}>
                    Fornecedores
                </button>
                <button className="button button-subtle" aria-pressed={tab === 'quotes'} onClick={() => switchTab('quotes')}>
                    Cotações
                </button>
            </nav>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    search.get('/suppliers', { preserveScroll: true });
                }}
            >
                <FilterBar>
                    {tab === 'suppliers' ? (
                        <>
                            <Field label="Buscar fornecedor">
                                <input
                                    value={search.data.q}
                                    onChange={(e) => search.setData('q', e.target.value)}
                                    placeholder="Nome ou serviço"
                                />
                            </Field>
                            <Field label="Serviço">
                                <select value={search.data.service} onChange={(e) => search.setData('service', e.target.value)}>
                                    <option value="">Todos</option>
                                    {services.map((s) => (
                                        <option key={s}>{s}</option>
                                    ))}
                                </select>
                            </Field>
                            <Field label="Situação">
                                <select value={search.data.status} onChange={(e) => search.setData('status', e.target.value)}>
                                    <option value="">Todas</option>
                                    <option value="active">Ativos</option>
                                    <option value="inactive">Inativos</option>
                                </select>
                            </Field>
                        </>
                    ) : (
                        <>
                            <Field label="Caso">
                                <select
                                    value={search.data.opportunity_id}
                                    onChange={(e) => search.setData('opportunity_id', e.target.value)}
                                >
                                    <option value="">Todos</option>
                                    {opportunities.map((o) => (
                                        <option key={o.id} value={o.id}>
                                            {o.title}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                            <Field label="Validade">
                                <select value={search.data.quote_status} onChange={(e) => search.setData('quote_status', e.target.value)}>
                                    <option value="">Todas</option>
                                    <option value="current">Vigentes</option>
                                    <option value="expired">Vencidas</option>
                                </select>
                            </Field>
                        </>
                    )}
                    <button className="button button-subtle">Aplicar filtros</button>
                </FilterBar>
            </form>
            {tab === 'suppliers' ? (
                <section className="rd-panel">
                    <div className="partner-library">
                        {suppliers.data.map((s) => (
                            <article className="partner-card" key={s.id}>
                                <div className="partner-card__top">
                                    <span className="partner-monogram" aria-hidden="true">
                                        {s.name.slice(0, 2).toLocaleUpperCase('pt-BR')}
                                    </span>
                                    <span className="status-pill gray">{s.status === 'active' ? 'Ativo' : 'Inativo'}</span>
                                </div>
                                <span className="eyebrow">{s.service ?? 'Serviço a definir'}</span>
                                <h2>
                                    <Link href={'/suppliers/' + s.id}>{s.name}</Link>
                                </h2>
                                <div className="partner-contact">
                                    <span>{s.email || 'E-mail não informado'}</span>
                                    <span>{s.phone || 'Telefone não informado'}</span>
                                </div>
                                <footer>
                                    <Link href={'/suppliers/' + s.id}>Abrir parceiro ↗</Link>
                                    <button
                                        className="button button-subtle"
                                        onClick={() => {
                                            setEdit(s);
                                            setDrawer('supplier');
                                        }}
                                    >
                                        Editar
                                    </button>
                                </footer>
                            </article>
                        ))}
                    </div>
                    {!suppliers.data.length && <p className="rd-empty">Nenhum fornecedor neste filtro.</p>}
                    <Pages page={suppliers} />
                </section>
            ) : (
                <>
                    <section className="rd-panel">
                        <h2>Consultas aguardando resposta</h2>
                        <button className="button button-subtle" onClick={() => setDrawer('inquiry')}>
                            Registrar consulta
                        </button>
                        {!inquiries.length && <p>Nenhuma consulta pendente.</p>}
                        {inquiries.map((i) => (
                            <article className="assistance-record" key={i.id}>
                                <strong>
                                    {i.supplier_name} · {i.service}
                                </strong>
                                <button
                                    className="button button-subtle"
                                    onClick={() => {
                                        f.setData({
                                            supplier_id: String(i.supplier_id),
                                            opportunity_id: String(i.opportunity_id),
                                            service: i.service,
                                            unit_cost: '',
                                            valid_until: '',
                                            conditions: '',
                                            evidence: '',
                                            price_basis: '',
                                            quantity: '',
                                            unit: '',
                                            supersedes_id: '',
                                            inquiry_id: String(i.id),
                                        });
                                        setDrawer('quote');
                                    }}
                                >
                                    Registrar resposta
                                </button>
                            </article>
                        ))}
                    </section>
                    <section className="rd-panel">
                        <h2>Cotações recebidas</h2>
                        <p>Preço unitário e pacote total não são equivalentes. Seleção não significa contratação.</p>
                        {quotes.data.map((q) => (
                            <article className="assistance-record" key={q.id}>
                                <label>
                                    <span>
                                        <input
                                            type="checkbox"
                                            checked={selected.includes(q.id)}
                                            onChange={(e) =>
                                                setSelected((s) => (e.target.checked ? [...s, q.id] : s.filter((id) => id !== q.id)))
                                            }
                                        />{' '}
                                        Comparar {q.supplier?.name} · {q.service}
                                    </span>
                                </label>
                                <p>
                                    <strong>{money(q.unit_cost_cents)}</strong> ·{' '}
                                    {q.price_basis === 'total'
                                        ? 'Pacote total'
                                        : q.price_basis === 'unit'
                                          ? 'Por unidade'
                                          : 'Base a confirmar'}{' '}
                                    · {q.quantity ?? '?'} {q.unit ?? 'unidade a confirmar'}
                                </p>
                                <p>
                                    {opportunities.find((o) => o.id === q.opportunity_id)?.title} · validade {q.valid_until.slice(0, 10)} ·{' '}
                                    {q.conditions || 'Condições não informadas'}
                                </p>
                                {(!q.is_valid || !q.is_current_revision) && (
                                    <small>
                                        {!q.is_valid ? 'Cotação vencida' : 'Revisão substituída'}: permanece no histórico, mas não pode
                                        receber a decisão.
                                    </small>
                                )}
                                <div className="rd-form-actions">
                                    <Link
                                        className="button button-subtle"
                                        href={`/opportunities/${q.opportunity_id}/budget?quote_id=${q.id}`}
                                    >
                                        Levar ao orçamento
                                    </Link>
                                    <button className="button button-subtle" onClick={() => newQuote(q)}>
                                        Nova revisão
                                    </button>
                                </div>
                            </article>
                        ))}
                        {!quotes.data.length && <p>Nenhuma cotação neste filtro.</p>}
                        <Pages page={quotes} />
                    </section>
                    {compared.length > 0 && (
                        <section className="rd-panel">
                            <h2>Comparação de alternativas</h2>
                            {!sameCase ? (
                                <p role="alert">Selecione cotações do mesmo caso.</p>
                            ) : (
                                <div className="rd-table-wrap">
                                    <table className="rd-table">
                                        <thead>
                                            <tr>
                                                <th>Fornecedor / escopo</th>
                                                <th>Base / quantidade</th>
                                                <th>Valor comparável</th>
                                                <th>Condições / evidência</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {compared.map((q) => (
                                                <tr key={q.id}>
                                                    <td>
                                                        {q.supplier?.name}
                                                        <small>{q.service}</small>
                                                    </td>
                                                    <td>
                                                        {q.price_basis ?? 'A confirmar'}
                                                        <small>
                                                            {q.quantity} {q.unit}
                                                        </small>
                                                    </td>
                                                    <td>
                                                        {comparableTotal(q) === null ? 'Dados insuficientes' : money(comparableTotal(q)!)}
                                                    </td>
                                                    <td>
                                                        {q.conditions}
                                                        <small>Validade: {q.valid_until.slice(0, 10)}</small>
                                                        <small>{q.evidence}</small>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                    <p>
                                        Confira equivalência de escopo antes de escolher. O sistema não presume que o menor preço seja a
                                        melhor opção.
                                    </p>
                                    <div className="rd-form-actions">
                                        <button
                                            className="button button-primary"
                                            type="button"
                                            disabled={compared.length < 2}
                                            onClick={registerComparison}
                                        >
                                            Registrar comparação e decisão
                                        </button>
                                        {hasScopeDifference && (
                                            <small>
                                                As alternativas têm escopos diferentes: a explicação será obrigatória no registro.
                                            </small>
                                        )}
                                    </div>
                                </div>
                            )}
                        </section>
                    )}
                    <section className="rd-panel">
                        <h2>Histórico de comparações</h2>
                        <p>O registro preserva as condições avaliadas, a decisão e a justificativa. Ele não altera a cotação original.</p>
                        {!comparisons.length && <p className="rd-empty">Nenhuma comparação registrada neste recorte.</p>}
                        {comparisons.map((item) => {
                            const decision = item.items.find((quote) => quote.quote_id === item.decision_quote_id);
                            return (
                                <article className="assistance-record" key={item.id}>
                                    <strong>{item.title}</strong>
                                    <p>
                                        {opportunities.find((o) => o.id === item.opportunity_id)?.title ?? 'Caso preservado'} ·{' '}
                                        {item.status === 'decided' ? 'Decisão registrada' : 'Aguardando decisão'} ·{' '}
                                        {dateTime(item.decided_at ?? item.created_at)}
                                    </p>
                                    {decision && (
                                        <p>
                                            Escolhida: {decision.supplier_name} · {decision.service} ·{' '}
                                            {decision.normalized_total_cents === null
                                                ? 'valor incompleto'
                                                : money(decision.normalized_total_cents)}
                                        </p>
                                    )}
                                    {item.scope_difference && <small>Diferença de escopo: {item.scope_difference}</small>}
                                    {item.justification && <small>Justificativa: {item.justification}</small>}
                                    <a
                                        className="button button-subtle"
                                        href={`/opportunities/${item.opportunity_id}/quote-comparisons/${item.id}/export`}
                                    >
                                        Exportar registro
                                    </a>
                                </article>
                            );
                        })}
                    </section>
                </>
            )}
            <Drawer title={edit ? 'Editar fornecedor' : 'Novo fornecedor'} open={drawer === 'supplier'} onClose={() => setDrawer(null)}>
                <SupplierForm key={edit ? `${edit.id}-${edit.revision}` : 'new'} supplier={edit} onSaved={() => setDrawer(null)} />
            </Drawer>
            <Drawer
                title={drawer === 'inquiry' ? 'Registrar consulta' : 'Registrar cotação'}
                open={drawer === 'quote' || drawer === 'inquiry'}
                onClose={() => setDrawer(null)}
            >
                <form
                    className="form-grid"
                    onSubmit={(e) => {
                        e.preventDefault();
                        f.post(`/suppliers/${f.data.supplier_id}/${drawer === 'inquiry' ? 'inquiries' : 'quotes'}`, {
                            preserveScroll: true,
                            onSuccess: () => setDrawer(null),
                        });
                    }}
                >
                    <Field label="Fornecedor">
                        <select required value={f.data.supplier_id} onChange={(e) => f.setData('supplier_id', e.target.value)}>
                            <option value="">Selecione</option>
                            {supplierOptions.map((s) => (
                                <option key={s.id} value={s.id}>
                                    {s.name}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Caso">
                        <select required value={f.data.opportunity_id} onChange={(e) => f.setData('opportunity_id', e.target.value)}>
                            <option value="">Selecione</option>
                            {opportunities.map((o) => (
                                <option key={o.id} value={o.id}>
                                    {o.title}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Serviço / escopo">
                        <input required value={f.data.service} onChange={(e) => f.setData('service', e.target.value)} />
                    </Field>
                    {drawer === 'quote' && (
                        <>
                            <Field label="O preço recebido é">
                                <select required value={f.data.price_basis} onChange={(e) => f.setData('price_basis', e.target.value)}>
                                    <option value="">Confirme a base</option>
                                    <option value="unit">Unitário</option>
                                    <option value="total">Total do pacote</option>
                                </select>
                            </Field>
                            {(['unit_cost', 'quantity', 'unit', 'valid_until'] as const).map((k, i) => (
                                <Field key={k} label={['Preço recebido (R$)', 'Quantidade coberta', 'Unidade', 'Válida até'][i]}>
                                    <input
                                        required
                                        type={k === 'valid_until' ? 'date' : 'text'}
                                        value={f.data[k]}
                                        onChange={(e) => f.setData(k, e.target.value)}
                                    />
                                </Field>
                            ))}
                            <Field label="Condições">
                                <textarea value={f.data.conditions} onChange={(e) => f.setData('conditions', e.target.value)} />
                            </Field>
                            <Field label="Mensagem / evidência">
                                <textarea required value={f.data.evidence} onChange={(e) => f.setData('evidence', e.target.value)} />
                            </Field>
                        </>
                    )}
                    <FormErrors errors={f.errors} />
                    <button className="button button-primary" disabled={f.processing}>
                        Salvar {drawer === 'inquiry' ? 'consulta' : 'cotação'}
                    </button>
                </form>
            </Drawer>
            <Drawer title="Registrar comparação e decisão" open={drawer === 'comparison'} onClose={() => setDrawer(null)}>
                <form
                    className="form-grid"
                    onSubmit={(event) => {
                        event.preventDefault();
                        const opportunityId = compared[0]?.opportunity_id;
                        if (!opportunityId) return;
                        comparison.post(`/opportunities/${opportunityId}/quote-comparisons`, {
                            preserveScroll: true,
                            onSuccess: () => {
                                setSelected([]);
                                setDrawer(null);
                            },
                        });
                    }}
                >
                    <Field label="Título do registro">
                        <input
                            required
                            value={comparison.data.title}
                            onChange={(event) => comparison.setData('title', event.target.value)}
                        />
                    </Field>
                    <p>
                        {compared.length} alternativas selecionadas. O valor comparável considera a base e a quantidade registradas em cada
                        cotação.
                    </p>
                    <Field label={hasScopeDifference ? 'Diferença de escopo' : 'Observação de escopo'}>
                        <textarea
                            required={hasScopeDifference}
                            rows={4}
                            value={comparison.data.scope_difference}
                            onChange={(event) => comparison.setData('scope_difference', event.target.value)}
                            placeholder={
                                hasScopeDifference
                                    ? 'Explique o que cada alternativa inclui ou deixa de incluir.'
                                    : 'Opcional: registre uma ressalva de equivalência.'
                            }
                        />
                    </Field>
                    <Field label="Alternativa escolhida">
                        <select
                            value={comparison.data.decision_quote_id}
                            onChange={(event) => comparison.setData('decision_quote_id', event.target.value)}
                        >
                            <option value="">Registrar sem decisão por enquanto</option>
                            {compared.map((quote) => (
                                <option
                                    key={quote.id}
                                    value={quote.id}
                                    disabled={!quote.is_valid || !quote.is_current_revision || comparableTotal(quote) === null}
                                >
                                    {quote.supplier?.name} · {quote.service}
                                    {!quote.is_valid ? ' (vencida)' : !quote.is_current_revision ? ' (revisão substituída)' : ''}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Justificativa da decisão">
                        <textarea
                            required={Boolean(comparison.data.decision_quote_id)}
                            rows={4}
                            value={comparison.data.justification}
                            onChange={(event) => comparison.setData('justification', event.target.value)}
                            placeholder="Por que esta alternativa foi escolhida?"
                        />
                    </Field>
                    <FormErrors errors={comparison.errors} />
                    <button className="button button-primary" disabled={comparison.processing}>
                        {comparison.processing
                            ? 'Registrando…'
                            : comparison.data.decision_quote_id
                              ? 'Registrar decisão'
                              : 'Registrar comparação'}
                    </button>
                </form>
            </Drawer>
        </AppLayout>
    );
}
