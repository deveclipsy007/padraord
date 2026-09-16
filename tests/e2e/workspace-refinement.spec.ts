import { expect, test } from '@playwright/test';
import { loginAs } from './helpers';

test('kanban details keep keyboard focus and return to the originating card', async ({ page }) => {
    await loginAs(page);
    await page.goto('/pipeline');
    const card = page.getByRole('button', { name: 'Abrir detalhes de Festival Vértice' });
    await card.click();
    const dialog = page.getByRole('dialog', { name: 'Festival Vértice' });
    await expect(dialog.getByRole('button', { name: 'Fechar', exact: true })).toBeFocused();
    await page.keyboard.press('Shift+Tab');
    await expect(dialog.getByRole('link', { name: 'Abrir workspace' })).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(dialog.getByRole('button', { name: 'Fechar', exact: true })).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(dialog).not.toBeVisible();
    await expect(card).toBeFocused();
});

test('kanban expands and exposes every stage without overflowing the page', async ({ page }, testInfo) => {
    await loginAs(page);
    await page.goto('/pipeline');
    await page.getByRole('button', { name: 'Ampliar Kanban' }).click();
    await expect(page.getByRole('button', { name: 'Recolher Kanban' })).toBeVisible();
    await page.getByRole('button', { name: 'Ir para Viabilidade contratada' }).click();
    await expect(page.locator('[data-stage="viability_contracted"] .column-heading')).toBeInViewport();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBeTruthy();
    await page.screenshot({ path: testInfo.outputPath('kanban-expanded.png'), fullPage: true });
    await page.keyboard.press('Escape');
    await expect(page.getByRole('button', { name: 'Ampliar Kanban' })).toBeVisible();
});

test('projects prioritize real cases and keep demonstrations available on demand', async ({ page }, testInfo) => {
    await loginAs(page);
    await page.goto('/projects');
    await expect(page.getByRole('heading', { name: 'Projetos', exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: /Conferência Horizonte 2026/ }).first()).toBeVisible();
    await page.getByLabel('Buscar projeto').fill('Horizonte');
    await expect(page.getByRole('link', { name: /Festival Vértice/ })).toHaveCount(0);
    await expect(page.getByRole('button', { name: /Experimentar Conferência Horizonte/ })).not.toBeVisible();
    await page.getByText('Laboratório de demonstração', { exact: true }).click();
    await expect(page.getByRole('button', { name: /Experimentar Conferência Horizonte/ })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBeTruthy();
    await page.screenshot({ path: testInfo.outputPath('projects.png'), fullPage: true });
});
