import { router } from '@inertiajs/react';
import { ArrowRight, Search, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

type Result = { id?: number | string; title: string; subtitle: string; href: string };
type ApiPayload = { groups?: { type: string; label: string; items: Result[] }[]; actions?: Result[] };
const fallback: Result[] = [
    { id: 'today', title: 'Hoje', subtitle: 'Dashboard operacional', href: '/' },
    { id: 'projects', title: 'Projetos', subtitle: 'Casos e laboratório de demonstração', href: '/projects' },
    { id: 'suppliers', title: 'Fornecedores', subtitle: 'Cadastros e cotações', href: '/suppliers' },
    { id: 'pipeline', title: 'Pipeline', subtitle: 'Fluxo comercial', href: '/pipeline' },
    { id: 'agenda', title: 'Agenda', subtitle: 'Próximas ações', href: '/agenda' },
    { id: 'help', title: 'Ajuda e tour', subtitle: 'Primeiro ciclo', href: '/help' },
];

export function CommandPalette({ open, onClose }: { open: boolean; onClose: () => void }) {
    const [query, setQuery] = useState('');
    const [payload, setPayload] = useState<ApiPayload>({});
    const [active, setActive] = useState(0);
    const inputRef = useRef<HTMLInputElement>(null);
    const triggerRef = useRef<HTMLElement | null>(null);
    const flatResults = useMemo(() => { const groups = payload.groups?.flatMap((group) => group.items) ?? []; return query.trim().length >= 2 ? [...groups, ...(payload.actions ?? [])] : fallback.filter((item) => item.title.toLowerCase().includes(query.toLowerCase()) || item.subtitle.toLowerCase().includes(query.toLowerCase())); }, [payload, query]);
    useEffect(() => { if (open) { triggerRef.current = document.activeElement as HTMLElement; setQuery(''); setPayload({}); setActive(0); window.setTimeout(() => inputRef.current?.focus(), 0); } else triggerRef.current?.focus(); }, [open]);
    useEffect(() => {
        if (!open || query.trim().length < 2) return;
        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            fetch(`/search?q=${encodeURIComponent(query.trim())}`, { headers: { Accept: 'application/json' }, signal: controller.signal })
                .then((response) => response.ok ? response.json() as Promise<ApiPayload> : Promise.reject(new Error('search')))
                .then(setPayload)
                .catch((error: unknown) => {
                    if (error instanceof DOMException && error.name === 'AbortError') return;
                    setPayload({});
                });
        }, 200);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [open, query]);
    useEffect(() => { if (!open) return; const keyboard = (event: KeyboardEvent) => { if (event.key === 'ArrowDown') { event.preventDefault(); setActive((value) => Math.min(value + 1, Math.max(0, flatResults.length - 1))); } if (event.key === 'ArrowUp') { event.preventDefault(); setActive((value) => Math.max(value - 1, 0)); } if (event.key === 'Enter' && flatResults[active]) { event.preventDefault(); onClose(); router.visit(flatResults[active].href); } }; window.addEventListener('keydown', keyboard); return () => window.removeEventListener('keydown', keyboard); }, [active, flatResults, onClose, open]);
    if (!open) return null;
    return <div className="command-backdrop" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) onClose(); }}><div className="command-palette" role="dialog" aria-modal="true" aria-label="Busca rápida"><div className="command-input"><Search size={18} /><input ref={inputRef} autoFocus value={query} onChange={(event) => { setQuery(event.target.value); setActive(0); }} placeholder="Buscar casos, clientes ou ações…" /><kbd>ESC</kbd><button type="button" onClick={onClose} aria-label="Fechar busca"><X size={16} /></button></div><div className="command-results">{flatResults.length ? flatResults.map((item, index) => <button className={`command-result${active === index ? ' is-active' : ''}`} type="button" key={`${item.href}-${String(item.id ?? item.title)}-${index}`} onMouseEnter={() => setActive(index)} onClick={() => { onClose(); router.visit(item.href); }}><span><strong>{item.title}</strong><small>{item.subtitle}</small></span><ArrowRight size={15} /></button>) : <div className="command-empty">{query.trim().length < 2 ? 'Digite para buscar ou escolha um destino.' : 'Nenhum resultado. Tente outro termo.'}</div>}</div><div className="command-footer"><span>↑↓ navegar</span><span>Enter abrir</span><span>Esc fechar</span></div></div></div>;
}
