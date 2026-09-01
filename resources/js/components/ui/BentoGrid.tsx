import type { HTMLAttributes, ReactNode } from 'react';

export function BentoGrid({ children, className = '', ...props }: HTMLAttributes<HTMLDivElement>) {
    return <div className={`bento-grid ${className}`.trim()} {...props}>{children}</div>;
}

type BentoItemProps = HTMLAttributes<HTMLDivElement> & {
    children: ReactNode;
    colSpan?: 1 | 2 | 3 | 4;
    rowSpan?: 1 | 2 | 3;
};

export function BentoItem({ children, className = '', colSpan = 1, rowSpan = 1, ...props }: BentoItemProps) {
    return (
        <div className={`bento-item bento-item--cols-${colSpan} bento-item--rows-${rowSpan} ${className}`.trim()} {...props}>
            {children}
        </div>
    );
}
