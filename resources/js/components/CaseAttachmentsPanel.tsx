import { router, useForm } from '@inertiajs/react';
import { Archive, ArchiveRestore, Download, FilePlus2, FileText, Link2, Paperclip } from 'lucide-react';
import { type FormEvent, useMemo, useState } from 'react';
import { Drawer, Field, FormActions } from './FormControls';
import { FormErrors } from './FormErrors';
import { Surface } from './ui/Surface';

export type CaseAttachment = {
    id: number;
    originalName: string;
    mimeType: string | null;
    sizeBytes: number;
    module: AttachmentModule;
    link: { type: string; id: number; label: string } | null;
    isArchived: boolean;
    archivedAt: string | null;
    archiveReason: string | null;
    createdAt: string | null;
    downloadUrl: string;
};

export type AttachmentModule = 'documents' | 'briefing' | 'production' | 'finance' | 'venue';

export type AttachmentLink = {
    key: string;
    module: AttachmentModule;
    label: string;
};

const modules: { value: AttachmentModule; label: string }[] = [
    { value: 'documents', label: 'Documentos' },
    { value: 'briefing', label: 'Briefing' },
    { value: 'production', label: 'Produção' },
    { value: 'finance', label: 'Financeiro' },
    { value: 'venue', label: 'Local' },
];

function fileKind(name: string): string {
    const extension = name.split('.').pop()?.trim();

    return extension ? extension.toUpperCase() : 'ARQUIVO';
}

function bytes(value: number): string {
    if (!value) return '0 B';

    const units = ['B', 'KB', 'MB', 'GB'];
    const position = Math.min(Math.floor(Math.log(value) / Math.log(1024)), units.length - 1);
    const amount = value / 1024 ** position;

    return `${new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 1 }).format(amount)} ${units[position]}`;
}

