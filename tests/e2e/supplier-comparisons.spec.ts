import { expect, test } from '@playwright/test';
import { loginAs } from './helpers';

test('quotation comparison records an explainable decision without horizontal overflow', async ({ page }, testInfo) => {
    const suffix = testInfo.project.name;
    const suppliers = [`Luz ${suffix}`, `Som ${suffix}`];

    await loginAs(page);
    await page.goto('/suppliers');
    for (const name of suppliers) {
        await page.getByRole('button', { name: 'Novo fornecedor' }).click();
        const dialog = page.getByRole('dialog', { name: 'Novo fornecedor' });
        await dialog.getByLabel('Nome').fill(name);
        await dialog.getByLabel('Serviço').fill('Audiovisual');
        await dialog.getByRole('button', { name: 'Salvar fornecedor' }).click();
    }

    await page.goto('/suppliers?tab=quotes');
    for (const [index, name] of suppliers.entries()) {
        await page.getByRole('button', { name: 'Registrar cotação' }).click();
        const dialog = page.getByRole('dialog', { name: 'Registrar cotação' });
        await dialog.getByLabel('Fornecedor').selectOption({ label: name });
        await dialog.getByLabel('Caso').selectOption({ label: 'Conferência Horizonte 2026' });
        await dialog.getByLabel('Serviço / escopo').fill('Operação audiovisual');
        await dialog.getByLabel('O preço recebido é').selectOption('total');
        await dialog.getByLabel('Preço recebido (R$)').fill(index === 0 ? '15000,00' : '14500,00');
        await dialog.getByLabel('Quantidade coberta').fill('1');
        await dialog.getByLabel('Unidade').fill('pacote');
        await dialog.getByLabel('Válida até').fill('2030-01-01');
        await dialog.getByLabel('Mensagem / evidência').fill('Proposta recebida e conferida.');
        await dialog.getByRole('button', { name: 'Salvar cotação' }).click();
    }

    for (const name of suppliers) {
        await page.getByRole('checkbox', { name: `Comparar ${name} · Operação audiovisual` }).check();
    }
    const compare = page.getByRole('button', { name: 'Registrar comparação e decisão' });
    await compare.focus();
    await expect(compare).toBeFocused();
    await compare.click();

    const dialog = page.getByRole('dialog', { name: 'Registrar comparação e decisão' });
    await dialog.getByLabel('Alternativa escolhida').selectOption({ index: 1 });
    await dialog.getByLabel('Justificativa da decisão').fill('A alternativa escolhida mantém o escopo e reduz o custo total comparável.');
    await dialog.getByRole('button', { name: 'Registrar decisão' }).click();

    const history = page.locator('section').filter({ has: page.getByRole('heading', { name: 'Histórico de comparações' }) });
    await expect(history.getByRole('heading', { name: 'Histórico de comparações' })).toBeVisible();
    await expect(history).toContainText('Decisão registrada');
    await expect(history.getByRole('link', { name: 'Exportar registro' }).first()).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBeTruthy();
    await page.screenshot({ path: testInfo.outputPath('supplier-comparison.png'), fullPage: true });
});
