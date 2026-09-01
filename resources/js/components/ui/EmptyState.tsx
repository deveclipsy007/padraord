import type { ReactNode } from 'react';

export function EmptyState({ icon, title, description, action }: { icon?: ReactNode; title: string; description: ReactNode; action?: ReactNode }) {
    return <div className="empty-state-v2">{icon && <div className="empty-state-v2__icon">{icon}</div>}<h3>{title}</h3><div>{description}</div>{action && <div className="empty-state-v2__action">{action}</div>}</div>;
}
