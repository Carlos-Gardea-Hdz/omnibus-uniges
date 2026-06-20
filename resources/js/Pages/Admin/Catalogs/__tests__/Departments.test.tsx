import { useState } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import { LocaleProvider } from '@/Contexts/LocaleContext';
import { ThemeProvider } from '@/Contexts/ThemeContext';

/*
 * Unit test for the Departments catalog CRUD page (SLICE 009, CONTRACT §10.7).
 * We mock @inertiajs/react so the page renders without the Inertia runtime:
 *   - Link → plain anchor (the back-to-hub link);
 *   - a *stateful* useForm (real useState) so opening the edit modal shows
 *     pre-filled inputs and typing re-renders;
 *   - router.delete spied so the delete-confirm dialog can be asserted;
 *   - usePage → super_admin auth + a configurable errors/flash bag so the
 *     graceful in-use-delete error banner can be exercised.
 *
 * The errors/flash bag is read from a hoisted mutable ref so individual tests
 * can set it before render.
 */
const { routerDelete, pageProps } = vi.hoisted(() => ({
    routerDelete: vi.fn(),
    pageProps: {
        current: {
            demo: null,
            auth: { user: { role: 'super_admin' } },
            errors: {} as Record<string, string>,
            flash: {} as { error?: string; success?: string },
        },
    },
}));

vi.mock('@inertiajs/react', () => ({
    Head: ({ title }: { title?: string }) => <title>{title}</title>,
    Link: ({ children, ...props }: React.ComponentProps<'a'>) => <a {...props}>{children}</a>,
    router: { delete: routerDelete, post: vi.fn(), put: vi.fn() },
    usePage: () => ({ props: pageProps.current }),
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

import Departments from '@/Pages/Admin/Catalogs/Departments';

type DepartmentsProps = React.ComponentProps<typeof Departments>;

const ROWS: DepartmentsProps['departments'] = [
    { id: 1, code: 'DEP01', name: 'Ingeniería' },
    { id: 2, code: 'DEP02', name: 'Ciencias' },
];

function renderPage(props: Partial<DepartmentsProps> = {}) {
    return render(
        <ThemeProvider>
            <LocaleProvider>
                <Departments departments={ROWS} {...props} />
            </LocaleProvider>
        </ThemeProvider>,
    );
}

describe('Departments catalog page', () => {
    afterEach(() => {
        cleanup();
        routerDelete.mockClear();
        pageProps.current = {
            demo: null,
            auth: { user: { role: 'super_admin' } },
            errors: {},
            flash: {},
        };
    });

    it('renders a table row per department', () => {
        renderPage();
        const table = screen.getByRole('table');
        expect(within(table).getByText('Ingeniería')).toBeInTheDocument();
        expect(within(table).getByText('DEP02')).toBeInTheDocument();
    });

    it('opens the create modal when New is clicked', () => {
        renderPage();
        fireEvent.click(screen.getByRole('button', { name: 'Nuevo' }));
        expect(screen.getByRole('dialog')).toBeInTheDocument();
    });

    it('opens the edit modal pre-filled with the row values', () => {
        renderPage();
        // Two edit buttons (one per row); edit the first.
        const editButtons = screen.getAllByRole('button', { name: 'Editar' });
        fireEvent.click(editButtons[0]!);

        const dialog = screen.getByRole('dialog');
        const codeInput = within(dialog).getByDisplayValue('DEP01') as HTMLInputElement;
        expect(codeInput).toBeInTheDocument();
        expect(within(dialog).getByDisplayValue('Ingeniería')).toBeInTheDocument();
    });

    it('opens a confirm dialog and fires router.delete on confirm', () => {
        renderPage();
        const deleteButtons = screen.getAllByRole('button', { name: 'Eliminar' });
        fireEvent.click(deleteButtons[0]!);

        const dialog = screen.getByRole('dialog');
        // The confirm dialog has its own Eliminar button.
        const confirm = within(dialog).getByRole('button', { name: 'Eliminar' });
        fireEvent.click(confirm);

        expect(routerDelete).toHaveBeenCalledWith(
            '/admin/catalogs/departments/1',
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('shows the graceful in-use-delete error banner from a catalog field error', () => {
        pageProps.current = {
            demo: null,
            auth: { user: { role: 'super_admin' } },
            errors: { catalog: 'No se puede eliminar: está en uso.' },
            flash: {},
        };
        renderPage();
        expect(screen.getByRole('alert')).toHaveTextContent('No se puede eliminar: está en uso.');
        // The referenced rows remain present (the delete failed gracefully).
        expect(screen.getByText('Ingeniería')).toBeInTheDocument();
    });
});
