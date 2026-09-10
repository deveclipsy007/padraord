import { type Datum, describeSeries, formatInteger, scaleTo, seriesColor } from './chart-utils';

type Props = {
    title: string;
    data: Datum[];
    format?: (value: number) => string;
    emptyMessage?: string;
    /** Uma cor por barra em vez da cor única da série. */
    categorical?: boolean;
};

/**
 * Barras horizontais. Horizontal porque os rótulos aqui são nomes de etapa e
 * de pessoa — em barra vertical eles giram ou truncam, e isso quebra no
 * celular. Sem dado, mostra estado vazio em vez de eixo vazio.
 */
export function BarSeries({ title, data, format = formatInteger, emptyMessage = 'Sem dados no período.', categorical = false }: Props) {
    const meaningful = data.filter((datum) => Number.isFinite(datum.value));
    const total = meaningful.reduce((carry, datum) => carry + Math.max(0, datum.value), 0);

    if (meaningful.length === 0 || total === 0) {
        return (
            <div className="rd-chart rd-chart--empty">
                <p>{emptyMessage}</p>
            </div>
        );
    }

    const scale = scaleTo(meaningful.map((datum) => datum.value));

    return (
        <div className="rd-chart rd-chart--bars">
            <div role="img" aria-label={`${title}. ${describeSeries(meaningful, format)}`} className="rd-chart__plot">
                {meaningful.map((datum, index) => {
                    const ratio = scale(datum.value);
                    return (
                        <div className="rd-bar-row" key={`${datum.label}-${index}`}>
                            <span className="rd-bar-row__label" title={datum.label}>
                                {datum.label}
                            </span>
                            <span className="rd-bar-row__track">
                                <span
                                    className="rd-bar-row__fill"
                                    style={{
                                        width: `${Math.max(ratio * 100, datum.value > 0 ? 2 : 0)}%`,
                                        background: categorical ? seriesColor(datum.seriesIndex ?? index) : 'var(--rd-series-1)',
                                    }}
                                />
                            </span>
                            <span className="rd-bar-row__value">{format(datum.value)}</span>
                        </div>
                    );
                })}
            </div>
            <table className="sr-only">
                <caption>{title}</caption>
                <thead>
                    <tr>
                        <th scope="col">Item</th>
                        <th scope="col">Valor</th>
                    </tr>
                </thead>
                <tbody>
                    {meaningful.map((datum, index) => (
                        <tr key={`${datum.label}-row-${index}`}>
                            <th scope="row">{datum.label}</th>
                            <td>{format(datum.value)}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
