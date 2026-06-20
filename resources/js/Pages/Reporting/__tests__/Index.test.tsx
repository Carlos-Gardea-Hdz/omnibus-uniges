import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, render, screen } from '@testing-library/react';
import { LocaleProvider } from '@/Contexts/LocaleContext';
import { ThemeProvider } from '@/Contexts/ThemeContext';

/*
 * Unit test for the Reporting hub (SLICE 008). A pure render of snake_case props
 * from Reporting\ReportingHubController. We mock @inertiajs/react so it renders
 * without the runtime: `Link` becomes a plain anchor (so we can assert each card
 * href is a real link — no dead-end), `usePage` returns a null `demo` prop so the
 * shared DemoBanner stays inert.
 */
vi.mock('@inertiajs/react', () => ({
    Head: ({ title }: { title?: string }) => <title>{title}</title>,
    Link: ({ children, ...props }: React.ComponentProps<'a'>) => <a {...props}>{children}</a>,
    usePage: () => ({ props: { demo: null } }),
}));

import ReportingIndex from '@/Pages/Reporting/Index';

type IndexProps = React.ComponentProps<typeof ReportingIndex>;

const REPORTS: IndexProps['reports'] = [
    {
        key: 'graduates',
        title_key: 'reports.graduates.title',
        description_key: 'reports.graduates.description',
        route: '/admin/reports/graduates',
    },
    {
        key: 'terminal_efficiency',
        title_key: 'reports.terminal_efficiency.title',
        description_key: 'reports.terminal_efficiency.description',
        route: '/admin/reports/terminal-efficiency',
    },
    {
        key: 'cohorts',
        title_key: 'reports.cohorts.title',
        description_key: 'reports.cohorts.description',
        route: '/admin/reports/cohorts',
    },
    {
        key: 'judge_certificates',
        title_key: 'reports.judge_certificates.title',
        description_key: 'reports.judge_certificates.description',
        route: '/admin/reports/judge-certificates',
    },
];

function renderHub(overrides: Partial<IndexProps> = {}) {
    return render(
        <ThemeProvider>
            <LocaleProvider>
                <ReportingIndex reports={REPORTS} {...overrides} />
            </LocaleProvider>
        </ThemeProvider>,
    );
}

describe('Reporting hub page', () => {
    afterEach(cleanup);

    it('renders exactly four report cards, each a real link to its route', () => {
        renderHub();

        const links = screen.getAllByRole('link');
        expect(links).toHaveLength(4);

        // Each card links to its report route (no dead-end).
        expect(screen.getByRole('link', { name: /Reporte de titulados/ })).toHaveAttribute(
            'href',
            '/admin/reports/graduates',
        );
        expect(screen.getByRole('link', { name: /Eficiencia terminal/ })).toHaveAttribute(
            'href',
            '/admin/reports/terminal-efficiency',
        );
        expect(screen.getByRole('link', { name: /Generaciones/ })).toHaveAttribute(
            'href',
            '/admin/reports/cohorts',
        );
        expect(screen.getByRole('link', { name: /Certificados de jurado/ })).toHaveAttribute(
            'href',
            '/admin/reports/judge-certificates',
        );
    });

    it('renders one h1 for the hub', () => {
        renderHub();
        expect(screen.getByRole('heading', { level: 1, name: 'Reportes' })).toBeInTheDocument();
    });
});
