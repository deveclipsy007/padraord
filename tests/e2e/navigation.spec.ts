import { expect, test } from '@playwright/test';
import { loginAs } from './helpers';

test('core operational destinations open after a direct refresh', async ({ page }) => {
    await loginAs(page);

    const destinations = [
        ['/pipeline', 'Pipeline vivo'],
        ['/projects', 'Do contexto à entrega.'],
        ['/agenda', 'Agenda'],
        ['/clients', 'Clientes'],
        ['/suppliers', 'Fornecedores e cotações'],
        ['/history', 'Histórico operacional'],
    ] as const;

    for (const [path, heading] of destinations) {
        await page.goto(path);
        await expect(page).toHaveURL(new RegExp(`${path.replace('/', '\\/')}$`));
        await expect(page.getByRole('heading', { name: heading }).first()).toBeVisible();
        await page.reload();
        await expect(page.getByRole('heading', { name: heading }).first()).toBeVisible();
    }
});

test('an opportunity keeps every contextual module addressable', async ({ page }) => {
    await loginAs(page);
    await page.goto('/opportunities/1');
    await expect(page.getByRole('heading', { name: 'Conferência Horizonte 2026' }).last()).toBeVisible();

    for (const module of ['journey', 'briefing', 'feasibility', 'budget', 'documents', 'production', 'post-event', 'history']) {
        await page.goto(`/opportunities/1/${module}`);
        expect((await page.title()).length).toBeGreaterThan(0);
    }
});

test('production and post-event keep their manual operational controls available', async ({ page }) => {
    await loginAs(page);

    await page.goto('/opportunities/1/production');
    await expect(page.getByRole('heading', { name: 'Do plano à execução' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Adicionar tarefa' })).toBeVisible();
    await expect(page.getByLabel('Referência')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Salvar validação' })).toBeVisible();

    await page.goto('/opportunities/1/post-event');
    await expect(page.getByRole('heading', { name: 'Preservar o aprendizado' })).toBeVisible();
    await expect(page.getByLabel('Resumo do evento')).toBeVisible();
    await expect(page.getByLabel('Aprendizados reutilizáveis')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Salvar memória' })).toBeVisible();
});
