import { expect, test } from '@playwright/test';
import { loginAs } from './helpers';

test('the shell does not create global horizontal overflow', async ({ page }) => {
    await loginAs(page);
    await page.goto('/pipeline');
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    expect(overflow).toBeLessThanOrEqual(1);
    if ((await page.evaluate(() => window.innerWidth)) <= 760) {
        await expect(page.getByRole('navigation', { name: 'Navegação móvel' })).toBeVisible();
    } else {
        await expect(page.getByRole('navigation', { name: 'Navegação principal' })).toBeVisible();
    }
});
