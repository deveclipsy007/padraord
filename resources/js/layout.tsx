import { Link, router, usePage } from '@inertiajs/react';
import {
    Bot,
    Orbit,
    PanelsTopLeft,
    Clapperboard,
    Building2,
    CalendarDays,
    CircleHelp,
    FolderKanban,
    History,
    LayoutDashboard,
    LogOut,
    MapPin,
    Menu,
    Plus,
    Search,
    Settings2,
    Sparkles,
    Users,
    Workflow,
    X,
} from 'lucide-react';
import { PropsWithChildren, useEffect, useState } from 'react';
import { CommandPalette } from './components/CommandPalette';
import { AssistantPanel } from './components/AssistantPanel';
import { ContextRail, type ContextRailItem } from './components/ui/ContextRail';

type SharedPage = {
    ai?: { mode: string; status?: string };
    auth?: { user?: { id: number; name: string; email: string; role: string; isAdmin: boolean } | null };
    flash?: { success?: string; error?: string };
    opportunity?: {
        id: number;
        title?: string;
        clientName?: string;
        client_name?: string;
        stageLabel?: string;
        stage?: string;
        readiness?: number;
        readinessPercent?: number;
        nextAction?: string;
        next_action?: string;
        owner?: { name?: string };
        responsible?: { name?: string };
    };
    client?: { id: number; name?: string; company_name?: string; status?: string };
    supplier?: { id: number; name?: string; company_name?: string; status?: string };
    moduleStatuses?: { key: string; status: string; pending: number }[];
    caseShell?: {
        id: number;
        title: string;
        clientName: string;
        stageLabel: string;
        nextAction?: string | null;
        readiness: number;
        moduleStatuses: { key: string; status: string; pending: number }[];
    } | null;
};
type NavigationItem = { label: string; href: string; icon: typeof LayoutDashboard };
const navigation: NavigationItem[] = [
    { label: 'Hoje', href: '/', icon: LayoutDashboard },
    { label: 'Controle operacional', href: '/operations', icon: PanelsTopLeft },
    { label: 'Agente Orbital RD', href: '/orbital', icon: Orbit },
    { label: 'Comercial', href: '/pipeline', icon: Workflow },
    { label: 'Projetos', href: '/projects', icon: FolderKanban },
    { label: 'Produção', href: '/production', icon: Clapperboard },
    { label: 'Tarefas e agenda', href: '/agenda', icon: CalendarDays },
    { label: 'Clientes', href: '/clients', icon: Users },
    { label: 'Fornecedores', href: '/suppliers', icon: Building2 },
    { label: 'Locais', href: '/venues', icon: MapPin },
    { label: 'Histórico', href: '/history', icon: History },
];
function isActive(pathname: string, href: string) {
    return href === '/' ? pathname === '/' : pathname.startsWith(href);
}

