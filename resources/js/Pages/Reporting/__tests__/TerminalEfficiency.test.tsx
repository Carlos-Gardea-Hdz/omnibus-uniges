import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, render, screen } from '@testing-library/react';
import { LocaleProvider } from '@/Contexts/LocaleContext';
import { ThemeProvider } from '@/Contexts/ThemeContext';

/*
 * Unit test for the Terminal Efficiency report (SLICE 008). Mocks
 * @inertiajs/react (Head/usePage). The key assertion: the int `rate` renders as
 * `N%` text (a redundant cue beside the progressbar), never a float or a bare
 * number — proving the int-percent contract reaches the screen.
 */
vi.mock('@inertiajs/react', () => ({
    Head: ({ title }: { title?: string }) => <title>{title}</title>,
    Link: ({ children, ...props }: React.ComponentProps<'a'>) => <a {...props}>{children}</a>,
    usePage: () => ({ props: { demo: null } }),
}));

import TerminalEfficiency from '@/Pages/Reporting/TerminalEfficiency';

type Props = React.ComponentProps<typeof TerminalEfficiency>;

const BASE: Props = {
    by_cohort: [{ cohort_year: 2019, total: 10, graduates: 4, rate: 40 }],
    by_program: [
        {
            program_id: 3,
            program_name: 'Ingeniería en Sistemas',
            total: 5,
            graduates: 1,
            rate: 20,
        },
    ],
    overall: { total: 15, graduates: 5, rate: 33 },
};

function renderEff(overrides: Partial<Props> = {}) {
    return render(
        <ThemeProvider>
            <LocaleProvider>
                <TerminalEfficiency {...BASE} {...overrides} />
            </LocaleProvider>
        </ThemeProvider>,
    );
}

describe('Terminal Efficiency report page', () => {
    afterEach(cleanup);

    it('renders the overall rate as N% text', () => {
        renderEff();
        // The overall headline rate as a percent string (also echoed beside its
        // progressbar, so it can appear more than once — assert presence).
        expect(screen.getAllByText('33%').length).toBeGreaterThan(0);
        // The cohort + program rates as percent strings (redundant cue).
        expect(screen.getByText('40%')).toBeInTheDocument();
        expect(screen.getByText('20%')).toBeInTheDocument();
    });

    it('exposes each rate as a real progressbar with aria-valuenow', () => {
        renderEff();
        const bars = screen.getAllByRole('progressbar');
        // overall + 1 cohort + 1 program = 3 bars.
        expect(bars).toHaveLength(3);
        expect(bars.some((bar) => bar.getAttribute('aria-valuenow') === '40')).toBe(true);
    });

    it('renders the graduates/total ratio alongside the rate', () => {
        renderEff();
        expect(screen.getByText('4 / 10')).toBeInTheDocument();
        expect(screen.getByText('1 / 5')).toBeInTheDocument();
    });

    it('shows the empty state when there are no students', () => {
        renderEff({
            by_cohort: [],
            by_program: [],
            overall: { total: 0, graduates: 0, rate: 0 },
        });
        expect(
            screen.getByText('Aún no hay estudiantes para calcular la eficiencia terminal.'),
        ).toBeInTheDocument();
        expect(screen.queryByRole('progressbar')).not.toBeInTheDocument();
    });
});
