type Props = {
    title: string;
    plannedLabel?: string;
    actualLabel?: string;
    plannedCents: number;
    actualCents: number;
    /** Acima deste desvio a leitura passa a exigir atenção. */
    toleranceRatio?: number;
    provisional?: boolean;
};

const money = (cents: number) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(cents / 100);

/**
 * Previsto contra realizado. Estourar o previsto é atenção, gastar menos não é
 * — a leitura precisa ser direcional, não simétrica. Nunca chama o resultado de
 * final enquanto o pós-evento não fechou.
 */
export function ComparisonMeter({
    title,
    plannedLabel = 'Previsto',
    actualLabel = 'Realizado',
    plannedCents,
    actualCents,
    toleranceRatio = 0.1,
    provisional = false,
}: Props) {
    if (plannedCents <= 0 && actualCents <= 0) {
        return (
            <div className="rd-chart rd-chart--empty">
                <p>Ainda não há valores previstos nem realizados.</p>
            </div>
        );
    }

    const ceiling = Math.max(plannedCents, actualCents, 1);
    const varianceCents = actualCents - plannedCents;
    const varianceRatio = plannedCents > 0 ? varianceCents / plannedCents : 0;
    const over = varianceCents > 0 && Math.abs(varianceRatio) > toleranceRatio;

    return (
        <div className="rd-chart rd-chart--comparison">
            <div
                role="img"
                aria-label={`${title}. ${plannedLabel}: ${money(plannedCents)}. ${actualLabel}: ${money(actualCents)}. Desvio de ${money(Math.abs(varianceCents))}${varianceCents > 0 ? ' acima' : varianceCents < 0 ? ' abaixo' : ''}.`}
            >
                <div className="rd-comparison-row">
                    <span className="rd-comparison-row__label">{plannedLabel}</span>
                    <span className="rd-comparison-row__track">
                        <span className="rd-comparison-row__fill is-planned" style={{ width: `${(plannedCents / ceiling) * 100}%` }} />
                    </span>
                    <span className="rd-comparison-row__value">{money(plannedCents)}</span>
                </div>
                <div className="rd-comparison-row">
                    <span className="rd-comparison-row__label">{actualLabel}</span>
                    <span className="rd-comparison-row__track">
                        <span
                            className={`rd-comparison-row__fill ${over ? 'is-over' : 'is-actual'}`}
                            style={{ width: `${(actualCents / ceiling) * 100}%` }}
                        />
                    </span>
                    <span className="rd-comparison-row__value">{money(actualCents)}</span>
                </div>
            </div>
            <p className={`rd-comparison-note${over ? ' is-over' : ''}`}>
                {varianceCents === 0
                    ? 'Realizado igual ao previsto.'
                    : `${money(Math.abs(varianceCents))} ${varianceCents > 0 ? 'acima' : 'abaixo'} do previsto.`}
                {provisional ? ' Valor provisório: o pós-evento ainda não foi encerrado.' : ''}
            </p>
        </div>
    );
}
