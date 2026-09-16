import { expect, test } from '@playwright/test';
import { loginAs } from './helpers';

test('project dossiers and their modules stay readable and navigable', async ({ page }, testInfo) => {
    await loginAs(page);
    await page.goto('/projects');
    await page
        .getByRole('link', { name: /Conferência Horizonte 2026/ })
        .first()
        .click();
    for (const path of ['', '/journey', '/briefing']) {
        await page.goto(`/opportunities/1${path}`);
        await expect(page.locator('.case-dossier-header h1')).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBeTruthy();
        await page.screenshot({ path: testInfo.outputPath(`dossier${path.replace('/', '-') || '-overview'}.png`), fullPage: true });
    }
    await page.getByRole('button', { name: 'Abrir Agente RD' }).click();
    await expect(page.getByRole('dialog', { name: 'Agente RD' })).toBeVisible();
    await page.keyboard.press('Escape');
    await page.emulateMedia({ reducedMotion: 'reduce' });
    expect(
        await page
            .locator('.agent-signal i')
            .first()
            .evaluate((el) => getComputedStyle(el).animationName),
    ).toBe('none');
});

test('directory cards preserve contact editing and fit the viewport', async ({ page }, testInfo) => {
    await loginAs(page);
    await page.goto('/suppliers');
    await page.getByRole('button', { name: 'Novo fornecedor' }).click();
    await page.locator('dialog[open]').getByLabel('Nome', { exact: true }).fill(`Parceiro editorial ${testInfo.project.name}`);
    await page.getByRole('button', { name: 'Salvar fornecedor' }).click();
    await expect(page.locator('dialog[open]')).toHaveCount(0);
    await page.goto('/venues');
    await page.getByRole('button', { name: 'Novo local' }).click();
    await page.locator('dialog[open]').getByLabel('Nome', { exact: true }).fill(`Espaço editorial ${testInfo.project.name}`);
    await page.getByRole('button', { name: 'Cadastrar local' }).click();
    await expect(page).toHaveURL(/\/venues\/\d+/);
    for (const path of ['/suppliers', '/venues', '/agenda', '/history']) {
        await page.goto(path);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBeTruthy();
        await page.screenshot({ path: testInfo.outputPath(`${path.slice(1)}.png`), fullPage: true });
    }
    await page.goto('/suppliers');
    await page.locator('.partner-card').first().getByRole('button', { name: 'Editar', exact: true }).click();
    await expect(page.locator('dialog[open]')).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(page.locator('dialog[open]')).toHaveCount(0);
});
