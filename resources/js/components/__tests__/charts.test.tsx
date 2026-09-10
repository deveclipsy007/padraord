import { describe, expect, it } from 'vitest';
import { renderToStaticMarkup } from 'react-dom/server';
import { BarSeries } from '../charts/BarSeries';
import { BudgetGauge } from '../charts/BudgetGauge';
import { ComparisonMeter } from '../charts/ComparisonMeter';
import { formatCurrencyFromCents, scaleTo, seriesColor } from '../charts/chart-utils';

describe('escala dos gráficos', () => {
    it('série inteira em zero não produz barra cheia', () => {
        const scale = scaleTo([0, 0, 0]);
        expect(scale(0)).toBe(0);
    });

    it('valor negativo não vira barra', () => {
        const scale = scaleTo([10, -5]);
        expect(scale(-5)).toBe(0);
        expect(scale(10)).toBe(1);
    });

    it('a paleta categórica dá a volta sem repetir na sequência curta', () => {
        const colours = [0, 1, 2, 3, 4, 5].map(seriesColor);
        expect(new Set(colours).size).toBe(6);
        expect(seriesColor(6)).toBe(seriesColor(0));
    });
});

describe('BarSeries', () => {
    it('mostra estado vazio em vez de eixo vazio quando tudo é zero', () => {
        const html = renderToStaticMarkup(
            <BarSeries title="Casos por etapa" data={[{ label: 'Lead', value: 0 }]} emptyMessage="Sem oportunidades ativas." />,
        );
        expect(html).toContain('Sem oportunidades ativas.');
        expect(html).not.toContain('rd-bar-row__fill');
    });

    it('descreve a série inteira para leitor de tela e oferece tabela alternativa', () => {
        const html = renderToStaticMarkup(
            <BarSeries
                title="Casos por etapa"
                data={[
                    { label: 'Lead', value: 3 },
                    { label: 'Proposta', value: 1 },
                ]}
            />,
        );
        expect(html).toContain('aria-label="Casos por etapa. Lead: 3; Proposta: 1."');
        expect(html).toContain('<table class="sr-only">');
        expect(html).toContain('<caption>Casos por etapa</caption>');
    });

    it('formata dinheiro a partir de centavos, sem arredondar para real', () => {
        const html = renderToStaticMarkup(
            <BarSeries title="Valor por etapa" data={[{ label: 'Proposta', value: 1234567 }]} format={formatCurrencyFromCents} />,
        );
        expect(html).toContain('12.345,67');
    });
});

describe('ComparisonMeter', () => {
    it('marca como atenção apenas quando o realizado estoura o previsto', () => {
        const over = renderToStaticMarkup(<ComparisonMeter title="Custo" plannedCents={100000} actualCents={150000} />);
        const under = renderToStaticMarkup(<ComparisonMeter title="Custo" plannedCents={100000} actualCents={50000} />);

        expect(over).toContain('is-over');
        expect(over).toContain('acima do previsto');
        // Gastar menos que o previsto não é um problema: a leitura é direcional.
        expect(under).not.toContain('is-over');
        expect(under).toContain('abaixo do previsto');
    });

    it('não chama o resultado de final enquanto o pós-evento não fechou', () => {
        const html = renderToStaticMarkup(<ComparisonMeter title="Custo" plannedCents={100000} actualCents={90000} provisional />);
        expect(html).toContain('provisório');
    });
});

describe('BudgetGauge', () => {
    it('mantém cobrado e reservado separados, porque reserva pode não virar cobrança', () => {
        const html = renderToStaticMarkup(<BudgetGauge title="Consumo" usedUsd={2} reservedUsd={1} limitUsd={10} />);

        expect(html).toContain('Cobrado');
        expect(html).toContain('Reservado');
        expect(html).toContain('Disponível');
        expect(html).toContain('US$ 7.0000');
    });

    it('avisa quando o limite do mês foi comprometido', () => {
        const html = renderToStaticMarkup(<BudgetGauge title="Consumo" usedUsd={8} reservedUsd={3} limitUsd={10} />);
        expect(html).toContain('limite mensal foi comprometido');
    });

    it('pede o limite em vez de desenhar um medidor sem escala', () => {
        const html = renderToStaticMarkup(<BudgetGauge title="Consumo" usedUsd={1} reservedUsd={0} limitUsd={0} />);
        expect(html).toContain('Defina o limite mensal');
    });
});
