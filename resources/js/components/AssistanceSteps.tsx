export function AssistanceSteps({ current }: { current: number }) {
    return (
        <ol className="assistance-steps" aria-label="Seu caminho no caso">
            {[
                'Enviar contexto',
                'Conferir entendimento',
                'Resolver pendências',
                'Preparar entrega',
                'Revisar decisões',
                'Acompanhar trabalho',
            ].map((s, i) => (
                <li key={s} className={current === i ? 'current' : ''} aria-current={current === i ? 'step' : undefined}>
                    {i + 1}. {s}
                </li>
            ))}
        </ol>
    );
}
