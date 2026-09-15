import { expect, test } from '@playwright/test';
import { loginAs } from './helpers';

test('workspace turns a blocker into a ranked, resolvable decision', async ({ page }, testInfo) => {
    const taskTitle = `Confirmar doca ${testInfo.project.name}`;

    await loginAs(page);
    await page.goto('/opportunities/1/production');
    await page.getByPlaceholder('Ex.: confirmar fornecedor final').fill(taskTitle);
    await page.getByRole('button', { name: 'Criar tarefa' }).click();

    await page.goto('/opportunities/1');
    await expect(page.getByRole('heading', { name: 'O que destrava o caso' })).toBeVisible();
    await page.getByRole('button', { name: 'Registrar bloqueio' }).click();
    await page.getByLabel('Decisão ou bloqueio').fill('Liberar acesso de carga');
    await page.getByLabel('O que impede o avanço').fill('O local ainda não confirmou a janela da equipe.');
    await page.getByLabel('Gravidade').selectOption('critical');
    await page.getByLabel('Tarefa impactada').selectOption({ label: taskTitle });
    await page.getByRole('button', { name: 'Salvar bloqueio' }).click();

    const queue = page.getByRole('region', { name: 'Fila de decisões do caso' });
    await expect(queue).toContainText('Liberar acesso de carga');
    await expect(queue).toContainText('Bloqueador crítico');
    await page.screenshot({ path: testInfo.outputPath('decision-queue-active.png'), fullPage: true });
    await page.getByRole('button', { name: 'Resolver bloqueio' }).click();
    await page.getByLabel('Evidência da resolução').fill('O local confirmou a janela por e-mail.');
    await page.getByRole('button', { name: 'Confirmar resolução' }).click();

    await expect(queue).toContainText('Histórico de bloqueios');
    await expect(queue).toContainText('Resolvido');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBeTruthy();
    await page.screenshot({ path: testInfo.outputPath('decision-queue.png'), fullPage: true });
});
