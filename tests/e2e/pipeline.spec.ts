import { expect, test } from '@playwright/test';
import { loginAs } from './helpers';

test('pipeline keeps a single horizontal board and cards are draggable', async ({ page }) => {
    await loginAs(page);
    await page.goto('/pipeline');
    const board = page.getByRole('region', { name: 'Kanban horizontal do pipeline' });
    await expect(board).toBeVisible();
    await expect(board.locator('.pipeline-lane')).toHaveCount(6);
    await expect(board.locator('[draggable="true"]').first()).toHaveAttribute('aria-grabbed', 'false');
    await page.reload();
    await expect(board).toBeVisible();
});
