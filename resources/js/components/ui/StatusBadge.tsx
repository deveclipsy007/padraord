import type { HTMLAttributes } from 'react';

export type StatusTone = 'neutral' | 'info' | 'success' | 'warning' | 'danger' | 'accent';

type StatusBadgeProps = HTMLAttributes<HTMLSpanElement> & { tone?: StatusTone };

export function StatusBadge({ tone = 'neutral', className = '', ...props }: StatusBadgeProps) {
    return <span className={`status-badge status-badge--${tone} ${className}`.trim()} {...props} />;
}
