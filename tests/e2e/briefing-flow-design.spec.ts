import { expect, test } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { loginAs } from './helpers';

test('guided briefing preserves drafts and exposes all three accessible stages', async ({ page }, testInfo) => {
    await loginAs(page);
    await page.goto('/opportunities/1/briefing');
    const nav = page.getByRole('navigation', { name: 'Etapas do briefing' });
    await expect(page.getByRole('button', { name: 'Criar briefing inteligente' })).toBeVisible();
    await page.getByLabel('Texto ou transcrição', { exact: true }).fill('Conversa em andamento, ainda não enviada.');
    for (const title of ['Conferir sugestões', 'Completar e aprovar', 'Reunir contexto']) {
        await nav.getByRole('button', { name: new RegExp(title) }).click();
        await expect(nav.getByRole('button', { name: new RegExp(title) })).toHaveAttribute('aria-current', 'step');
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBeTruthy();
        const audit = await new AxeBuilder({ page }).include('.briefing-workspace').withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
        expect(audit.violations.filter(v => ['serious', 'critical'].includes(v.impact || ''))).toEqual([]);
    }
    await expect(page.getByLabel('Texto ou transcrição', { exact: true })).toHaveValue('Conversa em andamento, ainda não enviada.');
    await page.screenshot({ path: testInfo.outputPath('briefing-start.png'), fullPage: true });
    await nav.getByRole('button', { name: /Completar e aprovar/ }).click();
    await page.reload();
    await expect(page.getByRole('region', { name: 'Briefing do evento' })).toBeVisible();
    await page.getByRole('button', { name: 'Criar briefing inteligente' }).click();
    await expect(page.getByRole('heading', { name: 'Comece do seu jeito.' })).toBeInViewport();
    await page.getByRole('link', { name: 'Enviar áudio', exact: true }).click();
    await expect(page.getByRole('region', { name: 'Áudio do briefing' })).toBeVisible();
});
