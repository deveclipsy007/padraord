import { Head } from '@inertiajs/react';
import { ArrowRight, Check, CircleHelp, Play, Sparkles } from 'lucide-react';
import { useState } from 'react';
import { AppLayout } from '../layout';
import { GlassSurface } from '../components/GlassSurface';
import { GuidedTour } from '../components/GuidedTour';

const steps = ['Conheça a navegação', 'Abra uma oportunidade', 'Organize um briefing', 'Revise a sugestão da IA', 'Monte um item de orçamento', 'Crie uma tarefa de produção', 'Envie seu feedback'];

export default function Help() {
    const [tourOpen, setTourOpen] = useState(false);
    return <AppLayout><Head title="Ajuda e tour" /><header className="topbar"><div><span className="eyebrow">PRIMEIRO CICLO</span><h1>Comece pelo contexto.</h1><p>Um tour curto para você experimentar o fluxo completo sem precisar decorar o sistema.</p></div><button className="button button-primary" type="button" onClick={() => setTourOpen(true)}><Play size={15} /> Iniciar tour</button></header><section className="help-grid"><GlassSurface className="tour-card"><div className="tour-card-icon"><Sparkles size={20} /></div><span className="eyebrow">GUIA PADRÃO RD</span><h2>Do primeiro contato ao evento encerrado.</h2><p>O protótipo acompanha cada decisão, mostra onde a IA ajuda e deixa claro quando a equipe precisa aprovar.</p><button className="button button-subtle" type="button" onClick={() => setTourOpen(true)}>Ver demonstração <ArrowRight size={15} /></button></GlassSurface><GlassSurface><div className="panel-heading"><div><span className="eyebrow">CHECKLIST DO PILOTO</span><h2>7 passos para validar</h2></div><CircleHelp size={17} className="muted-icon" /></div><div className="tour-steps">{steps.map((step, index) => <div className="tour-step" key={step}><span className={index === 0 ? 'check-circle complete' : 'check-circle'}>{index === 0 ? <Check size={12} /> : index + 1}</span><span>{step}</span><small>{index === 0 ? 'feito' : 'a seguir'}</small></div>)}</div></GlassSurface></section>{tourOpen && <GuidedTour onClose={() => setTourOpen(false)} />}</AppLayout>;
}
