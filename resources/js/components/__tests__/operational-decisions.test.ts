import { describe, expect, it } from 'vitest';
import { rankTodayActions } from '../operational-decisions';
import { simulateMargin } from '../financial-scenario';
import type { WorkQueueItem } from '../../types';

const task = (id: number, patch: Partial<WorkQueueItem> = {}): WorkQueueItem => ({
    id,
    kind: 'activity',
    title: `Ação ${id}`,
    context: 'Evento',
    href: '/agenda',
    owner: 'Equipe',
    ownerId: 1,
    dueAt: null,
    priority: 'normal',
    status: 'todo',
    overdue: false,
    availableActions: [],
    ...patch,
});

describe('decisões operacionais', () => {
    it('prioriza atraso, revisão e prioridade alta com motivo legível', () => {
        const ranked = rankTodayActions([task(1), task(2, { priority: 'high' }), task(3, { overdue: true }), task(4, { kind: 'review' })]);
        expect(ranked.map((item) => item.id)).toEqual([3, 4, 2]);
        expect(ranked[0].reason).toContain('prazo');
        expect(ranked[1].reason).toContain('revisão');
    });

    it('simula receita e custos sem alterar a base', () => {
        const original = { revenueCents: 100_000, costCents: 60_000 };
        expect(simulateMargin(original, -10_000, 5_000)).toEqual({
            revenueCents: 90_000,
            costCents: 65_000,
            marginCents: 25_000,
            deltaCents: -15_000,
        });
        expect(original).toEqual({ revenueCents: 100_000, costCents: 60_000 });
    });
});
