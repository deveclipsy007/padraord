import { Head } from '@inertiajs/react';
import { useState } from 'react';
import {
    ArrowDown,
    ArrowUpRight,
    BriefcaseBusiness,
    CalendarCheck2,
    Check,
    CircleDot,
    Crown,
    Layers3,
    LockKeyhole,
    Orbit,
    Send,
    ShieldCheck,
    Sparkles,
    Users,
    Wallet,
} from 'lucide-react';
import { AppLayout } from '../layout';
const profiles = [
    {
        key: 'direction',
        label: 'Direção',
        icon: Crown,
        question: 'O que merece minha atenção hoje?',
        reply: 'Uma leitura da operação: decisões que aguardam você, clientes que precisam de retorno e eventos que pedem atenção.',
        actions: ['Decisões do dia', 'Visão dos clientes', 'Riscos da operação'],
    },
    {
        key: 'commercial',
        label: 'Comercial',
        icon: BriefcaseBusiness,
        question: 'Qual cliente precisa do próximo contato?',
        reply: 'O contexto da negociação, o último encaminhamento e as informações que faltam para avançar — reunidos na mesma conversa.',
        actions: ['Próximos contatos', 'Briefings pendentes', 'Propostas em revisão'],
    },
    {
        key: 'production',
        label: 'Produção',
        icon: Users,
        question: 'O que precisamos resolver antes do evento?',
        reply: 'Tarefas sob sua responsabilidade, confirmações de fornecedores e impedimentos da execução, de acordo com o seu acesso.',
        actions: ['Minha programação', 'Confirmações', 'Impedimentos'],
    },
    {
        key: 'finance',
        label: 'Financeiro',
        icon: Wallet,
        question: 'O que precisa de conferência no financeiro?',
        reply: 'Vencimentos, documentos pendentes e valores registrados no sistema para você conferir antes de qualquer decisão.',
        actions: ['Vencimentos', 'Conferências', 'Documentos'],
    },
];
export default function Orbital() {
    const [profile, setProfile] = useState(0);
    const selected = profiles[profile];
    return (
        <AppLayout>
            <Head title="Agente Orbital RD" />
            <div className="orbital-page">
                <header className="orbital-heading">
                    <span className="rd-eyebrow">O PRÓXIMO CAPÍTULO / PADRÃO RD ONE</span>
                    <span className="orbital-lock">
                        <LockKeyhole size={13} /> Módulo adicional · em proposta
                    </span>
                </header>
                <section className="orbital-hero">
                    <div className="orbital-hero-copy">
                        <span className="orbital-product">
                            <Orbit size={20} /> AGENTE ORBITAL RD
                        </span>
                        <h1>
                            A operação inteira.
                            <br />
                            <em>Mais perto de você.</em>
                        </h1>
                        <p>
                            Um assessor diário no Telegram, conectado ao contexto do RD. Para saber onde olhar, o que decidir e qual é o
                            próximo passo — mesmo longe do painel.
                        </p>
                        <a className="orbital-explore" href="#orbital-proposta">
                            Conhecer o próximo módulo <ArrowDown size={16} />
                        </a>
                        <small>Apresentação conceitual. Integração ainda não disponível.</small>
                    </div>
                    <div className="orbital-system" aria-label="Ilustração do assessor conectado aos módulos RD">
                        <div className="orbital-ring orbital-ring--one" />
                        <div className="orbital-ring orbital-ring--two" />
                        <div className="orbital-core">
                            <Orbit size={52} strokeWidth={1} />
                            <span>
                                ORBITAL<span>RD</span>
                            </span>
                        </div>
                        <span className="orbital-satellite orbital-satellite--a">
                            <Users size={19} /> Clientes
                        </span>
                        <span className="orbital-satellite orbital-satellite--b">
                            <CalendarCheck2 size={19} /> Operação
                        </span>
                        <span className="orbital-satellite orbital-satellite--c">
                            <Wallet size={19} /> Financeiro
                        </span>
                        <span className="orbital-telegram">
                            <Send size={19} /> No seu Telegram
                        </span>
                    </div>
                </section>
                <section className="orbital-preview">
                    <div className="orbital-preview-intro">
                        <span className="rd-eyebrow">UMA CONVERSA, O CONTEXTO CERTO</span>
                        <h2>
                            O mesmo RD.
                            <br />
                            <em>A perspectiva de cada pessoa.</em>
                        </h2>
                        <p>
                            Explore exemplos do que cada perfil poderá consultar. As respostas serão limitadas às informações que aquela
                            pessoa tem permissão para acessar.
                        </p>
                        <div className="orbital-profiles" role="group" aria-label="Exemplo por perfil">
                            {profiles.map((p, i) => (
                                <button key={p.key} aria-pressed={profile === i} onClick={() => setProfile(i)}>
                                    <p.icon size={17} />
                                    {p.label}
                                </button>
                            ))}
                        </div>
                        <div className="orbital-safety">
                            <ShieldCheck size={20} />
                            <p>Dados do próprio sistema. Acesso por perfil. Ações importantes com conferência humana.</p>
                        </div>
                    </div>
                    <div className="orbital-chat">
                        <header>
                            <span className="orbital-avatar">
                                <Orbit size={24} />
                            </span>
                            <div>
                                <strong>Orbital RD</strong>
                                <small>Prévia ilustrativa · {selected.label}</small>
                            </div>
                            <LockKeyhole size={17} />
                        </header>
                        <div className="orbital-chat-body" key={selected.key}>
                            <small className="orbital-demo-label">EXEMPLO DE EXPERIÊNCIA FUTURA</small>
                            <div className="orbital-message orbital-message--user">{selected.question}</div>
                            <div className="orbital-message orbital-message--agent">
                                <span>
                                    <Sparkles size={13} /> ORBITAL RD
                                </span>
                                <p>{selected.reply}</p>
                                <div className="orbital-chat-options">
                                    {selected.actions.map((a) => (
                                        <span key={a}>
                                            {a}
                                            <ArrowUpRight size={12} />
                                        </span>
                                    ))}
                                </div>
                            </div>
                        </div>
                        <footer>
                            <LockKeyhole size={14} />
                            <span>Conversa disponível após implantação</span>
                            <Send size={17} />
                        </footer>
                    </div>
                </section>
                <section className="orbital-locked-workspace" aria-label="Prévia bloqueada do módulo">
                    <div className="orbital-ghost" aria-hidden="true">
                        <div>
                            <span>Resumo diário</span>
                            <h3>Clareza para começar.</h3>
                            <i />
                            <i />
                            <i />
                        </div>
                        <div>
                            <span>Contexto conectado</span>
                            <h3>Clientes e projetos.</h3>
                            <i />
                            <i />
                            <i />
                        </div>
                        <div>
                            <span>Próximas decisões</span>
                            <h3>O que move a operação.</h3>
                            <i />
                            <i />
                            <i />
                        </div>
                    </div>
                    <div className="orbital-unlock">
                        <span className="orbital-lock-icon">
                            <LockKeyhole size={24} />
                        </span>
                        <div>
                            <h2>Seu próximo nível de inteligência operacional.</h2>
                            <p>
                                Este módulo está reservado para uma próxima etapa. Implantação, perfis de acesso e condições serão definidos
                                em uma proposta adicional.
                            </p>
                        </div>
                        <a href="#orbital-proposta">
                            Ver o que está previsto <ArrowDown size={16} />
                        </a>
                    </div>
                </section>
                <section id="orbital-proposta" className="orbital-proposal">
                    <div>
                        <span className="rd-eyebrow">O QUE PODEREMOS CONSTRUIR JUNTOS</span>
                        <h2>
                            Um assessor que conhece
                            <br />
                            <em>o seu contexto.</em>
                        </h2>
                        <p>
                            Uma extensão do RD para acompanhar a rotina. A proposta final definirá fontes de dados, frequência dos resumos,
                            ações permitidas e custos de operação.
                        </p>
                    </div>
                    <div className="orbital-capabilities">
                        {[
                            {
                                icon: CalendarCheck2,
                                title: 'Seu resumo do dia',
                                text: 'Pendências e prioridades reunidas em uma conversa, nos horários acordados.',
                            },
                            {
                                icon: Layers3,
                                title: 'Continuidade entre os módulos',
                                text: 'Relacionar cliente, briefing, proposta e produção sem reconstruir o contexto a cada pergunta.',
                            },
                            {
                                icon: CircleDot,
                                title: 'Atenção no momento certo',
                                text: 'Sinalizar mudanças e pontos que precisam de decisão, com critérios definidos pela equipe.',
                            },
                            {
                                icon: ShieldCheck,
                                title: 'Controle em cada etapa',
                                text: 'Identificação do usuário, permissões e registro das ações. Conferência antes de alterações importantes.',
                            },
                        ].map((c) => (
                            <article key={c.title}>
                                <c.icon size={22} />
                                <div>
                                    <h3>{c.title}</h3>
                                    <p>{c.text}</p>
                                </div>
                            </article>
                        ))}
                    </div>
                </section>
                <footer className="orbital-final">
                    <Check size={16} />
                    <span>O sistema atual segue disponível. O Orbital é uma expansão opcional, com escopo e contratação próprios.</span>
                </footer>
            </div>
        </AppLayout>
    );
}
