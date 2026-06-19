import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import createServer from '@inertiajs/react/server';
import ReactDOMServer from 'react-dom/server';
import { ThemeProvider } from '@/Contexts/ThemeContext';
import { LocaleProvider } from '@/Contexts/LocaleContext';

const appName = process.env.VITE_APP_NAME ?? 'UNIGES';

createServer((page) =>
    createInertiaApp({
        page,
        render: ReactDOMServer.renderToString,
        title: (title) => (title ? `${title} · ${appName}` : appName),
        resolve: (name) =>
            resolvePageComponent(
                `./Pages/${name}.tsx`,
                import.meta.glob(['./Pages/**/*.tsx', '!./Pages/**/*.test.tsx', '!./Pages/**/__tests__/**']),
            ),
        setup: ({ App, props }) => (
            <ThemeProvider>
                <LocaleProvider>
                    <App {...props} />
                </LocaleProvider>
            </ThemeProvider>
        ),
    }),
);
