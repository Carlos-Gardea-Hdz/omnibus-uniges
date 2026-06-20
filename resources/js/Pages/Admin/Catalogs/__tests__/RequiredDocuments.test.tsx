import { useState } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import { LocaleProvider } from '@/Contexts/LocaleContext';
import { ThemeProvider } from '@/Contexts/ThemeContext';

/*
 * Unit test for the Required Documents catalog CRUD page (SLICE 009, CONTRACT
 * §10.7). This catalog has a numeric max_size_kb input and a nullable textarea
 * description; it has no unique column, so no duplicate-code path.
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

import RequiredDocuments from '@/Pages/Admin/Catalogs/RequiredDocuments';

type RequiredDocumentsProps = React.ComponentProps<typeof RequiredDocuments>;

const PROPS: RequiredDocumentsProps = {
    required_documents: [
        {
            id: 40,
            name: 'Acta de nacimiento',
            description: 'Copia certificada',
            allowed_mimes: 'pdf,jpg',
            max_size_kb: 2048,
        },
        {
            id: 41,
            name: 'CURP',
            description: null,
            allowed_mimes: 'pdf',
            max_size_kb: 1024,
        },
    ],
};

function renderPage() {
    return render(
        <ThemeProvider>
            <LocaleProvider>
                <RequiredDocuments {...PROPS} />
            </LocaleProvider>
        </ThemeProvider>,
    );
}

describe('Required Documents catalog page', () => {
    afterEach(() => {
        cleanup();
        routerDelete.mockClear();
    });

    it('renders the document name, allowed mimes and max size', () => {
        renderPage();
        const table = screen.getByRole('table');
        expect(within(table).getByText('Acta de nacimiento')).toBeInTheDocument();
        expect(within(table).getByText('2048')).toBeInTheDocument();
    });

    it('opens the create modal with a numeric size input', () => {
        renderPage();
        fireEvent.click(screen.getByRole('button', { name: 'Nuevo' }));
        const dialog = screen.getByRole('dialog');
        const sizeInput = within(dialog).getByRole('spinbutton', {
            name: /Tamaño máx/,
        }) as HTMLInputElement;
        expect(sizeInput.type).toBe('number');
    });

    it('opens the edit modal pre-filled including the size value', () => {
        renderPage();
        const editButtons = screen.getAllByRole('button', { name: 'Editar' });
        fireEvent.click(editButtons[0]!);
        const dialog = screen.getByRole('dialog');
        expect(within(dialog).getByDisplayValue('Acta de nacimiento')).toBeInTheDocument();
        expect(within(dialog).getByDisplayValue('2048')).toBeInTheDocument();
    });

    it('fires router.delete on delete confirm', () => {
        renderPage();
        const deleteButtons = screen.getAllByRole('button', { name: 'Eliminar' });
        fireEvent.click(deleteButtons[0]!);
        const dialog = screen.getByRole('dialog');
        fireEvent.click(within(dialog).getByRole('button', { name: 'Eliminar' }));
        expect(routerDelete).toHaveBeenCalledWith(
            '/admin/catalogs/required-documents/40',
            expect.objectContaining({ preserveScroll: true }),
        );
    });
});
