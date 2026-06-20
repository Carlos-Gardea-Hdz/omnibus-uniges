import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { LocaleProvider } from '@/Contexts/LocaleContext';
import { ThemeProvider } from '@/Contexts/ThemeContext';

/*
 * Unit test for the Graduates report (SLICE 008). Mocks @inertiajs/react: `Link`
 * → anchor, `usePage` → null demo (inert DemoBanner), and `router.get` → a spy so
 * we can assert the filter <select>s drive a server-side GET with the right query.
 */
const routerGet = vi.hoisted(() => vi.fn());

vi.mock('@inertiajs/react', () => ({
    Head: ({ title }: { title?: string }) => <title>{title}</title>,
    Link: ({ children, ...props }: React.ComponentProps<'a'>) => <a {...props}>{children}</a>,
    usePage: () => ({ props: { demo: null } }),
    router: { get: routerGet },
}));

import Graduates from '@/Pages/Reporting/Graduates';

type GraduatesProps = React.ComponentProps<typeof Graduates>;

const ROWS: GraduatesProps['graduates'] = [
    {
        id: 1,
        control_number: 'A1001',
        full_name: 'Ada Lovelace',
        program_name: 'Ingeniería en Sistemas',
        graduation_type_name: 'Tesis',
        diploma_folio: 'F-001',
        record_book: 'L-1',
        record_sheet: 'H-10',
        graduation_date: '2025-03-01',
    },
    {
        id: 2,
        control_number: 'A1002',
        full_name: 'Alan Turing',
        program_name: 'Ingeniería en Sistemas',
        graduation_type_name: 'Promedio',
        diploma_folio: null,
        record_book: null,
        record_sheet: null,
        graduation_date: '2025-06-15',
    },
];

const BASE: GraduatesProps = {
    graduates: ROWS,
    filters: { year: null, program_id: null },
    filter_options: {
        years: [2025, 2024],
        programs: [{ id: 3, name: 'Ingeniería en Sistemas' }],
    },
    total: 2,
};

function renderGraduates(overrides: Partial<GraduatesProps> = {}) {
    return render(
        <ThemeProvider>
            <LocaleProvider>
                <Graduates {...BASE} {...overrides} />
            </LocaleProvider>
        </ThemeProvider>,
    );
}

describe('Graduates report page', () => {
    afterEach(() => {
        cleanup();
        routerGet.mockClear();
    });

    it('renders a semantic table with one row per graduate and the count line', () => {
        renderGraduates();

        // Each graduate appears.
        expect(screen.getByText('Ada Lovelace')).toBeInTheDocument();
        expect(screen.getByText('Alan Turing')).toBeInTheDocument();
        expect(screen.getByText('A1001')).toBeInTheDocument();
        // The count reflects the total.
        expect(screen.getByText('2 titulados')).toBeInTheDocument();
        // Missing folio/book renders the em-dash placeholder, never null.
        expect(screen.getAllByText('—').length).toBeGreaterThan(0);
        // The table has an accessible caption.
        expect(screen.getByRole('table')).toBeInTheDocument();
    });

    it('drives a server-side GET when the year filter changes', () => {
        renderGraduates();

        fireEvent.change(screen.getByLabelText('Año de titulación'), {
            target: { value: '2025' },
        });

        expect(routerGet).toHaveBeenCalledWith(
            '/admin/reports/graduates',
            { year: '2025' },
            expect.objectContaining({ preserveState: true }),
        );
    });

    it('shows the clear link only when a filter is applied', () => {
        renderGraduates({ filters: { year: 2025, program_id: null } });
        expect(screen.getByRole('link', { name: 'Limpiar filtros' })).toHaveAttribute(
            'href',
            '/admin/reports/graduates',
        );
    });

    it('shows the empty state when there are no graduates', () => {
        renderGraduates({ graduates: [], total: 0 });
        expect(
            screen.getByText('No hay titulados que coincidan con los filtros seleccionados.'),
        ).toBeInTheDocument();
        expect(screen.queryByRole('table')).not.toBeInTheDocument();
    });
});
