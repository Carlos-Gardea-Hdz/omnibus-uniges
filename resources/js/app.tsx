import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot, hydrateRoot } from 'react-dom/client';
import { StrictMode } from 'react';
import { ThemeProvider } from '@/Contexts/ThemeContext';
import { LocaleProvider } from '@/Contexts/LocaleContext';

const appName = import.meta.env.VITE_APP_NAME ?? 'UNIGES';

void createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob('./Pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const tree = (
            <StrictMode>
                <ThemeProvider>
                    <LocaleProvider>
                        <App {...props} />
                    </LocaleProvider>
                </ThemeProvider>
            </StrictMode>
        );

        // Hydrate when SSR markup is present; otherwise mount fresh.
        if (el.hasChildNodes()) {
            hydrateRoot(el, tree);
        } else {
            createRoot(el).render(tree);
        }
    },
    progress: { color: '#69966B' },
});