function createContext(
    pathname: string,
    props: SharedPage,
): { title: string; status?: string; owner?: string; progress?: number; nextStep?: string; items: ContextRailItem[] } | null {
    const productionMatch =
        pathname.match(/^\/production\/events\/(\d+)/) || pathname.match(/^\/opportunities\/(\d+)\/(?:production|post-event)/);
    if (productionMatch) {
        const id = productionMatch[1];
        return {
            title: props.caseShell?.title || props.opportunity?.title || `Evento #${id}`,
            status: 'Central de Produção',
            items: [
                { label: 'Todos os eventos', href: '/production' },
                { label: 'Produção', href: `/production/events/${id}`, active: !pathname.endsWith('/post-event') },
                { label: 'Pós-evento', href: `/production/events/${id}/post-event`, active: pathname.endsWith('/post-event') },
                { label: 'Consultar projeto comercial', href: `/opportunities/${id}` },
            ],
        };
    }
    const opportunityMatch = pathname.match(/^\/opportunities\/(\d+)/);
    if (opportunityMatch) {
        const id = opportunityMatch[1];
        const base = `/opportunities/${id}`;
        const modules = [
            ['Visão geral', ''],
            ['Jornada', '/journey'],
            ['Controle', '/control'],
            ['Briefing', '/briefing'],
            ['Viabilidade', '/feasibility'],
            ['Orçamento', '/budget'],
            ['Financeiro', '/finance'],
            ['Documentos', '/documents'],
            ['Histórico', '/history'],
        ] as const;
        const opportunity = props.opportunity;
        const shell = props.caseShell;
        return {
            title: shell?.title || opportunity?.title || opportunity?.clientName || opportunity?.client_name || `Caso #${id}`,
            status: shell?.stageLabel || opportunity?.stageLabel || opportunity?.stage || 'Em andamento',
            owner: opportunity?.owner?.name || opportunity?.responsible?.name,
            progress: shell?.readiness ?? opportunity?.readiness ?? opportunity?.readinessPercent,
            nextStep: shell?.nextAction || opportunity?.nextAction || opportunity?.next_action,
            items: modules.map(([label, suffix]) => {
                const key = suffix.replace('/', '') || 'overview';
                const state = (shell?.moduleStatuses || props.moduleStatuses)?.find(
                    (item) => item.key === key || (key === 'post-event' && item.key === 'post-event'),
                );
                return {
                    label,
                    href: `${base}${suffix}`,
                    active: suffix ? pathname.startsWith(`${base}${suffix}`) : pathname === base,
                    status: state?.status,
                    pending: state?.pending,
                };
            }),
        };
    }
    const clientMatch = pathname.match(/^\/clients\/(\d+)/);
    if (clientMatch && props.client)
        return {
            title: props.client.name || props.client.company_name || `Cliente #${clientMatch[1]}`,
            status: props.client.status || 'Cliente',
            items: [
                { label: 'Visão geral', href: pathname, active: true },
                { label: 'Oportunidades', href: '/pipeline' },
                { label: 'Agenda', href: '/agenda' },
            ],
        };
    const supplierMatch = pathname.match(/^\/suppliers\/(\d+)/);
    if (supplierMatch && props.supplier)
        return {
            title: props.supplier.name || props.supplier.company_name || `Fornecedor #${supplierMatch[1]}`,
            status: props.supplier.status || 'Fornecedor',
            items: [
                { label: 'Perfil', href: pathname, active: true },
                { label: 'Cotações', href: '/suppliers?view=quotes' },
                { label: 'Casos relacionados', href: '/projects' },
            ],
        };
    if (pathname.startsWith('/prototype'))
        return {
            title: 'Conferência Horizonte',
            status: 'Demonstração',
            progress: 64,
            nextStep: 'Revisar o briefing estruturado.',
            items: [
                { label: 'Visão geral', href: '/prototype', active: pathname === '/prototype' },
                { label: 'Briefing', href: '/prototype/briefing', active: pathname.includes('briefing') },
                { label: 'Orçamento', href: '/prototype/budget', active: pathname.includes('budget') },
                { label: 'Produção', href: '/prototype/production', active: pathname.includes('production') },
            ],
        };
    return null;
}

