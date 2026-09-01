import { test } from '@playwright/test';
import { loginAs, logout } from './helpers';

test('login and logout preserve the session boundary', async ({ page }) => {
    await loginAs(page);
    await page.getByRole('heading', { name: 'Olá, Padrão RD.' }).isVisible();
    await logout(page);
});
