import { ArrowLeft, ArrowRight, Check, X } from 'lucide-react';
import { useEffect, useState } from 'react';

const steps = [
    ['Navegação', 'Use Hoje, Pipeline e Agenda para alternar o ritmo da operação.'],
    ['Oportunidade', 'Cada evento tem um hub que reúne contexto, decisões e próximos passos.'],
    ['Briefing', 'Cole uma transcrição ou escreva como conversa; a IA devolve uma sugestão revisável.'],
    ['Orçamento', 'Custos, margem e fornecedores ficam ligados ao mesmo evento.'],
    ['Produção', 'Transforme decisões em tarefas, prazos e responsáveis.'],
    ['Assistente', 'Abra o assistente, converse sobre o trabalho e confira as alterações antes de confirmar.'],
];

export function GuidedTour({ onClose }: { onClose: () => void }) {
    const [step, setStep] = useState(0);
    useEffect(() => { window.localStorage.setItem('rd-tour-started', '1'); }, []);
    const final = step === steps.length - 1;
    return <div className="tour-overlay" role="dialog" aria-modal="true" aria-labelledby="tour-title"><div className="tour-popover"><button className="tour-close" type="button" onClick={onClose} aria-label="Fechar tour"><X size={15} /></button><span className="eyebrow">GUIA PADRÃO RD · {step + 1}/{steps.length}</span><h2 id="tour-title">{steps[step][0]}</h2><p>{steps[step][1]}</p><div className="tour-dots">{steps.map((_, index) => <span className={index === step ? 'active' : ''} key={index} />)}</div><div className="tour-actions">{step > 0 ? <button className="button button-subtle" type="button" onClick={() => setStep((value) => value - 1)}><ArrowLeft size={14} /> Voltar</button> : <button className="button button-subtle" type="button" onClick={onClose}>Pular</button>}<button className="button button-primary" type="button" onClick={() => final ? onClose() : setStep((value) => value + 1)}>{final ? <><Check size={14} /> Concluir</> : <>Próximo <ArrowRight size={14} /></>}</button></div></div></div>;
}
