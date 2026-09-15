import { expect, test } from '@playwright/test';
import { loginAs } from './helpers';

test('commercial workspace keeps scope, contract fields and actions readable at every supported viewport', async ({ page }, testInfo) => {
    await loginAs(page);

    await page.goto('/opportunities/1/documents');
    await expect(page.getByRole('heading', { name: 'Versões, fontes e decisões' })).toBeVisible();
    await expect(page.getByText('Proposta de Viabilidade', { exact: true })).toBeVisible();
    await expect(page.getByText('Contrato', { exact: true })).toBeVisible();

    await page.goto('/opportunities/1/proposal?purpose=viability');
    await expect(page.locator('h1', { hasText: 'Proposta comercial' })).toBeVisible();
    await expect(page.getByLabel('O que está incluído')).toBeVisible();
    await expect(page.getByLabel('Fora do escopo')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Salvar novo rascunho' })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBeTruthy();

    await page.goto('/opportunities/1/contract');
    await expect(page.getByRole('heading', { name: /Contrato · v1/ })).toBeVisible();
    await expect(page.getByLabel('Cláusulas')).toBeVisible();
    await expect(page.getByLabel('Finalidade')).toHaveValue('Contrato');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBeTruthy();
    await page.screenshot({ path: testInfo.outputPath('commercial-workspace.png'), fullPage: true });
});
