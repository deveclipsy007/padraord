import { Link } from '@inertiajs/react';
import { useState } from 'react';
import { GlassSurface } from './GlassSurface';
export type QueueTask = { id: number; title: string; owner: string; ownerId: number | null; due: string | null; overdue: boolean; priority: string; status: string };
export function TodayQueue({ tasks, userId }: { tasks: QueueTask[]; userId: number }) {
    const [filter, setFilter] = useState('all');
    const visible = tasks.filter(task => filter === 'mine' ? task.ownerId === userId : filter === 'overdue' ? task.overdue : filter === 'high' ? task.priority === 'high' : true);
    return <GlassSurface className="prototype-panel"><div className="panel-heading"><div><span className="eyebrow">FILA DE TRABALHO</span><h2>O que precisa acontecer agora</h2></div><Link className="button button-subtle" href="/agenda">Criar / organizar tarefas</Link></div><label>Mostrar<select value={filter} onChange={e => setFilter(e.target.value)}><option value="all">Pendências da equipe</option><option value="mine">Minhas pendências</option><option value="overdue">Atrasadas</option><option value="high">Prioridade alta</option></select></label>{visible.slice(0, 8).map(task => <Link className="prototype-task" style={{ textDecoration: 'none', color: 'inherit' }} key={task.id} href="/agenda"><div><strong>{task.title}</strong><p>{task.owner} · {task.due || 'Prazo não definido'}</p></div><span className={'status-pill ' + (task.overdue ? 'amber' : 'gray')}>{task.overdue ? 'Atrasada' : task.priority === 'high' ? 'Alta prioridade' : 'Pendente'} →</span></Link>)}{!visible.length && <p>Nenhuma pendência neste filtro. As tarefas criadas na agenda aparecerão aqui.</p>}</GlassSurface>;
}
