import { useForm } from '@inertiajs/react';

type Deliverable = {
    id?: number;
    key: string;
    title: string;
    status: string;
    required: boolean;
    content?: string | null;
    evidence?: { note?: string } | null;
};

export function ViabilityDeliverableEvidence({ caseId, deliverable }: { caseId: number; deliverable: Deliverable }) {
    const form = useForm({
        status: deliverable.status === 'pending' ? 'draft' : deliverable.status,
        content: deliverable.content ?? '',
        evidence: deliverable.evidence?.note ?? '',
    });

    if (!deliverable.id) return null;

    return (
        <details className="viability-deliverable-evidence">
            <summary>Conteúdo e evidência</summary>
            <form
                className="form-grid"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post('/opportunities/' + caseId + '/feasibility/deliverables/' + deliverable.id, { preserveScroll: true });
                }}
            >
                <label>
                    Estado
                    <select value={form.data.status} onChange={(event) => form.setData('status', event.target.value)}>
                        <option value="pending">Pendente</option>
                        <option value="draft">Em rascunho</option>
                        <option value="ready">Pronto para entrega</option>
                        <option value="delivered">Entregue</option>
                    </select>
                </label>
                <label>
                    Conteúdo ou referência
                    <textarea rows={2} value={form.data.content} onChange={(event) => form.setData('content', event.target.value)} />
                </label>
                <label>
                    Evidência
                    <textarea
                        rows={2}
                        value={form.data.evidence}
                        onChange={(event) => form.setData('evidence', event.target.value)}
                        placeholder="Mensagem, documento ou decisão que comprova o entregável."
                    />
                </label>
                {Object.values(form.errors).map((error, index) => (
                    <p role="alert" className="form-error" key={index}>
                        {error}
                    </p>
                ))}
                <button className="button button-subtle" disabled={form.processing}>
                    Salvar evidência
                </button>
            </form>
        </details>
    );
}
