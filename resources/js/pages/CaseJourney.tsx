import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { AppLayout } from '../layout';
import { GlassSurface } from '../components/GlassSurface';
import { CasePageHeader } from '../components/CasePageHeader';
import { ArrowRight } from 'lucide-react';

type Journey = {
    revision: number;
    mode: string;
    modality: string;
    cycle: string;
    viability_status: string;
    management_status: string;
    outcome: string | null;
    deliverables: string[] | null;
    evidence: { action: string; reference: string; at: string; mode: string }[] | null;
};
type Props = {
    opportunity: { id: number; title: string; client_name: string; next_action: string | null };
    journey: Journey | null;
    deliverables: Record<string, string>;
    moduleStatuses: { key: string; label: string; status: string; pending: number }[];
};
const moduleLabels: Record<string, string> = {
    empty: 'Ainda não iniciado',
    draft: 'Em construção',
    needs_review: 'Precisa de revisão',
    approved: 'Aprovado',
    complete: 'Concluído',
    blocked: 'Bloqueado',
    stale: 'Revalidar',
};
const modulePath: Record<string, string> = {
    journey: 'journey',
    briefing: 'briefing',
    viability: 'feasibility',
    budget: 'budget',
    documents: 'documents',
    contract: 'contract',
    finance: 'finance',
    production: 'production',
    'post-event': 'post-event',
};
const labels: Record<string, string> = {
    commercial: 'Comercial',
    viability: 'Viabilidade',
    management: 'Gestão',
    not_contracted: 'Não contratado',
    in_progress: 'Em desenvolvimento',
    delivered: 'Entregue · aguarda aceite',
    accepted: 'Entrega aceita',
    planning: 'Preparação',
    contract_viability: 'Registrar contratação da Viabilidade',
    deliver_viability: 'Registrar entrega do projeto',
    accept_delivery: 'Registrar aceite da entrega',
    close_viability: 'Encerrar Viabilidade sem Gestão',
    contract_management: 'Registrar contratação da Gestão',
};

