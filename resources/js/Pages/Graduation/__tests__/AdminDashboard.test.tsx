import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, render, screen } from '@testing-library/react';
import { LocaleProvider } from '@/Contexts/LocaleContext';
import { ThemeProvider } from '@/Contexts/ThemeContext';

/*
 * Unit test for the staff dashboard overview (SLICE 007). A pure render of
 * snake_case props from Graduation\AdminDashboardController. We mock
 * @inertiajs/react so it renders without the Inertia runtime: `Link` becomes a
 * plain anchor (so we can assert each queue href) and `usePage` returns a null
 * `demo` prop so the shared DemoBanner stays inert.
 */
vi.mock('@inertiajs/react', () => ({
    Head: ({ title }: { title?: string }) => <title>{title}</title>,
    Link: ({ children, ...props }: React.ComponentProps<'a'>) => <a {...props}>{children}</a>,
    // `auth` defaults to a non-super_admin so the optional Catalogs nav link
    // (SLICE 009) stays hidden — the existing assertions count exactly 5 links.
    usePage: () => ({ props: { demo: null, auth: { user: { role: 'admin' } } } }),
}));

import AdminDashboard from '@/Pages/Graduation/AdminDashboard';

type AdminDashboardProps = React.ComponentProps<typeof AdminDashboard>;

/** Nine rows in step() order, with the colour token each status maps to. */
const BREAKDOWN: AdminDashboardProps['status_breakdown'] = [
    {
        status: 'form_b_pending',
        step: 1,
        label_key: 'status.form_b_pending',
        color: 'warning',
        count: 0,
    },
    {
        status: 'form_b_review',
        step: 2,
        label_key: 'status.form_b_review',
        color: 'primary',
        count: 3,
    },
    {
        status: 'form_b_rejected',
        step: 3,
        label_key: 'status.form_b_rejected',
        color: 'danger',
        count: 0,
    },
    {
        status: 'annexes_pending',
        step: 4,
        label_key: 'status.annexes_pending',
        color: 'warning',
        count: 0,
    },
    {
        status: 'annex_iii_pending',
        step: 5,
        label_key: 'status.annex_iii_pending',
        color: 'warning',
        count: 2,
    },
    {
        status: 'payment_pending',
        step: 6,
        label_key: 'status.payment_pending',
        color: 'warning',
        count: 1,
    },
    {
        status: 'jury_assigned',
        step: 7,
        label_key: 'status.jury_assigned',
        color: 'primary',
        count: 1,
    },
    {
        status: 'ceremony_scheduled',
        step: 8,
        label_key: 'status.ceremony_scheduled',
        color: 'primary',
        count: 2,
    },
    { status: 'graduated', step: 9, label_key: 'status.graduated', color: 'success', count: 4 },
];

const QUEUES: AdminDashboardProps['queues'] = [
    {
        key: 'form_b',
        label_key: 'dashboard.admin.queue.form_b',
        count: 3,
        route: '/admin/graduation',
    },
    {
        key: 'documents',
        label_key: 'dashboard.admin.queue.documents',
        count: 2,
        route: '/admin/graduation/documents',
    },
    {
        key: 'jury',
        label_key: 'dashboard.admin.queue.jury',
        count: 1,
        route: '/admin/graduation/jury',
    },
    {
        key: 'ceremony',
        label_key: 'dashboard.admin.queue.ceremony',
        count: 1,
        route: '/admin/graduation/ceremony',
    },
    {
        key: 'graduation',
        label_key: 'dashboard.admin.queue.graduation',
        count: 2,
        route: '/admin/graduation/ceremony',
    },
];

const BASE: AdminDashboardProps = {
    status_breakdown: BREAKDOWN,
    queues: QUEUES,
    totals: { students: 13, graduates: 4, in_progress: 9 },
};

function renderDashboard(overrides: Partial<AdminDashboardProps> = {}) {
    return render(
        <ThemeProvider>
            <LocaleProvider>
                <AdminDashboard {...BASE} {...overrides} />
            </LocaleProvider>
        </ThemeProvider>,
    );
}

describe('Admin Dashboard page', () => {
    afterEach(cleanup);

    it('renders the three aggregate totals', () => {
        renderDashboard();

        const totals = screen.getByRole('heading', { name: 'Panel de titulación' });
        expect(totals).toBeInTheDocument();
        // Totals values (Spanish default locale labels).
        expect(screen.getByText('Estudiantes').nextSibling).toHaveTextContent('13');
        expect(screen.getByText('Titulados').nextSibling).toHaveTextContent('4');
        expect(screen.getByText('En proceso').nextSibling).toHaveTextContent('9');
    });

    it('renders exactly nine breakdown tiles and five queue links', () => {
        renderDashboard();

        // 9 breakdown tiles + 5 queue items = 14 listitems.
        expect(screen.getAllByRole('listitem')).toHaveLength(14);

        const links = screen.getAllByRole('link');
        expect(links).toHaveLength(5);
        // First and last queue hrefs prove the route strings flow through.
        expect(screen.getByRole('link', { name: /Revisión de Formato B/ })).toHaveAttribute(
            'href',
            '/admin/graduation',
        );
        expect(screen.getByRole('link', { name: /Por titular/ })).toHaveAttribute(
            'href',
            '/admin/graduation/ceremony',
        );
    });

    it('shows the empty state when there are no students', () => {
        renderDashboard({
            status_breakdown: BREAKDOWN.map((row) => ({ ...row, count: 0 })),
            queues: QUEUES.map((queue) => ({ ...queue, count: 0 })),
            totals: { students: 0, graduates: 0, in_progress: 0 },
        });

        expect(
            screen.getByText('Aún no hay estudiantes en el proceso de titulación.'),
        ).toBeInTheDocument();
        // No breakdown/queue lists are rendered in the empty state.
        expect(screen.queryAllByRole('listitem')).toHaveLength(0);
        expect(screen.queryByRole('link')).not.toBeInTheDocument();
    });
});
