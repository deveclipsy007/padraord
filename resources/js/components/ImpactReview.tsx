import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { getJson } from './control-utils';
type Preview = { fingerprint: string; note: string; groups: { title: string; count: number; detail: string; href: string }[] };
export function ImpactReview({
    caseId,
    changeKey,
    onReviewed,
}: {
    caseId: number;
    changeKey: string;
    onReviewed: (fingerprint: string) => void;
}) {
    const [preview, setPreview] = useState<Preview | null>(null);
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);
    useEffect(() => {
        setPreview(null);
        onReviewed('');
    }, [changeKey]);
    async function load() {
        setBusy(true);
        setError('');
        try {
            const p = await getJson<Preview>('/opportunities/' + caseId + '/control/impact?change=scope');
            setPreview(p);
            onReviewed(p.fingerprint);
        } catch (e) {
            setError((e as Error).message);
        } finally {
            setBusy(false);
        }
    }
    return (
        <div className="control-preview control-full">
            <h3>Essa mudança pode afetar o restante do projeto.</h3>
            <p>Você alterou data, local, público ou escopo. Confira os registros relacionados antes de salvar.</p>
            {error && <p role="alert">{error}</p>}
            {preview && (
                <>
                    <div className="focus-links">
                        {preview.groups.map((g) => (
                            <Link key={g.title} href={g.href} target="_blank" rel="noreferrer">
                                {g.count} {g.title.toLowerCase()}
                            </Link>
                        ))}
                    </div>
                    <p>{preview.note}</p>
                    <small>Prévia conferida. Você já pode salvar.</small>
                </>
            )}
            <button className="button" type="button" disabled={busy} onClick={load}>
                {busy ? 'Conferindo…' : preview ? 'Conferir novamente' : 'Conferir impacto para continuar'}
            </button>
        </div>
    );
}
