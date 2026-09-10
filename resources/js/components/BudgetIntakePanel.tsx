import { useForm } from '@inertiajs/react';
import { Field } from './FormControls';
export type IntakeQuote = {
    id: number;
    label: string;
    priceBasis?: string;
    quantity?: string;
    unit?: string;
    unitCostCents: number;
    validUntil: string;
};
export type Preparation = { id: number; stale: boolean; payload: { budget: { description: string }[] } } | null;
export function BudgetIntakePanel({
    caseId,
    revision,
    locked,
    quotes,
    selectedQuoteId,
    preparation,
}: {
    caseId: number;
    revision: number;
    locked: boolean;
    quotes: IntakeQuote[];
    selectedQuoteId: number;
    preparation: Preparation;
}) {
    const f = useForm({
        revision,
        request_key: crypto.randomUUID(),
        quote_id: selectedQuoteId ? String(selectedQuoteId) : '',
        preparation_id: '',
        index: '',
        quantity: '',
        unit: '',
    });
    const quote = quotes.find((q) => String(q.id) === f.data.quote_id);
    return (
        <section className="rd-panel">
            <h2>Trazer contexto para o orçamento</h2>
            <p>Importe uma cotação com sua referência ou escolha um item do roteiro. Nada é aprovado nesta etapa.</p>
            <form
                className="rd-filter-bar"
                onSubmit={(e) => {
                    e.preventDefault();
                    f.transform((d) => ({ ...d, revision }));
                    f.post(`/opportunities/${caseId}/budget/intake`, {
                        preserveScroll: true,
                        onSuccess: () => {
                            f.reset();
                            f.setData('request_key', crypto.randomUUID());
                        },
                    });
                }}
            >
                <Field label="Cotação recebida">
                    <select
                        disabled={locked}
                        value={f.data.quote_id}
                        onChange={(e) => f.setData((d) => ({ ...d, quote_id: e.target.value, preparation_id: '', index: '' }))}
                    >
                        <option value="">Selecionar cotação</option>
                        {quotes.map((q) => (
                            <option key={q.id} value={q.id}>
                                {q.label} · {q.priceBasis === 'total' ? 'pacote' : q.priceBasis === 'unit' ? 'unitário' : 'base pendente'}
                            </option>
                        ))}
                    </select>
                </Field>
                {quote && (
                    <p>
                        {new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(quote.unitCostCents / 100)} ·{' '}
                        {quote.quantity || '?'} {quote.unit || 'unidade pendente'} · validade {quote.validUntil}.{' '}
                        {quote.priceBasis === 'total' ? 'Será incluído como 1 pacote, sem multiplicar o valor.' : ''}
                    </p>
                )}
                {preparation && !preparation.stale && (
                    <>
                        <Field label="Ou item do roteiro">
                            <select
                                disabled={locked}
                                value={f.data.index}
                                onChange={(e) =>
                                    f.setData((d) => ({
                                        ...d,
                                        quote_id: '',
                                        index: e.target.value,
                                        preparation_id: e.target.value !== '' ? String(preparation.id) : '',
                                    }))
                                }
                            >
                                <option value="">Selecionar item preparado</option>
                                {preparation.payload.budget.map((item, i) => (
                                    <option key={i} value={i}>
                                        {item.description}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        {f.data.preparation_id && (
                            <>
                                <Field label="Quantidade confirmada">
                                    <input
                                        required
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        value={f.data.quantity}
                                        onChange={(e) => f.setData('quantity', e.target.value)}
                                    />
                                </Field>
                                <Field label="Unidade confirmada">
                                    <input required value={f.data.unit} onChange={(e) => f.setData('unit', e.target.value)} />
                                </Field>
                                <p>O custo ficará pendente, sem estimativa inventada.</p>
                            </>
                        )}
                    </>
                )}
                {preparation?.stale && <p role="status">O briefing mudou. Gere um novo roteiro antes de importar.</p>}
                {Object.values(f.errors).map((error, i) => (
                    <p role="alert" className="form-error" key={i}>
                        {error}
                    </p>
                ))}
                <button className="button button-primary" disabled={locked || f.processing || (!f.data.quote_id && !f.data.preparation_id)}>
                    {f.processing ? 'Incluindo…' : 'Confirmar inclusão no rascunho'}
                </button>
            </form>
        </section>
    );
}
