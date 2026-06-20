import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { LocaleProvider } from '@/Contexts/LocaleContext';
import { ThemeProvider } from '@/Contexts/ThemeContext';

/*
 * Unit test for the Judge Certificates report (SLICE 008). Mocks
 * @inertiajs/react (Link/usePage/router.get). Critical assertions: the
 * export-deferred note is present AND there is NO working export button (the
 * print button belongs to the deferred export slice), plus the role labels and
 * the professor filter driving a server-side GET.
 */
const routerGet = vi.hoisted(() => vi.fn());

vi.mock('@inertiajs/react', () => ({
    Head: ({ title }: { title?: string }) => <title>{title}</title>,
    Link: ({ children, ...props }: React.ComponentProps<'a'>) => <a {...props}>{children}</a>,
    usePage: () => ({ props: { demo: null } }),
    router: { get: routerGet },
}));

import JudgeCertificates from '@/Pages/Reporting/JudgeCertificates';

type Props = React.ComponentProps<typeof JudgeCertificates>;

const BASE: Props = {
    professors: [
        {
            professor_id: 7,
            professor_name: 'Grace Hopper',
            assignment_count: 2,
            assignments: [
                {
                    jury_assignment_id: 11,
                    role: 'president',
                    role_label_key: 'jury_role.president',
                    student_id: 1,
                    student_control_number: 'A1001',
                    student_name: 'Ada Lovelace',
                    program_name: 'Ingeniería en Sistemas',
                    student_status: 'graduated',
                    ceremony_date: '2025-02-01 10:00:00',
                    graduation_date: '2025-03-01',
                },
                {
                    jury_assignment_id: 12,
                    role: 'vocal',
                    role_label_key: 'jury_role.vocal',
                    student_id: 2,
                    student_control_number: 'A1002',
                    student_name: 'Alan Turing',
                    program_name: 'Ingeniería en Sistemas',
                    student_status: 'ceremony_scheduled',
                    ceremony_date: null,
                    graduation_date: null,
                },
            ],
        },
    ],
    filters: { professor_id: null },
    filter_options: { professors: [{ id: 7, name: 'Grace Hopper' }] },
};

function renderJudge(overrides: Partial<Props> = {}) {
    return render(
        <ThemeProvider>
            <LocaleProvider>
                <JudgeCertificates {...BASE} {...overrides} />
            </LocaleProvider>
        </ThemeProvider>,
    );
}

describe('Judge Certificates report page', () => {
    afterEach(() => {
        cleanup();
        routerGet.mockClear();
    });

    it('shows the export-deferred note and NO working export button', () => {
        renderJudge();
        expect(
            screen.getByText(
                'La generación del certificado en Word estará disponible próximamente. Por ahora puedes consultar los datos en pantalla.',
            ),
        ).toBeInTheDocument();
        // Export deferral is honest: no working export/download button exists yet
        // (the language/theme chrome buttons are not export controls).
        expect(
            screen.queryByRole('button', {
                name: /export|exportar|descargar|generar|word|pdf|imprimir|print/i,
            }),
        ).not.toBeInTheDocument();
    });

    it('renders each professor with their assignment count and resolved role labels', () => {
        renderJudge();
        expect(
            screen.getByRole('heading', { level: 2, name: 'Grace Hopper' }),
        ).toBeInTheDocument();
        expect(screen.getByText('2 asignaciones')).toBeInTheDocument();
        // Roles resolved via the reused jury_role.* keys.
        expect(screen.getByText('Presidente')).toBeInTheDocument();
        expect(screen.getByText('Vocal')).toBeInTheDocument();
        // Student status resolved via the reused status.* keys.
        expect(screen.getByText('Titulado')).toBeInTheDocument();
    });

    it('drives a server-side GET when the professor filter changes', () => {
        renderJudge();
        fireEvent.change(screen.getByRole('combobox', { name: /profesor/i }), {
            target: { value: '7' },
        });
        expect(routerGet).toHaveBeenCalledWith(
            '/admin/reports/judge-certificates',
            { professor_id: '7' },
            expect.objectContaining({ preserveState: true }),
        );
    });

    it('shows the empty state when no professor has served on a jury', () => {
        renderJudge({ professors: [] });
        expect(
            screen.getByText('Ningún profesor ha participado todavía en un jurado.'),
        ).toBeInTheDocument();
    });
});
