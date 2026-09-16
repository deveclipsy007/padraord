import { expect, test } from '@playwright/test';
import { loginAs } from './helpers';

test('client folders keep chosen covers after reload and preserve directory actions', async ({ page }, testInfo) => {
    await loginAs(page);
    await page.goto('/clients');
    const folder = page.locator('.client-folder').first();
    await folder.getByRole('button', { name: /Personalizar capa/ }).click();
    const editor = page.getByRole('dialog', { name: 'Personalizar capa' });
    await editor.getByRole('button', { name: 'Duna', exact: true }).click();
    await editor.getByRole('button', { name: 'Salvar capa' }).click();
    await expect(editor).not.toBeVisible();
    await page.reload();
    await expect(folder).toHaveAttribute('data-cover', 'dune');
    await folder.getByRole('button', { name: /Personalizar capa/ }).click();
    await editor.locator('input[type="file"]').setInputFiles({
        name: 'cover.png', mimeType: 'image/png',
        buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aSAAAAABJRU5ErkJggg==', 'base64'),
    });
    await editor.getByRole('button', { name: 'Salvar capa' }).click();
    await expect(editor).not.toBeVisible();
    await page.reload();
    await expect(folder.locator('.folder-cover img')).toBeVisible();
    expect(await folder.locator('.folder-cover img').evaluate((img: HTMLImageElement) => img.naturalWidth)).toBeGreaterThan(0);
    await folder.getByRole('button', { name: /Personalizar capa/ }).click();
    await editor.getByRole('button', { name: 'Duna', exact: true }).click();
    await editor.getByRole('button', { name: 'Salvar capa' }).click();
    await expect(editor).not.toBeVisible();
    await expect(folder.getByRole('link', { name: /^Abrir / })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBeTruthy();
    await page.screenshot({ path: testInfo.outputPath('client-folders.png'), fullPage: true });
});

test('stage controls have a single arrow and cards retain independent click targets', async ({ page }, testInfo) => {
    await loginAs(page);
    await page.goto('/pipeline');
    const card = page.locator('.pipeline-card').first();
    await expect(card.locator('select')).toHaveCSS('appearance', 'none');
    await card.getByRole('button').click();
    await expect(page.getByRole('dialog')).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(page.getByRole('dialog')).not.toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBeTruthy();
    await page.screenshot({ path: testInfo.outputPath('editorial-kanban.png'), fullPage: true });
});
