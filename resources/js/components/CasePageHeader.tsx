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
    return (
        <PageHeader
            breadcrumbs={
                <Link className="case-back" href={`/opportunities/${id}`}>
                    <ArrowLeft size={14} /> Visão geral
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
    );
}
