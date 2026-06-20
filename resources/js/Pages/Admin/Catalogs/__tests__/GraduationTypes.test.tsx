import { useState } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import { LocaleProvider } from '@/Contexts/LocaleContext';
import { ThemeProvider } from '@/Contexts/ThemeContext';

/*
 * Unit test for the Graduation Types catalog CRUD page (SLICE 009, CONTRACT
 * §10.7). The form has a requires_advisor checkbox and a labelled multi-select
 * of required documents (a fieldset of checkboxes). The edit modal pre-checks
 * the advisor flag + the assigned documents.
 */
const { routerDelete } = vi.hoisted(() => ({ routerDelete: vi.fn() }));

vi.mock('@inertiajs/react', () => ({
    Head: ({ title }: { title?: string }) => <title>{title}</title>,
    Link: ({ children, ...props }: React.ComponentProps<'a'>) => <a {...props}>{children}</a>,
    router: { delete: routerDelete, post: vi.fn(), put: vi.fn() },
    usePage: () => ({
        props: { demo: null, auth: { user: { role: 'super_admin' } }, errors: {}, flash: {} },
    }),
    useForm: <T extends Record<string, unknown>>(initial: T) => {
        const [data, setDataState] = useState<T>(initial);
        const setData = (keyOrValues: string | T, value?: unknown) => {
            if (typeof keyOrValues === 'string') {
                setDataState((prev) => ({ ...prev, [keyOrValues]: value }));
            } else {
                setDataState(keyOrValues);
            }
        };
        return {
            data,
            setData,
            errors: {} as Record<string, string>,
            processing: false,
            post: vi.fn(),
            put: vi.fn(),
            transform: vi.fn(),
            reset: vi.fn(),
            clearErrors: vi.fn(),
        };
    },
}));

import GraduationTypes from '@/Pages/Admin/Catalogs/GraduationTypes';

type GraduationTypesProps = React.ComponentProps<typeof GraduationTypes>;

const PROPS: GraduationTypesProps = {
    graduation_types: [
        {
            id: 50,
            code: 'TESIS',
            name: 'Tesis',
            requires_advisor: true,
            required_document_ids: [40],
        },
        {
            id: 51,
            code: 'EGEL',
            name: 'EGEL',
            requires_advisor: false,
            required_document_ids: [],
        },
    ],
    required_document_options: [
        { id: 40, name: 'Acta de nacimiento' },
        { id: 41, name: 'CURP' },
    ],
};

function renderPage() {
    return render(
        <ThemeProvider>
            <LocaleProvider>
                <GraduationTypes {...PROPS} />
            </LocaleProvider>
        </ThemeProvider>,
    );
}

describe('Graduation Types catalog page', () => {
    afterEach(() => {
        cleanup();
        routerDelete.mockClear();
    });

    it('renders the type rows with the requires-advisor cue', () => {
        renderPage();
        const table = screen.getByRole('table');
        expect(within(table).getByText('Tesis')).toBeInTheDocument();
        // "Sí" (yes) appears for the advisor-requiring type.
        expect(within(table).getByText('Sí')).toBeInTheDocument();
    });

    it('opens the create modal with the advisor checkbox and a document multi-select', () => {
        renderPage();
        fireEvent.click(screen.getByRole('button', { name: 'Nuevo' }));
        const dialog = screen.getByRole('dialog');
        expect(
            within(dialog).getByRole('checkbox', { name: /Requiere asesor/ }),
        ).toBeInTheDocument();
        // Two document option checkboxes inside the required-documents fieldset.
        expect(
            within(dialog).getByRole('checkbox', { name: 'Acta de nacimiento' }),
        ).toBeInTheDocument();
        expect(within(dialog).getByRole('checkbox', { name: 'CURP' })).toBeInTheDocument();
    });

    it('pre-checks the advisor flag and the assigned document in the edit modal', () => {
        renderPage();
        const editButtons = screen.getAllByRole('button', { name: 'Editar' });
        fireEvent.click(editButtons[0]!);
        const dialog = screen.getByRole('dialog');

        const advisor = within(dialog).getByRole('checkbox', {
            name: /Requiere asesor/,
        }) as HTMLInputElement;
        expect(advisor.checked).toBe(true);

        const acta = within(dialog).getByRole('checkbox', {
            name: 'Acta de nacimiento',
        }) as HTMLInputElement;
        expect(acta.checked).toBe(true);

        const curp = within(dialog).getByRole('checkbox', { name: 'CURP' }) as HTMLInputElement;
        expect(curp.checked).toBe(false);
    });

    it('toggles a document checkbox in the create modal', () => {
        renderPage();
        fireEvent.click(screen.getByRole('button', { name: 'Nuevo' }));
        const dialog = screen.getByRole('dialog');
        const curp = within(dialog).getByRole('checkbox', { name: 'CURP' }) as HTMLInputElement;
        expect(curp.checked).toBe(false);
        fireEvent.click(curp);
        expect(curp.checked).toBe(true);
    });

    it('fires router.delete on delete confirm', () => {
        renderPage();
        const deleteButtons = screen.getAllByRole('button', { name: 'Eliminar' });
        fireEvent.click(deleteButtons[0]!);
        const dialog = screen.getByRole('dialog');
        fireEvent.click(within(dialog).getByRole('button', { name: 'Eliminar' }));
        expect(routerDelete).toHaveBeenCalledWith(
            '/admin/catalogs/graduation-types/50',
            expect.objectContaining({ preserveScroll: true }),
        );
    });
});
