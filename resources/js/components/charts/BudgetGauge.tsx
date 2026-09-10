type Props = {
    title: string;
    /** Já cobrado e conhecido. */
    usedUsd: number;
    /** Reservado, sem confirmação de cobrança. */
    reservedUsd: number;
    limitUsd: number;
};

const money = (value: number) => `US$ ${value.toFixed(4)}`;

/**
 * Consumo contra teto. Mantém o já cobrado separado do reservado porque a
 * reserva ainda pode não virar cobrança — somar os dois num número só faria o
 * painel afirmar um gasto que ninguém confirmou.
 */
export function BudgetGauge({ title, usedUsd, reservedUsd, limitUsd }: Props) {
    if (limitUsd <= 0) {
        return (
            <div className="rd-chart rd-chart--empty">
                <p>Defina o limite mensal para acompanhar o consumo.</p>
            </div>
        );
    }

    const usedRatio = Math.min(usedUsd / limitUsd, 1);
    const reservedRatio = Math.min(Math.max(reservedUsd, 0) / limitUsd, 1 - usedRatio);
    const committed = usedUsd + reservedUsd;
    const exceeded = committed >= limitUsd;

    return (
        <div className="rd-chart rd-chart--gauge">
            <div
                role="img"
                aria-label={`${title}. Cobrado ${money(usedUsd)}, reservado ${money(reservedUsd)}, limite ${money(limitUsd)}.`}
                className="rd-gauge__track"
            >
                <span className="rd-gauge__fill is-used" style={{ width: `${usedRatio * 100}%` }} />
                <span className="rd-gauge__fill is-reserved" style={{ width: `${reservedRatio * 100}%` }} />
            </div>
            <ul className="rd-gauge__legend">
                <li>
                    <span className="rd-gauge__key is-used" aria-hidden="true" />
                    Cobrado <strong>{money(usedUsd)}</strong>
                </li>
                <li>
                    <span className="rd-gauge__key is-reserved" aria-hidden="true" />
                    Reservado <strong>{money(reservedUsd)}</strong>
                </li>
                <li>
                    <span className="rd-gauge__key is-free" aria-hidden="true" />
                    Disponível <strong>{money(Math.max(0, limitUsd - committed))}</strong>
                </li>
            </ul>
            {exceeded && (
                <p className="rd-comparison-note is-over" role="status">
                    O limite mensal foi comprometido. Novos processamentos pagos ficam bloqueados até a revisão do teto.
                </p>
            )}
        </div>
    );
}
