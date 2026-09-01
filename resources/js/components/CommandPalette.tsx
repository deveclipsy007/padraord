import { router } from '@inertiajs/react';
import { ArrowRight, Search, X } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

type Result = { label: string; detail: string; href: string; id?: number };
const fallback: Result[] = [
    { label: 'Hoje', detail: 'Dashboard operacional', href: '/' },
    { label: 'Projetos', detail: 'Casos e laboratório de demonstração', href: '/projects' },
    { label: 'Fornecedores', detail: 'Cadastros e cotações', href: '/suppliers' },
    { label: 'Pipeline', detail: 'Fluxo comercial', href: '/pipeline' },
    { label: 'Agenda', detail: 'Próximas ações', href: '/agenda' },
    { label: 'Ajuda e tour', detail: 'Primeiro ciclo', href: '/help' },
];

export function CommandPalette({ open, onClose }: { open: boolean; onClose: () => void }) {
    const [query, setQuery] = useState('');
    const [opportunities, setOpportunities] = useState<Result[]>([]);
    useEffect(() => { if (!open) return; setQuery(''); setOpportunities([]); }, [open]);
    useEffect(() => {
        if (!open || query.trim().length < 2) return;
        const controller = new AbortController();
        fetch(`/search?q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' }, signal: controller.signal }).then((response) => response.ok ? response.json() : null).then((payload) => setOpportunities(payload?.opportunities ?? [])).catch(() => undefined);
        return () => controller.abort();
    }, [open, query]);
    const shortcuts = useMemo(() => fallback.filter((item) => item.label.toLowerCase().includes(query.toLowerCase()) || item.detail.toLowerCase().includes(query.toLowerCase())), [query]);
    if (!open) return null;
    const results = [...opportunities, ...shortcuts];
    return <div className="command-backdrop" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) onClose(); }}><div className="command-palette" role="dialog" aria-modal="true" aria-label="Busca rápida"><div className="command-input"><Search size={18} /><input autoFocus value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Buscar oportunidade ou navegar…" /><kbd>ESC</kbd><button type="button" onClick={onClose} aria-label="Fechar busca"><X size={16} /></button></div><div className="command-results">{results.length ? results.map((item, index) => <button className="command-result" type="button" key={`${item.href}-${item.label}-${index}`} onClick={() => { onClose(); router.visit(item.href); }}><span><strong>{item.label}</strong><small>{item.detail}</small></span><ArrowRight size={15} /></button>) : <div className="command-empty">Digite pelo menos dois caracteres para encontrar uma oportunidade.</div>}</div><div className="command-footer"><span>↑↓ navegar</span><span>Enter abrir</span><span>Esc fechar</span></div></div></div>;
}
