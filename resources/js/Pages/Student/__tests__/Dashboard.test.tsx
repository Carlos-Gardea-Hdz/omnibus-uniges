import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, render, screen } from '@testing-library/react';
import { LocaleProvider } from '@/Contexts/LocaleContext';
import { ThemeProvider } from '@/Contexts/ThemeContext';

/*
 * Unit test for the student dashboard overview (SLICE 007). The page is a pure
 * render of snake_case props from Student\DashboardController. We mock
 * @inertiajs/react so it renders without the Inertia runtime: `Link` becomes a
 * plain anchor (so we can assert the CTA href) and `usePage` returns a null
 * `demo` prop so the shared DemoBanner stays inert.
 */
vi.mock('@inertiajs/react', () => ({
    Head: ({ title }: { title?: string }) => <title>{title}</title>,
    Link: ({ children, ...props }: React.ComponentProps<'a'>) => <a {...props}>{children}</a>,
    usePage: () => ({ props: { demo: null } }),
}));

// Imported after the mock so the page (and GraduationProgress) pick it up.
import Dashboard from '@/Pages/Student/Dashboard';

type DashboardProps = React.ComponentProps<typeof Dashboard>;

const BASE: DashboardProps = {
    student_id: 7,
    control_number: '20180003',
    full_name: 'Ana Demo',
    status: 'annex_iii_pending',
    step: 5,
    total_steps: 9,
    is_graduated: false,
    cta: { route: '/student/documents', label_key: 'dashboard.student.cta.documents' },
    form_b_observations: null,
};

function renderDashboard(overrides: Partial<DashboardProps> = {}) {
    return render(
        <ThemeProvider>
            <LocaleProvider>
                <Dashboard {...BASE} {...overrides} />
            </LocaleProvider>
        </ThemeProvider>,
    );
}

describe('Student Dashboard page', () => {
    afterEach(cleanup);

    it('renders the next-step CTA as a link to the active screen', () => {
        renderDashboard();

        // Spanish (default locale) label for the documents CTA.
        const cta = screen.getByRole('link', { name: 'Continuar con mis documentos' });
        expect(cta).toHaveAttribute('href', '/student/documents');
    });

    it('lists exactly nine progress steps with the current one named', () => {
        renderDashboard();

        const steps = screen.getAllByRole('listitem');
        expect(steps).toHaveLength(9);

        // Step 5 is current → carries the live status label + the "in progress" cue.
        const current = steps[4]!;
        expect(current).toHaveAttribute('aria-current', 'step');
        expect(current).toHaveTextContent('Anexo III pendiente');
        expect(current).toHaveTextContent('En curso');
    });

    it('shows the rejection observations and the fix CTA when rejected', () => {
        renderDashboard({
            status: 'form_b_rejected',
            step: 3,
            cta: { route: '/student/form-b', label_key: 'dashboard.student.cta.form_b_fix' },
            form_b_observations: 'El promedio no coincide con el acta.',
        });

        expect(screen.getByText('El promedio no coincide con el acta.')).toBeInTheDocument();
        const cta = screen.getByRole('link', { name: 'Corregir y reenviar Formato B' });
        expect(cta).toHaveAttribute('href', '/student/form-b');
    });

    it('shows the graduation celebration and no CTA when graduated', () => {
        renderDashboard({
            status: 'graduated',
            step: 9,
            is_graduated: true,
            cta: null,
        });

        expect(
            screen.getByRole('heading', { name: '¡Felicidades, estás titulado!' }),
        ).toBeInTheDocument();
        // No next-step CTA in the terminal state.
        expect(screen.queryByRole('link')).not.toBeInTheDocument();
    });
});
