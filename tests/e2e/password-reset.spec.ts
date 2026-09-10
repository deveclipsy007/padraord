import { expect, test } from '@playwright/test';

// Sem esta jornada, quem esquece a senha fica fora do sistema e depende de
// alguém editar o banco. Verificada nos três tamanhos de tela.

test('the login screen offers recovery and the request screen answers without revealing accounts', async ({ page }) => {
    await page.goto('/login');
    await page.getByRole('link', { name: 'Esqueci minha senha' }).click();
    await expect(page).toHaveURL(/\/forgot-password$/);
    await expect(page.getByRole('heading', { name: 'Enviar link de redefinição' })).toBeVisible();

    await page.getByLabel('E-mail').fill('naoexiste@padraord.com.br');
    await page.getByRole('button', { name: 'Enviar link' }).click();

    const answer = page.getByRole('status');
    await expect(answer).toContainText(/Se houver uma conta ativa/);
    // A resposta não pode confirmar nem negar a existência da conta.
    await expect(answer).not.toContainText(/não encontrad|inexistente|não existe/i);
});

test('an invalid or used token is refused with an actionable message', async ({ page }) => {
    await page.goto('/reset-password/token-invalido?email=test%40example.com');
    await expect(page.getByRole('heading', { name: 'Definir nova senha' })).toBeVisible();

    await page.getByLabel('Nova senha', { exact: true }).fill('Producao2026Segura');
    await page.getByLabel('Confirme a nova senha').fill('Producao2026Segura');
    await page.getByRole('button', { name: 'Salvar nova senha' }).click();

    await expect(page.getByRole('alert')).toContainText(/já foi usado ou expirou/);
});

test('recovery screens stay reachable while signed out', async ({ page }) => {
    for (const path of ['/forgot-password', '/reset-password/qualquer-token']) {
        const response = await page.goto(path);
        expect(response?.status()).toBe(200);
    }
});
