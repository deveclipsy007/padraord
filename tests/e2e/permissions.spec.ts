import { expect, test } from '@playwright/test';
import { loginAs } from './helpers';

test('administrator can reach protected administration pages', async ({ page }) => {
    await loginAs(page);
    await page.goto('/team');
    await expect(page.getByRole('heading', { name: 'Equipe.' })).toBeVisible();
    await page.goto('/settings/ai');
    await expect(page.getByRole('heading', { name: /Inteligência artificial/ })).toBeVisible();
});

test('health endpoint accepts only the configured token', async ({ request }) => {
    const unauthorized = await request.get('/api/v1/health');
    expect(unauthorized.status()).toBe(401);

    const authorized = await request.get('/api/v1/health', { headers: { Authorization: 'Bearer cycle01-health-token' } });
    expect(authorized.status()).toBe(200);
    expect((await authorized.json()).checks.database).toBe('ok');
});
