import { HTMLAttributes, PropsWithChildren } from 'react';

export type GlassSurfaceTone = 'light' | 'dark' | 'neutral';

type Props = PropsWithChildren<
    HTMLAttributes<HTMLDivElement> & {
        tone?: GlassSurfaceTone;
        interactive?: boolean;
    }
>;

export function GlassSurface({ children, className = '', tone = 'light', interactive = false, ...props }: Props) {
    return (
        <div
            className={`panel glass-surface glass-surface-${tone}${interactive ? ' glass-surface-interactive' : ''}${className ? ` ${className}` : ''}`}
            {...props}
        >
            {children}
        </div>
    );
}