export function AppLayout({ children }: PropsWithChildren) {
    const pathname = typeof window === 'undefined' ? '/' : window.location.pathname;
    const pageProps = usePage<SharedPage>().props;
    const { auth, flash, ai } = pageProps;
    const user = auth?.user;
    const [menuOpen, setMenuOpen] = useState(false);
    const [toast, setToast] = useState<string | null>(null);
    const [commandOpen, setCommandOpen] = useState(false);
    const context = createContext(pathname, pageProps);

    useEffect(() => {
        const message = flash?.success || flash?.error;
        if (!message) return;
        setToast(message);
        const timer = window.setTimeout(() => setToast(null), 4200);
        return () => window.clearTimeout(timer);
    }, [flash?.success, flash?.error]);

    useEffect(() => {
        const open = () => setCommandOpen(true);
        const keyboard = (event: KeyboardEvent) => {
            const target = event.target;
            const isTyping =
                target instanceof HTMLElement &&
                (target.matches('input, textarea, select, [contenteditable="true"]') || target.isContentEditable);
            if (!isTyping && (event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                setCommandOpen(true);
            }
            if (event.key === 'Escape' && (!isTyping || commandOpen)) setCommandOpen(false);
        };
        window.addEventListener('rd:command', open);
        window.addEventListener('keydown', keyboard);
        return () => {
            window.removeEventListener('rd:command', open);
            window.removeEventListener('keydown', keyboard);
        };
    }, [commandOpen]);

    return (
        <div className="rd-os-shell">
            <aside className="sidebar app-rail">
                <Link className="brand-lockup" href="/">
                    <div className="brand-mark" aria-hidden="true">
                        RD
                    </div>
                    <span className="sr-only">Padrão RD</span>
                </Link>
                <nav className="primary-nav" aria-label="Navegação principal">
                    {navigation.map(({ label, href, icon: Icon }) => (
                        <Link
                            className={`nav-item${isActive(pathname, href) ? ' active' : ''}`}
                            href={href}
                            key={label}
                            viewTransition
                            aria-label={label}
                            aria-current={isActive(pathname, href) ? 'page' : undefined}
                            data-tooltip={label}
                            title={label}
                        >
                            <Icon size={19} strokeWidth={1.75} />
                            <span>{label}</span>
                        </Link>
                    ))}
                </nav>
                <div className="sidebar-bottom">
                    <button
                        className="rail-action rail-search"
                        type="button"
                        onClick={() => setCommandOpen(true)}
                        aria-label="Busca global"
                        data-tooltip="Busca global"
                    >
                        <Search size={19} />
                    </button>
                    <button
                        className="rail-action rail-ai"
                        type="button"
                        onClick={() => router.visit(user?.isAdmin ? '/settings/ai' : '/help')}
                        aria-label="Inteligência artificial"
                        data-tooltip={ai?.mode === 'openai' ? 'IA · OpenAI' : ai?.mode === 'demo' ? 'IA · demonstração' : 'IA · manual'}
                    >
                        <Bot size={19} />
                    </button>
                    <Link className="rail-action" href="/help" aria-label="Ajuda e tour" data-tooltip="Ajuda e tour">
                        <CircleHelp size={19} />
                    </Link>
                    {user?.isAdmin && (
                        <Link className="rail-action" href="/team" aria-label="Administração" data-tooltip="Administração">
                            <Settings2 size={19} />
                        </Link>
                    )}
                    <button
                        className="rail-profile"
                        type="button"
                        onClick={() => router.post('/logout')}
                        aria-label={`Sair da conta de ${user?.name ?? 'Padrão RD'}`}
                        data-tooltip={`${user?.name ?? 'Padrão RD'} · sair`}
                    >
                        {user?.name?.slice(0, 2).toUpperCase() ?? 'RD'}
                        <LogOut size={11} />
                    </button>
                </div>
            </aside>
            {context && <ContextRail {...context} />}
            <main className={`main-content${context ? ' has-context-rail' : ''}`}>{children}</main>
            {pathname !== '/orbital' && <AssistantPanel key={pathname} path={pathname} mode={ai?.mode || 'manual'} />}
            <nav className="mobile-nav" aria-label="Navegação móvel">
                <Link className={isActive(pathname, '/') ? 'active' : ''} href="/" viewTransition>
                    <LayoutDashboard size={18} />
                    <span>Hoje</span>
                </Link>
                <Link className={isActive(pathname, '/pipeline') ? 'active' : ''} href="/pipeline" viewTransition>
                    <Workflow size={18} />
                    <span>Pipeline</span>
                </Link>
                <Link className={isActive(pathname, '/agenda') ? 'active' : ''} href="/agenda" viewTransition>
                    <CalendarDays size={18} />
                    <span>Agenda</span>
                </Link>
                <button className={menuOpen ? 'active' : ''} type="button" onClick={() => setMenuOpen((value) => !value)}>
                    <Menu size={18} />
                    <span>Menu</span>
                </button>
            </nav>
            {menuOpen && (
                <div className="mobile-menu" role="dialog" aria-label="Menu">
                    <Link href="/operations">Controle operacional</Link>
                    <Link href="/orbital">Agente Orbital RD</Link>
                    <Link href="/projects">Projetos e laboratório</Link>
                    <Link href="/production">Produção</Link>
                    <Link href="/clients">Clientes</Link>
                    <Link href="/suppliers">Fornecedores</Link>
                    <Link href="/venues">Locais</Link>
                    <Link href="/history">Histórico</Link>
                    <Link href="/help">Ajuda e tour</Link>
                    {user?.isAdmin && (
                        <>
                            <Link href="/team">Equipe</Link>
                            <Link href="/settings/ai">Inteligência artificial</Link>
                        </>
                    )}
                    <button type="button" onClick={() => router.post('/logout')}>
                        Sair
                    </button>
                </div>
            )}
            {toast && (
                <div className="toast" role="status">
                    <Sparkles size={15} />
                    {toast}
                    <button type="button" aria-label="Fechar aviso" onClick={() => setToast(null)}>
                        <X size={14} />
                    </button>
                </div>
            )}
            <CommandPalette open={commandOpen} onClose={() => setCommandOpen(false)} />
        </div>
    );
}

export function PrimaryButton({
    children,
    onClick,
    type = 'button',
}: PropsWithChildren<{ onClick?: () => void; type?: 'button' | 'submit' }>) {
    return (
        <button className="button button-primary" type={type} onClick={onClick}>
            <Plus size={16} />
            {children}
        </button>
    );
}
