import { expect, test } from '@playwright/test';
import { loginAs } from './helpers';

test('assistant opens as a rounded conversation and retains messages', async ({ page }, testInfo) => {
    await loginAs(page);
    await page.getByRole('button', { name: 'Abrir assistente' }).click();
    const dialog = page.getByRole('dialog', { name: 'Assistente Padrão RD' });
    await expect(dialog).toBeVisible();
    await expect(dialog).toHaveCSS('opacity', '1');
    expect(await dialog.evaluate(e => parseFloat(getComputedStyle(e).borderTopLeftRadius))).toBeGreaterThanOrEqual(20);
    await page.getByLabel('Sua mensagem').fill('Como organizar meu próximo evento?');
    await page.getByRole('button', { name: 'Enviar mensagem' }).click();
    await expect(dialog.getByText('Como organizar meu próximo evento?', { exact: true }).last()).toBeVisible();
    await expect(dialog.getByText(/Nenhuma alteração foi realizada|Demonstração: posso orientar/).last()).toBeVisible();
    await page.keyboard.press('Escape');
    await page.reload();
    await page.getByRole('button', { name: 'Abrir assistente' }).click();
    await expect(dialog.getByText('Como organizar meu próximo evento?', { exact: true }).last()).toBeVisible();
    await expect(dialog).toHaveCSS('opacity', '1');
    const box = await dialog.boundingBox();
    expect(box!.x).toBeGreaterThanOrEqual(0);
    expect(box!.y).toBeGreaterThanOrEqual(0);
    expect(box!.y + box!.height).toBeLessThanOrEqual(page.viewportSize()!.height + 1);
    expect(box!.x + box!.width).toBeLessThanOrEqual(page.viewportSize()!.width + 1);
    await page.screenshot({ path: testInfo.outputPath('assistant.png'), animations: 'disabled' });
});

test('activity confirmation uses the app dialog instead of the browser popup', async ({ page }, testInfo) => {
    await loginAs(page);
    await page.goto('/agenda');
    await page.getByRole('button', { name: 'Nova atividade', exact: true }).click();
    await page.getByLabel('Atividade', { exact: true }).fill('Conferir confirmação visual');
    await page.getByRole('button', { name: 'Criar atividade', exact: true }).click();
    await page.getByRole('button', { name: 'Fechar painel' }).click();
    await page.reload();
    await page.locator('.agenda-activity-main').first().click();
    page.on('dialog', dialog => dialog.dismiss());
    await page.getByRole('button', { name: 'Cancelar atividade', exact: true }).click();
    const confirm = page.getByRole('dialog', { name: 'Confirmar ação' });
    await expect(confirm).toBeVisible();
    expect(await confirm.evaluate(e => parseFloat(getComputedStyle(e).borderRadius))).toBeGreaterThanOrEqual(20);
    await page.screenshot({ path: testInfo.outputPath('confirmation.png'), animations: 'disabled' });
    await confirm.getByRole('button', { name: 'Voltar' }).click();
    await expect(confirm).not.toBeVisible();
});
