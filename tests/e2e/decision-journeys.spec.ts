import { expect, test } from '@playwright/test';
import { loginAs } from './helpers';

const decisionSurfaces = [
    { path: '/opportunities/1/briefing', activeModule: 'Briefing' },
    { path: '/opportunities/1/budget', activeModule: 'Orçamento' },
    { path: '/opportunities/1/documents', activeModule: 'Documentos' },
    { path: '/opportunities/1/production', activeModule: 'Produção' },
    { path: '/opportunities/1/finance', activeModule: 'Financeiro' },
];

test('decision context stays visible across the operational journey at every supported viewport', async ({ page }, testInfo) => {
    await loginAs(page, 'finance@example.test');

    for (const surface of decisionSurfaces) {
        await page.goto(surface.path);
        await expect(page.getByRole('main')).toBeVisible();
        await expect(page.locator('.context-rail__nav a.is-active', { hasText: surface.activeModule })).toBeVisible();

        const nextDecision = page.getByRole('region', { name: 'Próxima decisão do caso' });
        await expect(nextDecision).toBeVisible();
        await expect(nextDecision).toContainText('Próxima decisão');
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBeTruthy();
    }

    await page.reload();
    await expect(page.getByRole('region', { name: 'Próxima decisão do caso' })).toBeVisible();

    const assistant = page.getByRole('button', { name: 'Abrir Agente RD' });
    await expect(assistant).toBeVisible();
    if (testInfo.project.name === 'mobile') {
        const [assistantBox, navigationBox] = await Promise.all([
            assistant.boundingBox(),
            page.getByRole('navigation', { name: 'Navegação móvel' }).boundingBox(),
        ]);

        expect(assistantBox).not.toBeNull();
        expect(navigationBox).not.toBeNull();
        expect(assistantBox!.y).toBeGreaterThanOrEqual(navigationBox!.y - 1);
        expect(assistantBox!.y + assistantBox!.height).toBeLessThanOrEqual(navigationBox!.y + navigationBox!.height + 1);
    }

    await page.screenshot({ path: testInfo.outputPath('decision-journey.png'), fullPage: true, animations: 'disabled' });
});
