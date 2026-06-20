import { useState } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import { LocaleProvider } from '@/Contexts/LocaleContext';
import { ThemeProvider } from '@/Contexts/ThemeContext';

/*
 * Unit test for the Programs catalog CRUD page (SLICE 009, CONTRACT §10.7).
 * Same mock contract as Departments; Programs adds a department FK <select>.
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

import Programs from '@/Pages/Admin/Catalogs/Programs';

type ProgramsProps = React.ComponentProps<typeof Programs>;

const PROPS: ProgramsProps = {
    programs: [
        {
            id: 10,
            code: 'ISC',
            name: 'Ingeniería en Sistemas',
            department_id: 1,
            department_name: 'Ingeniería',
        },
    ],
    department_options: [
        { id: 1, name: 'Ingeniería' },
        { id: 2, name: 'Ciencias' },
    ],
};

function renderPage() {
    return render(
        <ThemeProvider>
            <LocaleProvider>
                <Programs {...PROPS} />
            </LocaleProvider>
        </ThemeProvider>,
    );
}

describe('Programs catalog page', () => {
    afterEach(() => {
        cleanup();
        routerDelete.mockClear();
    });

    it('renders the program row with its department name', () => {
        renderPage();
        const table = screen.getByRole('table');
        expect(within(table).getByText('Ingeniería en Sistemas')).toBeInTheDocument();
        expect(within(table).getByText('Ingeniería')).toBeInTheDocument();
    });

    it('renders the department FK select with both options in the create modal', () => {
        renderPage();
        fireEvent.click(screen.getByRole('button', { name: 'Nuevo' }));
        const dialog = screen.getByRole('dialog');
        const select = within(dialog).getByRole('combobox', { name: /Departamento/ });
        expect(within(select).getByRole('option', { name: 'Ciencias' })).toBeInTheDocument();
    });

    it('opens the edit modal pre-filled with the program values', () => {
        renderPage();
        fireEvent.click(screen.getByRole('button', { name: 'Editar' }));
        const dialog = screen.getByRole('dialog');
        expect(within(dialog).getByDisplayValue('ISC')).toBeInTheDocument();
        const select = within(dialog).getByRole('combobox', {
            name: /Departamento/,
        }) as HTMLSelectElement;
        expect(select.value).toBe('1');
    });

    it('fires router.delete on delete confirm', () => {
        renderPage();
        fireEvent.click(screen.getByRole('button', { name: 'Eliminar' }));
        const dialog = screen.getByRole('dialog');
        fireEvent.click(within(dialog).getByRole('button', { name: 'Eliminar' }));
        expect(routerDelete).toHaveBeenCalledWith(
            '/admin/catalogs/programs/10',
            expect.objectContaining({ preserveScroll: true }),
        );
    });
});
