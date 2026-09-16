import { expect, test } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { loginAs } from './helpers';

test('production rooms expose only the current tools and retain drafts', async ({ page }, testInfo) => {
    await loginAs(page);
    await page.goto('/production/events/1');
    const areas = page.getByRole('navigation', { name: 'Áreas da produção' });
    await expect(page.getByRole('heading', { name: 'Adicionar tarefa', exact: true })).toBeVisible();
    await expect(page.getByLabel('Referência', { exact: true })).not.toBeVisible();
    await areas.getByRole('button', { name: /02 Preparação/ }).click();
    await page.getByLabel('Referência', { exact: true }).fill('Planta técnica em revisão');
    await areas.getByRole('button', { name: /03 Operação/ }).click();
    await expect(page.getByRole('heading', { name: 'Reservar responsável', exact: true })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Criar checklist', exact: true })).not.toBeVisible();
    await page.getByRole('button', { name: 'Checklists', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Criar checklist', exact: true })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Reservar responsável', exact: true })).not.toBeVisible();
    await page.getByRole('button', { name: 'Fornecedores e ordens', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Ordens de serviço', exact: true })).toBeVisible();
    await areas.getByRole('button', { name: /02 Preparação/ }).click();
    await expect(page.getByLabel('Referência', { exact: true })).toHaveValue('Planta técnica em revisão');
    await areas.getByRole('button', { name: /04 Arquivos/ }).click();
    await expect(page.locator('#production-area-files')).toBeVisible();
    await expect(page.locator('#production-area-tasks')).not.toBeVisible();
    for (const url of ['/production', '/production/events/1', '/']) {
        await page.goto(url);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBeTruthy();
        const audit = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
        expect(audit.violations.filter((v) => ['serious', 'critical'].includes(v.impact || ''))).toEqual([]);
        await page.screenshot({
            path: testInfo.outputPath(url === '/' ? 'home.png' : url === '/production' ? 'library.png' : 'tasks.png'),
            fullPage: true,
        });
    }
});
