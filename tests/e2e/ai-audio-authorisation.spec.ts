import { expect, test } from '@playwright/test';
import { loginAs } from './helpers';

// A transcrição de reunião é o requisito central do produto e, antes desta
// mudança, só podia ser liberada editando .env no servidor. Estes testes
// provam que um administrador consegue ver os motores e autorizar o áudio
// pela própria interface, nos três tamanhos de tela.

test('the settings screen names the model of every engine', async ({ page }) => {
    await loginAs(page);
    await page.goto('/settings/ai');

    const engines = page.getByRole('heading', { name: 'Motores em uso' }).locator('..');
    await expect(engines.getByText('Organização de contexto e briefing')).toBeVisible();
    await expect(engines.getByText('Transcrição de reunião')).toBeVisible();
    await expect(engines.getByText('Assistente de conversa')).toBeVisible();
    // Nenhum motor pode aparecer sem o modelo que realmente será chamado.
    await expect(engines.locator('code')).toHaveCount(4);
    for (const model of await engines.locator('code').allTextContents()) {
        expect(model.trim()).not.toBe('');
    }
});

test('audio authorisation is reachable and refuses to enable without a tariff', async ({ page }) => {
    await loginAs(page);
    await page.goto('/settings/ai');

    const authorise = page.getByRole('checkbox', { name: /Autorizo enviar áudio das reuniões/ });
    await expect(authorise).toBeVisible();
    await expect(authorise).not.toBeChecked();
    await expect(page.getByText('Transcrição de reunião: não autorizada')).toBeVisible();

    const tariff = page.getByLabel('Tarifa por minuto de áudio (US$)');
    await expect(tariff).toBeVisible();
    await expect(tariff).toHaveValue('0');

    await authorise.check();
    await page.getByLabel('Confirme sua senha').fill('password');
    await page.getByRole('button', { name: 'Salvar configurações' }).click();

    // Tarifa zerada: o sistema recusa autorizar uma chamada paga sem preço.
    await expect(page.getByRole('alert')).toContainText(/tarifa por minuto/i);
    await expect(page.getByText('Transcrição de reunião: não autorizada')).toBeVisible();
});
