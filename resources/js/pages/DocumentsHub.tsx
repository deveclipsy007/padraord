import { Head, Link } from '@inertiajs/react';
import { FileCheck2, FileText, ShieldCheck } from 'lucide-react';
import { CasePageHeader } from '../components/CasePageHeader';
import { AppLayout } from '../layout';
import { Surface } from '../components/ui/Surface';
import { StatusBadge } from '../components/ui/StatusBadge';

type Document = {
    id: number;
    type: string;
    purpose: string;
    title: string;
    version: number;
    status: string;
    stale: boolean;
    createdAt: string;
};
export default function DocumentsHub({
    opportunity,
    documents,
}: {
    opportunity: { id: number; title: string; clientName: string };
    documents: Document[];
}) {
    const groups = [
        { key: 'viability', title: 'Proposta de Viabilidade', type: 'proposal', icon: FileText },
        { key: 'management', title: 'Proposta de Gestão', type: 'proposal', icon: ShieldCheck },
        { key: 'contract', title: 'Contrato', type: 'contract', icon: FileCheck2 },
    ];
    return (
        <AppLayout>
            <Head title={`Documentos · ${opportunity.title}`} />
            <CasePageHeader
                id={opportunity.id}
                eyebrow="Central de documentos"
                title="Versões, fontes e decisões"
                client={opportunity.clientName}
            />
            <section className="documents-hub-grid">
                {groups.map(({ key, title, type, icon: Icon }) => {
                    const items = documents.filter((document) =>
                        type === 'contract' ? document.type === 'contract' : document.type === 'proposal' && document.purpose === key,
                    );
                    const latest = items[0];
                    return (
                        <Surface className="document-hub-card" key={key} interactive>
                            <div className="document-hub-card__icon">
                                <Icon size={20} />
                            </div>
                            <div>
                                <span className="eyebrow">{type === 'contract' ? 'ACORDO' : 'PROPOSTA'}</span>
                                <h2>{title}</h2>
                                <p>{latest ? `Versão ${latest.version} · ${latest.createdAt}` : 'Nenhuma versão criada.'}</p>
                            </div>
                            {latest && (
                                <StatusBadge tone={latest.stale ? 'warning' : latest.status === 'sent' ? 'success' : 'neutral'}>
                                    {latest.stale ? 'Fontes alteradas' : latest.status}
                                </StatusBadge>
                            )}
                            <Link className="button button-subtle" href={`/opportunities/${opportunity.id}/${type}?purpose=${key}`}>
                                {latest ? 'Abrir versões' : 'Criar rascunho'}
                            </Link>
                        </Surface>
                    );
                })}
            </section>
            <Surface className="documents-timeline">
                <div className="panel-heading">
                    <div>
                        <span className="eyebrow">HISTÓRICO</span>
                        <h2>Versões preservadas</h2>
                    </div>
                </div>
                {documents.length ? (
                    documents.map((document) => (
                        <Link
                            key={document.id}
                            href={`/opportunities/${opportunity.id}/${document.type}?version=${document.version}`}
                            className="document-version-row"
                        >
                            <span>
                                <strong>{document.title}</strong>
                                <small>
                                    {document.type} · {document.purpose} · v{document.version}
                                </small>
                            </span>
                            <StatusBadge tone={document.stale ? 'warning' : 'neutral'}>
                                {document.stale ? 'Revisar fontes' : document.status}
                            </StatusBadge>
                        </Link>
                    ))
                ) : (
                    <p className="inline-empty">Os documentos criados a partir de snapshots aparecerão aqui.</p>
                )}
            </Surface>
        </AppLayout>
    );
}
