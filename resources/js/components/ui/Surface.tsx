import type { HTMLAttributes, ReactNode } from 'react';

export type SurfaceTone = 'plain' | 'glass' | 'elevated' | 'accent' | 'dark';
export type SurfacePadding = 'none' | 'sm' | 'md' | 'lg';

type SurfaceProps = HTMLAttributes<HTMLElement> & {
    as?: 'article' | 'aside' | 'div' | 'section';
    children: ReactNode;
    tone?: SurfaceTone;
    padding?: SurfacePadding;
    interactive?: boolean;
};

export function Surface({
    as: Component = 'section',
    children,
    className = '',
    tone = 'glass',
    padding = 'md',
    interactive = false,
    ...props
}: SurfaceProps) {
    const classes = ['ui-surface', `ui-surface--${tone}`, `ui-surface--pad-${padding}`, interactive ? 'is-interactive' : '', className]
        .filter(Boolean)
        .join(' ');

    return (
        <Component className={classes} {...props}>
            {children}
        </Component>
    );
}
