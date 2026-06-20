import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, render, screen } from '@testing-library/react';
import { LocaleProvider } from '@/Contexts/LocaleContext';
import { ThemeProvider } from '@/Contexts/ThemeContext';

/*
 * Unit test for the Cohorts report (SLICE 008). Mocks @inertiajs/react
 * (Head/usePage). Asserts the per-cohort heading + summary and the EXACTLY-9
 * status tiles in step order (the fixed-shape breakdown reaching the screen).
 */
vi.mock('@inertiajs/react', () => ({
    Head: ({ title }: { title?: string }) => <title>{title}</title>,
    Link: ({ children, ...props }: React.ComponentProps<'a'>) => <a {...props}>{children}</a>,
    usePage: () => ({ props: { demo: null } }),
}));

import Cohorts from '@/Pages/Reporting/Cohorts';

type Props = React.ComponentProps<typeof Cohorts>;

/** Nine rows in step() order — the fixed-shape cohort breakdown. */
const BY_STATUS: Props['cohorts'][number]['by_status'] = [
    { status: 'form_b_pending', step: 1, label_key: 'status.form_b_pending', color: 'warning', count: 1 },
    { status: 'form_b_review', step: 2, label_key: 'status.form_b_review', color: 'primary', count: 0 },
    { status: 'form_b_rejected', step: 3, label_key: 'status.form_b_rejected', color: 'danger', count: 0 },
    { status: 'annexes_pending', step: 4, label_key: 'status.annexes_pending', color: 'warning', count: 0 },
    { status: 'annex_iii_pending', step: 5, label_key: 'status.annex_iii_pending', color: 'warning', count: 2 },
    { status: 'payment_pending', step: 6, label_key: 'status.payment_pending', color: 'warning', count: 0 },
    { status: 'jury_assigned', step: 7, label_key: 'status.jury_assigned', color: 'primary', count: 0 },
    { status: 'ceremony_scheduled', step: 8, label_key: 'status.ceremony_scheduled', color: 'primary', count: 3 },
    { status: 'graduated', step: 9, label_key: 'status.graduated', color: 'success', count: 4 },
];

const BASE: Props = {
    cohorts: [
        {
            cohort_year: 2019,
            total: 10,
            graduated: 4,
            in_progress: 6,
            by_status: BY_STATUS,
        },
    ],
};

function renderCohorts(overrides: Partial<Props> = {}) {
    return render(
        <ThemeProvider>
            <LocaleProvider>
                <Cohorts {...BASE} {...overrides} />
            </LocaleProvider>
        </ThemeProvider>,
    );
}

describe('Cohorts report page', () => {
    afterEach(cleanup);

    it('renders the cohort heading with the year and the summary', () => {
        renderCohorts();
        expect(
            screen.getByRole('heading', { level: 2, name: 'Generación 2019' }),
        ).toBeInTheDocument();
        // The summary line shows total/graduated/in_progress.
        expect(screen.getByText(/Total: 10/)).toBeInTheDocument();
        expect(screen.getByText(/Titulados: 4/)).toBeInTheDocument();
        expect(screen.getByText(/En proceso: 6/)).toBeInTheDocument();
    });

    it('renders exactly nine status tiles for the cohort', () => {
        renderCohorts();
        // 9 status tiles (no other listitems on the page).
        expect(screen.getAllByRole('listitem')).toHaveLength(9);
    });

    it('shows the empty state when there are no cohorts', () => {
        renderCohorts({ cohorts: [] });
        expect(screen.getByText('Aún no hay generaciones registradas.')).toBeInTheDocument();
        expect(screen.queryAllByRole('listitem')).toHaveLength(0);
    });
});