function date(value: string | null): string {
    if (!value) return 'Data indisponível';

    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

export function CaseAttachmentsPanel({
    opportunityId,
    attachments,
    attachmentLinks,
    attachmentQuota,
}: {
    opportunityId: number;
    attachments: CaseAttachment[];
    attachmentLinks: AttachmentLink[];
    attachmentQuota: number;
}) {
    const [uploadOpen, setUploadOpen] = useState(false);
    const [archiveTarget, setArchiveTarget] = useState<CaseAttachment | null>(null);
    const [linkKey, setLinkKey] = useState('');
    const upload = useForm({
        file: null as File | null,
        module: 'documents' as AttachmentModule,
        linked_type: '',
        linked_id: '',
    });
    const archive = useForm({ reason: '' });
    const usedBytes = attachments.reduce((total, attachment) => total + attachment.sizeBytes, 0);
    const availableLinks = useMemo(
        () => attachmentLinks.filter((link) => link.module === upload.data.module),
        [attachmentLinks, upload.data.module],
    );
    const active = attachments.filter((attachment) => !attachment.isArchived);
    const archived = attachments.filter((attachment) => attachment.isArchived);
    const base = `/opportunities/${opportunityId}/attachments`;

    function chooseModule(module: AttachmentModule) {
        upload.setData('module', module);
        if (!attachmentLinks.some((link) => link.key === linkKey && link.module === module)) {
            setLinkKey('');
        }
    }

    function submitUpload(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const [linkedType = '', linkedId = ''] = linkKey.split(':');

        upload.transform((data) => ({
            ...data,
            linked_type: linkedType,
            linked_id: linkedId,
        }));
        upload.post(base, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                upload.reset();
                upload.clearErrors();
                setLinkKey('');
                setUploadOpen(false);
            },
        });
    }

    function submitArchive(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!archiveTarget) return;

        archive.post(`${base}/${archiveTarget.id}/archive`, {
            preserveScroll: true,
            onSuccess: () => {
                archive.reset();
                archive.clearErrors();
                setArchiveTarget(null);
            },
        });
    }

    function restore(attachment: CaseAttachment) {
        router.post(`${base}/${attachment.id}/restore`, {}, { preserveScroll: true });
    }

    return (
        <>
            <Surface as="section" tone="plain" className="case-attachments" aria-label="Anexos privados">
                <header className="case-attachments__header">
                    <div>
                        <span className="eyebrow">ARQUIVOS DO CASO</span>
                        <h2>Anexos privados</h2>
                        <p>Arquivos ficam vinculados ao caso, com acesso controlado e histórico preservado.</p>
                    </div>
                    <div className="case-attachments__summary">
                        <span>
                            {bytes(usedBytes)} de {bytes(attachmentQuota)} usados
                        </span>
                        <button className="button button-primary" type="button" onClick={() => setUploadOpen(true)}>
                            <FilePlus2 size={16} />
                            Adicionar anexo
                        </button>
                    </div>
                </header>

                {active.length ? (
                    <div className="case-attachments__list" aria-label="Arquivos disponíveis">
                        {active.map((attachment) => (
                            <AttachmentRow
                                key={attachment.id}
                                attachment={attachment}
                                onArchive={() => setArchiveTarget(attachment)}
                                onRestore={() => restore(attachment)}
                            />
                        ))}
                    </div>
                ) : (
                    <div className="case-attachments__empty">
                        <Paperclip size={20} />
                        <div>
                            <strong>Nenhum anexo disponível</strong>
                            <p>Adicione referências, arquivos de briefing, validações ou comprovantes deste caso.</p>
                        </div>
                    </div>
                )}

                {archived.length > 0 && (
                    <section className="case-attachments__archived" aria-label="Anexos arquivados">
                        <div>
                            <span className="eyebrow">HISTÓRICO</span>
                            <h3>Arquivados ({archived.length})</h3>
                        </div>
                        <div className="case-attachments__list">
                            {archived.map((attachment) => (
                                <AttachmentRow
                                    key={attachment.id}
                                    attachment={attachment}
                                    onArchive={() => setArchiveTarget(attachment)}
                                    onRestore={() => restore(attachment)}
                                />
                            ))}
                        </div>
                    </section>
                )}
            </Surface>

            <Drawer title="Adicionar anexo privado" open={uploadOpen} onClose={() => setUploadOpen(false)}>
                <form className="attachment-form" onSubmit={submitUpload}>
                    <p className="drawer-intro">Aceita PDF, PNG, JPEG, WebP ou TXT com até 20 MB. O arquivo não recebe uma URL pública.</p>
                    <Field label="Arquivo" error={upload.errors.file}>
                        <input
                            type="file"
                            required
                            accept=".pdf,.png,.jpg,.jpeg,.webp,.txt,application/pdf,image/png,image/jpeg,image/webp,text/plain"
                            onChange={(event) => upload.setData('file', event.currentTarget.files?.[0] ?? null)}
                        />
                    </Field>
                    <Field label="Área do arquivo" error={upload.errors.module}>
                        <select value={upload.data.module} onChange={(event) => chooseModule(event.target.value as AttachmentModule)}>
                            {modules.map((module) => (
                                <option key={module.value} value={module.value}>
                                    {module.label}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Vincular a (opcional)" error={upload.errors.linked_id ?? upload.errors.linked_type}>
                        <select value={linkKey} onChange={(event) => setLinkKey(event.target.value)}>
                            <option value="">Sem vínculo adicional</option>
                            {availableLinks.map((link) => (
                                <option key={link.key} value={link.key}>
                                    {link.label}
                                </option>
                            ))}
                        </select>
                    </Field>
                    {!availableLinks.length && upload.data.module !== 'documents' && (
                        <p className="attachment-form__hint">Ainda não há um registro compatível neste caso para vincular.</p>
                    )}
                    <FormErrors errors={upload.errors} />
                    <FormActions>
                        <button className="button button-subtle" type="button" onClick={() => setUploadOpen(false)}>
                            Cancelar
                        </button>
                        <button className="button button-primary" disabled={upload.processing}>
                            {upload.processing ? 'Guardando…' : 'Guardar anexo'}
                        </button>
                    </FormActions>
                </form>
            </Drawer>

            <Drawer
                title="Arquivar anexo privado"
                open={archiveTarget !== null}
                onClose={() => {
                    archive.clearErrors();
                    setArchiveTarget(null);
                }}
            >
                <form className="attachment-form" onSubmit={submitArchive}>
                    <p className="drawer-intro">O arquivo continuará preservado no histórico e consumindo a quota deste caso.</p>
                    <Field label="Motivo do arquivamento" error={archive.errors.reason}>
                        <textarea
                            required
                            rows={4}
                            value={archive.data.reason}
                            onChange={(event) => archive.setData('reason', event.target.value)}
                        />
                    </Field>
                    <FormErrors errors={archive.errors} />
                    <FormActions>
                        <button className="button button-subtle" type="button" onClick={() => setArchiveTarget(null)}>
                            Cancelar
                        </button>
                        <button className="button button-primary" disabled={archive.processing}>
                            {archive.processing ? 'Arquivando…' : 'Arquivar anexo'}
                        </button>
                    </FormActions>
                </form>
            </Drawer>
        </>
    );
}

function AttachmentRow({ attachment, onArchive, onRestore }: { attachment: CaseAttachment; onArchive: () => void; onRestore: () => void }) {
    return (
        <article
            className={attachment.isArchived ? 'case-attachment-row is-archived' : 'case-attachment-row'}
            aria-label={attachment.originalName}
        >
            <div className="case-attachment-row__type" aria-hidden="true">
                <FileText size={18} />
                <span>{fileKind(attachment.originalName)}</span>
            </div>
            <div className="case-attachment-row__main">
                <strong>{attachment.originalName}</strong>
                <small>
                    {bytes(attachment.sizeBytes)} · {date(attachment.createdAt)}
                </small>
                {attachment.link && (
                    <span className="case-attachment-row__link">
                        <Link2 size={13} />
                        {attachment.link.label}
                    </span>
                )}
                {attachment.isArchived && attachment.archiveReason && <small>{attachment.archiveReason}</small>}
            </div>
            <div className="case-attachment-row__meta">
                <span>{modules.find((module) => module.value === attachment.module)?.label ?? attachment.module}</span>
                <span className={attachment.isArchived ? 'status-badge status-badge--neutral' : 'status-badge status-badge--info'}>
                    {attachment.isArchived ? 'Arquivado' : 'Disponível'}
                </span>
            </div>
            <div className="case-attachment-row__actions">
                <a className="button button-subtle" href={attachment.downloadUrl}>
                    <Download size={15} />
                    Baixar
                </a>
                {attachment.isArchived ? (
                    <button className="button button-subtle" type="button" onClick={onRestore}>
                        <ArchiveRestore size={15} />
                        Restaurar
                    </button>
                ) : (
                    <button className="button button-subtle" type="button" onClick={onArchive}>
                        <Archive size={15} />
                        Arquivar
                    </button>
                )}
            </div>
        </article>
    );
}
