import { expect, test } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { loginAs } from './helpers';

test('planning hands off to a distinct production workspace', async ({ page }, testInfo) => {
    await loginAs(page);
    await page
        .getByRole('navigation', { name: 'Áreas de trabalho' })
        .getByRole('link', { name: /EXECUTAR/ })
        .click();
    await expect(page).toHaveURL(/\/production$/);
    await page.getByRole('button', { name: 'Todos os projetos', exact: true }).click();
    await page.getByLabel('Buscar evento').fill('Horizonte');
    await page
        .locator('.production-event')
        .filter({ has: page.getByRole('heading', { name: 'Conferência Horizonte 2026', exact: true }) })
        .getByRole('link', { name: 'Abrir produção', exact: true })
        .click();
    await expect(page).toHaveURL(/\/production\/events\/1$/);
    await expect(page.locator('.context-rail__nav').getByRole('link', { name: 'Briefing', exact: true })).toHaveCount(0);
    await expect(page.getByRole('heading', { name: 'Controle de execução' })).toBeVisible();
    await page.getByRole('link', { name: /Abrir pós-evento/ }).click();
    await expect(page).toHaveURL(/\/production\/events\/1\/post-event$/);
    await page.goto('/opportunities/1');
    await expect(page.locator('.module-grid').getByRole('link', { name: /Produção|Pós-evento/ })).toHaveCount(0);
    await page.getByRole('link', { name: /Abrir Central de Produção/ }).click();
    await expect(page).toHaveURL(/\/production\/events\/1$/);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBeTruthy();
    await page.screenshot({ path: testInfo.outputPath('execution.png'), fullPage: true });
});

test('team job titles persist independently from access permissions', async ({ page }, testInfo) => {
    await loginAs(page);
    await page.goto('/team');
    await page.getByRole('button', { name: 'Adicionar pessoa', exact: true }).click();
    const dialog = page.getByRole('dialog', { name: 'Adicionar pessoa' });
    await dialog.getByLabel('Nome', { exact: true }).fill(`Pessoa ${testInfo.project.name}`);
    await dialog.getByLabel('E-mail', { exact: true }).fill(`person-${testInfo.project.name}@example.test`);
    await dialog.getByRole('button', { name: 'Financeiro', exact: true }).click();
    await dialog.getByLabel('Senha inicial (mínimo 12 caracteres)').fill('TemporaryPass123!');
    await dialog.getByRole('button', { name: 'Criar usuário' }).click();
    await expect(dialog).not.toBeVisible();
    await page.reload();
    const member = page.locator('.team-member-card').filter({ hasText: `Pessoa ${testInfo.project.name}` });
    await expect(member).toContainText('Financeiro');
    await expect(member).toContainText('Acesso à operação');
    await expect(member).toContainText('Sem aprovação comercial');
    for (const url of ['/team', '/production', '/']) {
        await page.goto(url);
        const audit = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
        expect(audit.violations.filter((v) => ['serious', 'critical'].includes(v.impact || ''))).toEqual([]);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBeTruthy();
        await page.screenshot({ path: testInfo.outputPath(url === '/' ? 'home.png' : `${url.slice(1)}.png`), fullPage: true });
    }
});
