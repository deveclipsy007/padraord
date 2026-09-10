import { renderToStaticMarkup } from 'react-dom/server';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

import { BentoGrid, BentoItem } from '../ui/BentoGrid';
import { PageHeader } from '../ui/PageHeader';
import { StatusBadge } from '../ui/StatusBadge';
import { Surface } from '../ui/Surface';

describe('clean iOS visual system', () => {
    it('uses a bundled product typeface and high-contrast rail navigation', () => {
        const cssRoot = fileURLToPath(new URL('../../../css/', import.meta.url));
        const tokens = readFileSync(`${cssRoot}tokens.css`, 'utf8');
        const shell = readFileSync(`${cssRoot}shell.css`, 'utf8');

        expect(tokens).toContain('--rd-font-ui: "Manrope Variable"');
        expect(tokens).toContain('--rd-font-display: "Manrope Variable"');
        expect(shell).toContain('--rail-icon: #3f4858');
        expect(shell).toContain('--rail-icon-active: #17181d');
    });

    it('renders an accent surface with explicit depth and padding', () => {
        const html = renderToStaticMarkup(
            <Surface tone="accent" padding="lg" interactive>
                Conteúdo
            </Surface>,
        );

        expect(html).toContain('ui-surface ui-surface--accent ui-surface--pad-lg is-interactive');
    });

    it('keeps semantic state separate from visual accent', () => {
        const html = renderToStaticMarkup(<StatusBadge tone="warning">Cotação vencida</StatusBadge>);

        expect(html).toContain('status-badge status-badge--warning');
        expect(html).toContain('Cotação vencida');
    });

    it('renders responsive bento spans as a typed contract', () => {
        const html = renderToStaticMarkup(
            <BentoGrid>
                <BentoItem colSpan={2} rowSpan={2}>
                    Operação hoje
                </BentoItem>
            </BentoGrid>,
        );

        expect(html).toContain('bento-grid');
        expect(html).toContain('bento-item bento-item--cols-2 bento-item--rows-2');
    });

    it('exposes one primary action and optional contextual copy in the page header', () => {
        const html = renderToStaticMarkup(
            <PageHeader
                eyebrow="Comercial"
                title="Pipeline"
                description="Acompanhe o próximo passo de cada oportunidade."
                primaryAction={<button type="button">Nova oportunidade</button>}
            />,
        );

        expect(html).toContain('<h1>Pipeline</h1>');
        expect(html).toContain('Nova oportunidade');
        expect(html).toContain('Acompanhe o próximo passo');
    });

    it('places tablet context navigation above content instead of covering it', () => {
        const cssRoot = fileURLToPath(new URL('../../../css/', import.meta.url));
        const shell = readFileSync(`${cssRoot}shell.css`, 'utf8');

        expect(shell).toContain('grid-template-rows: auto minmax(0,1fr)');
        expect(shell).toContain('.sidebar.app-rail { grid-row: 1 / span 2; }');
        expect(shell).toContain('.context-rail + .main-content { grid-column: 2; grid-row: 2; }');
    });

    it('keeps the operational board contained and gives primary actions the product accent', () => {
        const cssRoot = fileURLToPath(new URL('../../../css/', import.meta.url));
        const components = readFileSync(`${cssRoot}components.css`, 'utf8');
        const modules = readFileSync(`${cssRoot}modules.css`, 'utf8');

        expect(components).toContain('background: linear-gradient(135deg,var(--rd-violet)');
        expect(modules).toContain('.pipeline-board-shell');
        expect(modules).toContain('overflow: hidden');
        expect(modules).toContain('.pipeline-full { width: 100%; max-width: 100%;');
    });

    it('styles search and checkpoints independently from legacy form containers', () => {
        const cssRoot = fileURLToPath(new URL('../../../css/', import.meta.url));
        const controls = readFileSync(`${cssRoot}controls.css`, 'utf8');
        const modules = readFileSync(`${cssRoot}modules.css`, 'utf8');

        expect(controls).toContain('appearance:none');
        expect(controls).toContain('linear-gradient(135deg,#8462f6,#6f4ee8)');
        expect(modules).toContain('.directory-search');
        expect(modules).toContain('.client-directory');
    });
});
