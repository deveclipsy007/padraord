import { expect, test } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { loginAs } from './helpers';

test('briefing audio shortcut exposes a clear private upload with accessible feedback', async ({ page }, testInfo) => {
    await loginAs(page);
    await page.goto('/opportunities/1/briefing');
    await page.getByRole('link', { name: 'Enviar áudio', exact: true }).click();
    const studio = page.getByRole('region', { name: 'Áudio do briefing' });
    await expect(studio).toBeInViewport();
    await expect(studio.getByRole('button', { name: 'Preservar áudio privado' })).toBeDisabled();
    await studio
        .getByLabel('Arquivo de áudio', { exact: true })
        .setInputFiles({ name: 'Reunião de briefing.wav', mimeType: 'audio/wav', buffer: Buffer.from('local preview fixture') });
    await expect(studio).toContainText('Reunião de briefing.wav');
    await expect(studio.getByRole('button', { name: 'Preservar áudio privado' })).toBeEnabled();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBeTruthy();
    const audit = await new AxeBuilder({ page }).include('.audio-studio').withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
    expect(audit.violations.filter((v) => ['serious', 'critical'].includes(v.impact || ''))).toEqual([]);
    await page.screenshot({ path: testInfo.outputPath('audio-briefing.png') });
    await page.goto('/opportunities/1');
    const trigger = page.getByRole('button', { name: 'Enviar áudio', exact: true });
    await trigger.click();
    await expect(trigger).toHaveAttribute('aria-expanded', 'true');
    await expect(page.getByLabel('Gravação da reunião')).toBeVisible();
    await page.getByRole('button', { name: 'Fechar envio de áudio' }).click();
    await expect(trigger).toHaveAttribute('aria-expanded', 'false');
    await page.emulateMedia({ reducedMotion: 'reduce' });
    expect(await trigger.evaluate((el) => getComputedStyle(el).animationName)).toBe('none');
});
