import { expect, test } from '@playwright/test';
import { loginAs } from './helpers';

test('pipeline combines filters with inline and bulk priority controls without hijacking typing shortcuts', async ({ page }, testInfo) => {
    await loginAs(page);
    await page.goto('/pipeline?view=list');

    const selectAll = page.getByLabel('Selecionar todos os casos visíveis');
    await expect(selectAll).toBeVisible();
    const horizonPriority = page.getByLabel('Prioridade de Conferência Horizonte 2026');
    const initialPriority = await horizonPriority.inputValue();
    const bulkPriority = initialPriority === 'high' ? 'low' : 'high';
    await selectAll.check();

    const bulkActions = page.getByRole('region', { name: 'Ações para casos selecionados' });
    await expect(bulkActions).toContainText(/casos selecionados/);
    await page.getByLabel('Aplicar prioridade aos casos selecionados').selectOption(bulkPriority);
    await page.getByRole('button', { name: 'Aplicar prioridade' }).click();
    await expect(page.getByRole('status')).toContainText('Prioridade atualizada');

    await expect(horizonPriority).toHaveValue(bulkPriority);
    const undo = page.getByRole('region', { name: 'Última alteração de prioridade' });
    await expect(undo).toContainText('Prioridade atualizada');
    await undo.getByRole('button', { name: 'Desfazer alteração', exact: true }).click();
    await expect(page.getByRole('status')).toContainText('Alteração de prioridade desfeita');
    await expect(horizonPriority).toHaveValue(initialPriority);

    const inlinePriority = initialPriority === 'normal' ? 'low' : 'normal';
    await horizonPriority.selectOption(inlinePriority);
    await expect(page.getByRole('status')).toContainText('Prioridade atualizada.');
    await expect(horizonPriority).toHaveValue(inlinePriority);

    const search = page.getByLabel('Buscar oportunidade');
    await search.fill('Horizonte');
    await expect(page).toHaveURL(/q=Horizonte/);
    await page.getByLabel('Prioridade do pipeline').selectOption(inlinePriority);
    await expect(page).toHaveURL(new RegExp(`q=Horizonte.*priority=${inlinePriority}|priority=${inlinePriority}.*q=Horizonte`));
    await expect(page.getByRole('row', { name: /Conferência Horizonte 2026/ })).toBeVisible();

    await search.press('Meta+K');
    await expect(page.getByRole('dialog', { name: 'Busca rápida' })).not.toBeVisible();
    await search.blur();
    await page.keyboard.press('Meta+K');
    await expect(page.getByRole('dialog', { name: 'Busca rápida' })).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(page.getByRole('dialog', { name: 'Busca rápida' })).not.toBeVisible();

    await search.focus();
    await search.press('Meta+Shift+A');
    await expect(selectAll).not.toBeChecked();
    await search.blur();
    await page.keyboard.press('Meta+Shift+A');
    await expect(selectAll).toBeChecked();
    await page.keyboard.press('Escape');
    await expect(selectAll).not.toBeChecked();

    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBeTruthy();
    await page.screenshot({ path: testInfo.outputPath('pipeline-operations.png'), fullPage: true });
});