export default function CaseJourney({ opportunity, journey, deliverables, moduleStatuses }: Props) {
    const [selectedModule, setSelectedModule] = useState<string | null>(
        moduleStatuses.find((module) => ['blocked', 'needs_review', 'stale'].includes(module.status))?.key ??
            moduleStatuses.find((module) => !['approved', 'complete'].includes(module.status))?.key ??
            moduleStatuses[0]?.key ??
            null,
    );
    const selected = moduleStatuses.find((module) => module.key === selectedModule);
    const form = useForm({
        action: 'configure',
        revision: journey?.revision ?? 0,
        mode: journey?.mode ?? 'demo',
        modality: journey?.modality ?? 'express',
        evidence: '',
        deliverables: journey?.deliverables ?? ([] as string[]),
    });
    const next =
        !journey || journey.outcome
            ? []
            : journey.viability_status === 'not_contracted'
              ? ['contract_viability']
              : journey.viability_status === 'in_progress'
                ? ['deliver_viability']
                : journey.viability_status === 'delivered'
                  ? ['accept_delivery']
                  : journey.management_status === 'not_contracted'
                    ? ['close_viability', 'contract_management']
                    : [];
    const editable = !journey || journey.viability_status === 'not_contracted';
    function submit(event: FormEvent, action: string) {
        event.preventDefault();
        form.transform((data) => ({ ...data, action, revision: journey?.revision ?? 0 }));
        form.post('/opportunities/' + opportunity.id + '/journey', { preserveScroll: true, onSuccess: () => form.reset('evidence') });
    }
    return (
        <AppLayout>
            <Head title={'Jornada · ' + opportunity.title} />
            <CasePageHeader
                id={opportunity.id}
                eyebrow="Jornada do projeto"
                title="Da oportunidade à entrega"
                client={opportunity.client_name}
                status={journey?.mode === 'real' ? 'Operação real' : 'Demonstração'}
                actions={
                    <Link className="button button-subtle" href={`/opportunities/${opportunity.id}/feasibility`}>
                        Abrir Viabilidade <ArrowRight size={15} />
                    </Link>
                }
            />
            <section className="journey-workspace" aria-label="Mapa do projeto">
                <header>
                    <span className="eyebrow">MAPA DO PROJETO</span>
                    <h2>Uma jornada, decisões claras.</h2>
                    <p>Ciclo atual: {labels[journey?.cycle ?? 'commercial']}. Selecione uma etapa para ver o que precisa acontecer.</p>
                </header>
                <div className="journey-workspace__layout">
                    <div className="journey-workspace__track" role="group" aria-label="Etapas do projeto">
                        {moduleStatuses.map((module, index) => (
                            <button
                                type="button"
                                key={module.key}
                                className={`journey-workspace__node ${selectedModule === module.key ? 'is-selected' : ''}`}
                                aria-pressed={selectedModule === module.key}
                                onClick={() => setSelectedModule(module.key)}
                            >
                                <span>0{index + 1}</span>
                                <strong>{module.label}</strong>
                                <small>
                                    {moduleLabels[module.status] ?? module.status}
                                    {module.pending > 0 ? ` · ${module.pending} pendência${module.pending === 1 ? '' : 's'}` : ''}
                                </small>
                            </button>
                        ))}
                    </div>
                    {selected && (
                        <aside className="journey-workspace__detail" aria-live="polite">
                            <span className="eyebrow">ETAPA SELECIONADA</span>
                            <h3>{selected.label}</h3>
                            <p>{moduleLabels[selected.status] ?? selected.status}</p>
                            <strong>
                                {selected.pending > 0
                                    ? `${selected.pending} ${selected.pending === 1 ? 'ponto pede atenção' : 'pontos pedem atenção'}`
                                    : 'Nenhuma pendência registrada nesta etapa'}
                            </strong>
                            {opportunity.next_action && <p>Próxima ação do projeto: {opportunity.next_action}</p>}
                            <Link
                                className="button button-primary"
                                href={`/opportunities/${opportunity.id}/${modulePath[selected.key] ?? 'journey'}`}
                            >
                                Abrir {selected.label} <ArrowRight size={15} />
                            </Link>
                        </aside>
                    )}
                </div>
            </section>
            <section className="budget-grid">
                <GlassSurface className="budget-items">
                    <h2>Projeto de Viabilidade</h2>
                    <p>
                        {labels[journey?.viability_status ?? 'not_contracted']} ·{' '}
                        {journey?.modality === 'complete' ? 'Completo' : 'Express'}
                    </p>
                    <h3>Entregáveis</h3>
                    {Object.entries(deliverables)
                        .filter(
                            ([key]) =>
                                (journey?.modality ?? form.data.modality) === 'complete' || !['model_3d', 'floor_plan'].includes(key),
                        )
                        .map(([key, label]) => (
                            <label key={key} style={{ display: 'flex', gap: 12, padding: '12px 0', alignItems: 'center' }}>
                                <input
                                    type="checkbox"
                                    style={{ width: 20, minHeight: 24 }}
                                    checked={form.data.deliverables.includes(key)}
                                    disabled={journey?.viability_status !== 'in_progress'}
                                    onChange={(e) =>
                                        form.setData(
                                            'deliverables',
                                            e.target.checked
                                                ? [...form.data.deliverables, key]
                                                : form.data.deliverables.filter((x) => x !== key),
                                        )
                                    }
                                />
                                {label}
                            </label>
                        ))}
                    <h3>Gestão do evento</h3>
                    <p>{labels[journey?.management_status ?? 'not_contracted']}</p>
                    {journey?.management_status === 'planning' && (
                        <Link className="button button-primary" href={'/opportunities/' + opportunity.id + '/production'}>
                            Abrir tarefas de produção
                        </Link>
                    )}
                    {journey?.outcome && (
                        <p role="status">
                            Viabilidade concluída sem Gestão. Relacionamento, projeto e evidências preservados — não é uma perda comercial.
                        </p>
                    )}
                    <h3>Evidências das decisões</h3>
                    {(journey?.evidence ?? []).map((entry, i) => (
                        <article className="budget-row" style={{ display: 'block' }} key={i}>
                            <strong>{labels[entry.action] ?? entry.action}</strong>
                            <p>{entry.reference}</p>
                            <small>
                                {entry.at} · {entry.mode === 'demo' ? 'Demonstração' : 'Real'}
                            </small>
                        </article>
                    ))}
                </GlassSurface>
                <GlassSurface className="budget-form-card">
                    <h2>Próxima decisão</h2>
                    {Object.entries(form.errors).map(([key, value]) => (
                        <p role="alert" key={key}>
                            {value}
                        </p>
                    ))}
                    {editable && (
                        <form className="form-grid" onSubmit={(e) => submit(e, 'configure')}>
                            <label>
                                Modalidade
                                <select value={form.data.modality} onChange={(e) => form.setData('modality', e.target.value)}>
                                    <option value="express">Express</option>
                                    <option value="complete">Completo</option>
                                    <option value="strategic">Estratégico · definição pendente</option>
                                </select>
                            </label>
                            <label>
                                Modo
                                <select value={form.data.mode} onChange={(e) => form.setData('mode', e.target.value)}>
                                    <option value="demo">Demonstração</option>
                                    <option value="real">Real · exige regras validadas</option>
                                </select>
                            </label>
                            <p>Sem preços automáticos. A contratação e o aceite da entrega são decisões diferentes.</p>
                            <button className="button button-subtle" disabled={form.processing}>
                                Salvar configuração
                            </button>
                        </form>
                    )}
                    {next.length > 0 && (
                        <form className="form-grid" onSubmit={(e) => submit(e, next[0])}>
                            <label>
                                Referência da evidência / decisão
                                <textarea
                                    required
                                    rows={4}
                                    value={form.data.evidence}
                                    onChange={(e) => form.setData('evidence', e.target.value)}
                                    placeholder="Referência do aceite ou documento. Em demonstração, identifique o registro como simulado."
                                />
                            </label>
                            {next.map((action) => (
                                <button
                                    type="button"
                                    className="button button-primary"
                                    key={action}
                                    disabled={form.processing}
                                    onClick={(e) => submit(e, action)}
                                >
                                    {labels[action]}
                                </button>
                            ))}
                        </form>
                    )}
                    {!next.length && journey && <p>Consulte o estado e as evidências do ciclo ao lado.</p>}
                </GlassSurface>
            </section>
        </AppLayout>
    );
}
