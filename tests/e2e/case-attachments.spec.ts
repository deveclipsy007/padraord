import { expect, test } from '@playwright/test';
import { loginAs } from './helpers';

test('private case attachments can be uploaded, archived and restored without exposing storage details', async ({ page }, testInfo) => {
    await loginAs(page);
    await page.goto('/opportunities/1/documents');

    const attachments = page.getByRole('region', { name: 'Anexos privados' });
    await expect(attachments).toBeVisible();
    await expect(attachments.getByText(/de 200 MB usados/)).toBeVisible();

    await attachments.getByRole('button', { name: 'Adicionar anexo' }).click();
    const dialog = page.getByRole('dialog', { name: 'Adicionar anexo privado' });
    const filename = 'referencia-' + testInfo.project.name + '.txt';

    await dialog.getByLabel('Arquivo', { exact: true }).setInputFiles({
        name: filename,
        mimeType: 'text/plain',
        buffer: Buffer.from('Referência privada para a operação.'),
    });
    await dialog.getByLabel('Área do arquivo').selectOption('documents');
    await dialog.getByRole('button', { name: 'Guardar anexo' }).click();

    const row = attachments.getByRole('article', { name: filename });
    await expect(row).toBeVisible();
    await expect(row.getByText('TXT', { exact: true })).toBeVisible();
    await expect(row.getByRole('link', { name: 'Baixar' })).toHaveAttribute('href', /\/opportunities\/1\/attachments\/\d+$/);
    await expect(row).not.toContainText('case-attachments');
    const panelBounds = await attachments.boundingBox();
    const archiveBounds = await row.getByRole('button', { name: 'Arquivar' }).boundingBox();
    expect(archiveBounds?.x).toBeGreaterThanOrEqual(panelBounds?.x ?? 0);
    expect((archiveBounds?.x ?? 0) + (archiveBounds?.width ?? 0)).toBeLessThanOrEqual((panelBounds?.x ?? 0) + (panelBounds?.width ?? 0));

    await row.getByRole('button', { name: 'Arquivar' }).click();
    const archive = page.getByRole('dialog', { name: 'Arquivar anexo privado' });
    await archive.getByLabel('Motivo do arquivamento').fill('A referência será substituída.');
    await archive.getByRole('button', { name: 'Arquivar anexo' }).click();
    await expect(row.getByText('Arquivado')).toBeVisible();

    await row.getByRole('button', { name: 'Restaurar' }).click();
    await expect(row.getByText('Disponível')).toBeVisible();
    await expect(page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBeTruthy();
    await page.screenshot({ path: testInfo.outputPath('case-attachments.png'), fullPage: true });
});
