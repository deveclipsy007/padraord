import { expect, test } from '@playwright/test';
import { loginAs } from './helpers';

test('directory, kanban and assistant stay within their content boundaries', async ({ page }, testInfo) => {
    await loginAs(page);
    for (const route of ['clients', 'pipeline', 'agenda', 'history']) {
        await page.goto('/' + route);
        await expect(page.locator('.feedback-fab')).toHaveCount(0);
        await expect(page.locator('.assistant-trigger')).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBeTruthy();
        if (route === 'clients') {
            const rows = page.locator('.client-directory__row');
            expect(await rows.count()).toBeGreaterThan(0);
            expect(await rows.evaluateAll(elements => elements.every(row => {
                const identity = row.querySelector('.client-directory__identity')!.getBoundingClientRect();
                const actions = row.querySelector('.client-directory__actions')!.getBoundingClientRect();
                return identity.width > 80 && identity.right <= actions.left;
            }))).toBeTruthy();
        }
        if (route === 'pipeline') {
            expect(await page.locator('.pipeline-card').evaluateAll(cards => cards.every(card => card.scrollWidth <= card.clientWidth + 1))).toBeTruthy();
        }
        await page.screenshot({ path: testInfo.outputPath(route + '.png'), fullPage: true });
    }
    await page.locator('.assistant-trigger').click();
    await expect(page.locator('.rd-drawer[open]')).toBeVisible();
    const bounds = await page.locator('.rd-drawer[open]').boundingBox();
    expect(bounds!.x).toBeGreaterThanOrEqual(0);
    expect(bounds!.width).toBeLessThanOrEqual(page.viewportSize()!.width);
    await page.keyboard.press('Escape');
    await expect(page.locator('.rd-drawer[open]')).toHaveCount(0);
});
