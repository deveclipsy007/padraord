import type { WorkQueueItem } from '../types';

export type RankedAction = WorkQueueItem & { reason: string };

export function rankTodayActions(items: WorkQueueItem[]): RankedAction[] {
    const score = (item: WorkQueueItem) =>
        (item.overdue ? 100 : 0) +
        (item.kind === 'review' ? 50 : 0) +
        (item.priority === 'high' ? 30 : item.priority === 'normal' ? 10 : 0) +
        (item.dueAt ? 5 : 0);
    return [...items]
        .sort((a, b) => score(b) - score(a) || a.id - b.id)
        .slice(0, 3)
        .map((item) => ({
            ...item,
            reason: item.overdue
                ? 'O prazo já passou; resolva ou reagende.'
                : item.kind === 'review'
                  ? 'Sua revisão libera a próxima etapa.'
                  : item.priority === 'high'
                    ? 'Prioridade alta definida pela equipe.'
                    : item.dueAt
                      ? 'Prazo próximo; mantenha o fluxo andando.'
                      : 'Próxima ação disponível para avançar.',
        }));
}
