export type Datum = { label: string; value: number; hint?: string; seriesIndex?: number };

export const SERIES_COUNT = 6;

/** Cor categórica por posição. A ordem é fixa: mudar realoca cores já vistas. */
export function seriesColor(index: number): string {
    return `var(--rd-series-${(index % SERIES_COUNT) + 1})`;
}

export function formatInteger(value: number): string {
    return new Intl.NumberFormat('pt-BR').format(value);
}

export function formatCurrencyFromCents(cents: number): string {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(cents / 100);
}

export function formatPercent(ratio: number): string {
    return new Intl.NumberFormat('pt-BR', { style: 'percent', maximumFractionDigits: 1 }).format(ratio);
}

/**
 * Escala para o maior valor da série. Série inteira em zero devolve 0 em vez
 * de barras cheias — um gráfico não pode sugerir volume onde não há dado.
 */
export function scaleTo(values: number[]): (value: number) => number {
    const max = Math.max(0, ...values);
    return (value: number) => (max === 0 ? 0 : Math.max(0, value) / max);
}

export function sum(values: number[]): number {
    return values.reduce((total, value) => total + value, 0);
}

/** Descrição textual da série, para leitor de tela e para o resumo do gráfico. */
export function describeSeries(data: Datum[], format: (value: number) => string): string {
    if (data.length === 0) return 'Sem dados.';
    return data.map((datum) => `${datum.label}: ${format(datum.value)}`).join('; ') + '.';
}
