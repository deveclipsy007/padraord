import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { AppLayout } from '../layout';
import { GlassSurface } from '../components/GlassSurface';
import { CasePageHeader } from '../components/CasePageHeader';
import { ArrowRight, BriefcaseBusiness, Check, CircleDollarSign, Layers3 } from 'lucide-react';

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
    opportunity: { id: number; title: string; client_name: string };
    journey: Journey | null;
    deliverables: Record<string, string>;
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

export default function CaseJourney({ opportunity, journey, deliverables }: Props) {
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
    const icons = [BriefcaseBusiness, Layers3, CircleDollarSign];
    return (
        <AppLayout>
            <Head title={'Jornada · ' + opportunity.title} />
            <CasePageHeader
                id={opportunity.id}
                eyebrow="Mapa dos ciclos"
                title="Da oportunidade à entrega"
                client={opportunity.client_name}
                status={journey?.mode === 'real' ? 'Operação real' : 'Demonstração'}
                actions={
                    <Link className="button button-subtle" href={`/opportunities/${opportunity.id}/feasibility`}>
                        Abrir Viabilidade <ArrowRight size={15} />
                    </Link>
                }
            />
            <section className="case-cycle-map">
                {['commercial', 'viability', 'management'].map((cycle, index) => {
                    const Icon = icons[index];
                    const active = journey?.cycle === cycle;
                    const completed =
                        cycle === 'commercial'
                            ? journey?.viability_status !== 'not_contracted'
                            : cycle === 'viability'
                              ? ['accepted', 'closed'].includes(journey?.viability_status ?? '') || !!journey?.outcome
                              : journey?.management_status === 'planning';
                    return (
                        <article className={`case-cycle${active ? ' is-active' : ''}${completed ? ' is-complete' : ''}`} key={cycle}>
                            <div className="case-cycle__icon">{completed ? <Check size={17} /> : <Icon size={17} />}</div>
                            <span>CICLO {index + 1}</span>
                            <h2>{labels[cycle]}</h2>
                            <p>
                                {active
                                    ? 'Ciclo atual · confira a próxima decisão'
                                    : cycle === 'management'
                                      ? 'Contratação opcional após aceite da Viabilidade'
                                      : completed
                                        ? 'Marco preservado no histórico'
                                        : 'Aguardando ciclo anterior'}
                            </p>
                            {index < 2 && <ArrowRight className="case-cycle__arrow" size={18} />}
                        </article>
                    );
                })}
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
