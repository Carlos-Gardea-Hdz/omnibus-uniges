import { useState } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import { LocaleProvider } from '@/Contexts/LocaleContext';
import { ThemeProvider } from '@/Contexts/ThemeContext';

/*
 * Unit test for the Professors catalog CRUD page (SLICE 009, CONTRACT §10.7).
 * Professors has four fields (first/last/mother last name + email); the
 * mother_last_name is nullable and shown blank when null.
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

import Professors from '@/Pages/Admin/Catalogs/Professors';

type ProfessorsProps = React.ComponentProps<typeof Professors>;

const PROPS: ProfessorsProps = {
    professors: [
        {
            id: 7,
            first_name: 'Ana',
            last_name: 'López',
            mother_last_name: 'Ruiz',
            email: 'ana.lopez@uniges.test',
            full_name: 'Ana López Ruiz',
        },
        {
            id: 8,
            first_name: 'Beto',
            last_name: 'Sánchez',
            mother_last_name: null,
            email: 'beto.sanchez@uniges.test',
            full_name: 'Beto Sánchez',
        },
    ],
};

function renderPage() {
    return render(
        <ThemeProvider>
            <LocaleProvider>
                <Professors {...PROPS} />
            </LocaleProvider>
        </ThemeProvider>,
    );
}

describe('Professors catalog page', () => {
    afterEach(() => {
        cleanup();
        routerDelete.mockClear();
    });

    it('renders the professor full name and email', () => {
        renderPage();
        const table = screen.getByRole('table');
        expect(within(table).getByText('Ana López Ruiz')).toBeInTheDocument();
        expect(within(table).getByText('beto.sanchez@uniges.test')).toBeInTheDocument();
    });

    it('opens the edit modal pre-filled including the email field', () => {
        renderPage();
        const editButtons = screen.getAllByRole('button', { name: 'Editar' });
        fireEvent.click(editButtons[0]!);
        const dialog = screen.getByRole('dialog');
        expect(within(dialog).getByDisplayValue('Ana')).toBeInTheDocument();
        expect(within(dialog).getByDisplayValue('ana.lopez@uniges.test')).toBeInTheDocument();
    });

    it('shows a blank mother last name in the edit modal when it is null', () => {
        renderPage();
        const editButtons = screen.getAllByRole('button', { name: 'Editar' });
        // Edit the second professor (mother_last_name is null).
        fireEvent.click(editButtons[1]!);
        const dialog = screen.getByRole('dialog');
        const motherField = within(dialog).getByRole('textbox', {
            name: /Apellido materno/,
        }) as HTMLInputElement;
        expect(motherField.value).toBe('');
    });

    it('fires router.delete on delete confirm', () => {
        renderPage();
        const deleteButtons = screen.getAllByRole('button', { name: 'Eliminar' });
        fireEvent.click(deleteButtons[0]!);
        const dialog = screen.getByRole('dialog');
        fireEvent.click(within(dialog).getByRole('button', { name: 'Eliminar' }));
        expect(routerDelete).toHaveBeenCalledWith(
            '/admin/catalogs/professors/7',
            expect.objectContaining({ preserveScroll: true }),
        );
    });
});
