import { expect, type Page } from '@playwright/test';

export async function loginAs(page: Page, email = 'test@example.com', password = 'password'): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('E-mail').fill(email);
    await page.getByLabel('Senha').fill(password);
    await page.getByRole('button', { name: /Entrar no sistema/ }).click();
    await expect(page).toHaveURL(/\/$/);
}

export async function logout(page: Page): Promise<void> {
    const railLogout = page.getByRole('button', { name: /Sair da conta/ });
    if (await railLogout.isVisible().catch(() => false)) {
        await railLogout.click();
    } else {
        await page.getByRole('button', { name: 'Menu' }).click();
        await page.getByRole('dialog', { name: 'Menu' }).getByRole('button', { name: 'Sair' }).click();
    }
    await expect(page).toHaveURL(/\/login$/);
}
