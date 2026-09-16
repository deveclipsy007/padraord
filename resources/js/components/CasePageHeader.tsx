import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { ReactNode } from 'react';
import { PageHeader } from './ui/PageHeader';

export function CasePageHeader({
    id,
    eyebrow,
    title,
    client,
    status,
    actions,
}: {
    id: number;
    eyebrow: string;
    title: string;
    client: string;
    status?: ReactNode;
    actions?: ReactNode;
}) {
    const production =
        typeof window !== 'undefined' &&
        (/^\/production/.test(window.location.pathname) || /\/(production|post-event)$/.test(window.location.pathname));
    return (
        <div className="case-dossier-header">
            <PageHeader
                breadcrumbs={
                    <Link className="case-back" href={production ? '/production' : `/opportunities/${id}`}>
                        <ArrowLeft size={14} /> {production ? 'Central de Produção' : 'Visão geral'}
                    </Link>
                }
                eyebrow={eyebrow}
                title={title}
                description={
                    <span className="case-header-meta">
                        {client}
                        {status && <> · {status}</>}
                    </span>
                }
                primaryAction={actions}
            />
        </div>
    );
}
