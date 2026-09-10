import { Head, Link } from '@inertiajs/react';
import { ArrowRight, CircleAlert, MessageSquareText, Sparkles } from 'lucide-react';
import { AppLayout } from '../layout';
import { GlassSurface } from '../components/GlassSurface';
type Props = { opportunities: { id: number; title: string; clientName: string; status: string; stage: string }[] };
const labels: Record<string, string> = {
    not_started: 'Ainda sem briefing',
    processing: 'IA organizando',
    awaiting_review: 'Revisão pendente',
};
export default function Briefings({ opportunities }: Props) {
    return (
        <AppLayout>
            <Head title="Briefings" />
            <header className="topbar">
                <div>
                    <span className="eyebrow">WORKSPACE CONVERSACIONAL</span>
                    <h1>Briefings.</h1>
                    <p>Contexto original, lacunas e revisão humana em um só lugar.</p>
                </div>
                <span className="status-pill violet">
                    <Sparkles size={12} /> {opportunities.length} pedem atenção
                </span>
            </header>
            <section className="briefings-list">
                <GlassSurface className="briefings-intro">
                    <MessageSquareText size={25} />
                    <h2>A conversa é a primeira matéria-prima do evento.</h2>
                    <p>Abra uma oportunidade para continuar a transcrição, revisar a sugestão da IA e preparar a próxima decisão.</p>
                </GlassSurface>
                <div className="briefing-cards">
                    {opportunities.length ? (
                        opportunities.map((opportunity) => (
                            <GlassSurface className="briefing-index-card" key={opportunity.id}>
                                <div className="card-top">
                                    <span className="status-pill amber">{labels[opportunity.status] || opportunity.status}</span>
                                    <CircleAlert size={15} className="muted-icon" />
                                </div>
                                <h3>{opportunity.title}</h3>
                                <p>
                                    {opportunity.clientName} · estágio {opportunity.stage}
                                </p>
                                <div className="briefing-index-actions">
                                    <Link className="button button-primary" href={`/opportunities/${opportunity.id}/briefing`}>
                                        Abrir briefing <ArrowRight size={14} />
                                    </Link>
                                    <Link className="text-button" href={`/opportunities/${opportunity.id}`}>
                                        Ver hub
                                    </Link>
                                </div>
                            </GlassSurface>
                        ))
                    ) : (
                        <GlassSurface>
                            <div className="empty-state compact">
                                <MessageSquareText size={22} />
                                <p>Todos os briefings estão em dia.</p>
                            </div>
                        </GlassSurface>
                    )}
                </div>
            </section>
        </AppLayout>
    );
}
