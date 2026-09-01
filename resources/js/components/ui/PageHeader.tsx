import type { ReactNode } from 'react';

type PageHeaderProps = {
    title: string;
    eyebrow?: string;
    description?: ReactNode;
    breadcrumbs?: ReactNode;
    primaryAction?: ReactNode;
    secondaryActions?: ReactNode;
};

export function PageHeader({ title, eyebrow, description, breadcrumbs, primaryAction, secondaryActions }: PageHeaderProps) {
    return (
        <header className="page-header-v2">
            <div className="page-header-v2__copy">
                {breadcrumbs && <div className="page-header-v2__breadcrumbs">{breadcrumbs}</div>}
                {eyebrow && <span className="page-header-v2__eyebrow">{eyebrow}</span>}
                <h1>{title}</h1>
                {description && <div className="page-header-v2__description">{description}</div>}
            </div>
            {(primaryAction || secondaryActions) && (
                <div className="page-header-v2__actions">
                    {secondaryActions}
                    {primaryAction && <div className="page-header-v2__primary">{primaryAction}</div>}
                </div>
            )}
        </header>
    );
}
