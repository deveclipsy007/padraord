import { Link, router } from '@inertiajs/react';
import { Check, Clock3 } from 'lucide-react';
import { useState } from 'react';
import { GlassSurface } from './GlassSurface';
import type { WorkQueueItem } from '../types';

export type QueueTask = WorkQueueItem;

export function TodayQueue({ tasks, userId }: { tasks: QueueTask[]; userId: number }) {
    const [filter, setFilter] = useState('mine');
    const visible = tasks.filter((task) =>
        filter === 'mine'
            ? task.ownerId === userId
            : filter === 'overdue'
              ? task.overdue
              : filter === 'high'
                ? task.priority === 'high'
                : true,
    );
    const complete = (event: React.MouseEvent, task: QueueTask) => {
        event.preventDefault();
        event.stopPropagation();
        if (task.kind === 'activity' || task.kind === 'follow_up')
            router.post(`/activities/${task.id}/complete`, {}, { preserveScroll: true });
    };

    return (
        <GlassSurface className="prototype-panel">
            <div className="panel-heading">
                <div>
                    <span className="eyebrow">FILA DE TRABALHO</span>
                    <h2>O que precisa acontecer agora</h2>
                </div>
                <Link className="button button-subtle" href="/agenda">
                    Criar / organizar tarefas
                </Link>
            </div>
            <label className="queue-filter">
                <span>Mostrar</span>
                <select value={filter} onChange={(event) => setFilter(event.target.value)}>
                    <option value="mine">Minhas pendências</option>
                    <option value="all">Pendências da equipe</option>
                    <option value="overdue">Atrasadas</option>
                    <option value="high">Prioridade alta</option>
                </select>
            </label>
            {visible.slice(0, 8).map((task) => (
                <Link
                    className="prototype-task"
                    style={{ textDecoration: 'none', color: 'inherit' }}
                    key={`${task.kind}-${task.id}`}
                    href={task.href}
                >
                    <div>
                        <strong>{task.title}</strong>
                        <p>
                            {task.context} · {task.owner || 'Sem responsável'} · {task.dueAt || 'Prazo não definido'}
                        </p>
                    </div>
                    <span className={'status-pill ' + (task.overdue ? 'amber' : task.priority === 'high' ? 'violet' : 'gray')}>
                        {task.overdue
                            ? 'Atrasada'
                            : task.priority === 'high'
                              ? 'Alta prioridade'
                              : task.kind === 'review'
                                ? 'Revisar'
                                : 'Pendente'}{' '}
                        <Clock3 size={12} />
                    </span>
                    {(task.kind === 'activity' || task.kind === 'follow_up') && (
                        <button
                            type="button"
                            className="queue-complete"
                            onClick={(event) => complete(event, task)}
                            aria-label={`Concluir ${task.title}`}
                        >
                            <Check size={14} />
                        </button>
                    )}
                </Link>
            ))}
            {!visible.length && <p>Nenhuma pendência neste filtro. As tarefas criadas na agenda aparecerão aqui.</p>}
        </GlassSurface>
    );
}
