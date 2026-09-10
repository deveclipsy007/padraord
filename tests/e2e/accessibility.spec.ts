import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';
import { loginAs } from './helpers';

// Acessibilidade verificada por ferramenta, não por opinião. Só violação
// crítica ou séria reprova: as leves entram como dívida registrada, para o
// portão não virar ruído que a equipe aprende a ignorar.

const BLOCKING = ['critical', 'serious'];

async function audit(page: Page, name: string) {
    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();

    const blocking = results.violations.filter((violation) => BLOCKING.includes(violation.impact ?? ''));
    const detail = blocking
        .map((violation) => {
            const targets = violation.nodes
                .slice(0, 3)
                .map((node) => node.target.join(' '))
                .join(' | ');
            return `${violation.impact}: ${violation.id} — ${violation.help} (${targets})`;
        })
        .join('\n');

    expect(blocking.length, `${name} tem violação bloqueante de acessibilidade:\n${detail}`).toBe(0);
}

test('a tela de entrada é acessível', async ({ page }) => {
    await page.goto('/login');
    await audit(page, 'Login');
});

test('as telas de recuperação de acesso são acessíveis', async ({ page }) => {
    await page.goto('/forgot-password');
    await audit(page, 'Recuperar senha');
    await page.goto('/reset-password/token-de-exemplo?email=test%40example.com');
    await audit(page, 'Definir nova senha');
});

test('as telas operacionais principais são acessíveis', async ({ page }) => {
    await loginAs(page);

    for (const [path, name] of [
        ['/', 'Hoje'],
        ['/pipeline', 'Pipeline'],
        ['/clients', 'Clientes'],
        ['/suppliers', 'Fornecedores'],
        ['/agenda', 'Agenda'],
        ['/history', 'Histórico'],
    ] as const) {
        await page.goto(path);
        await audit(page, name);
    }
});

test('a administração de inteligência artificial é acessível', async ({ page }) => {
    await loginAs(page);
    await page.goto('/settings/ai');
    await audit(page, 'Inteligência artificial');
});
