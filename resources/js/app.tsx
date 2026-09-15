import '@fontsource-variable/manrope';
import '../css/app.css';
import '../css/prototype.css';
import '../css/assistance.css';
import '../css/controls.css';
import '../css/tokens.css';
import '../css/shell.css';
import '../css/components.css';
import '../css/modules.css';
import '../css/responsive-workspace.css';
import '../css/chat-and-dialogs.css';
import '../css/charts.css';
import '../css/operational-system.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

type PageModule = { default: import('@inertiajs/react').ResolvedComponent };

const pages = import.meta.glob<PageModule>('./pages/**/*.tsx');

createInertiaApp({
    title: (title) => (title ? `${title} · Padrão RD` : 'Padrão RD'),
    resolve: async (name) => (await resolvePageComponent<PageModule>(`./pages/${name}.tsx`, pages)).default,
    setup({ el, App, props }: { el: HTMLElement | null; App: import('@inertiajs/react').ResolvedComponent; props: any }) {
        if (!el) return;
        createRoot(el).render(<App {...props} />);
    },
});
