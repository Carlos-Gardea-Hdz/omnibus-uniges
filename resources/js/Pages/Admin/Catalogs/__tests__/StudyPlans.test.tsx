import { useState } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import { LocaleProvider } from '@/Contexts/LocaleContext';
import { ThemeProvider } from '@/Contexts/ThemeContext';

/*
 * Unit test for the Study Plans catalog CRUD page (SLICE 009, CONTRACT §10.7).
 * Study plans carry a program FK <select>, same shape as Programs.
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

import StudyPlans from '@/Pages/Admin/Catalogs/StudyPlans';

type StudyPlansProps = React.ComponentProps<typeof StudyPlans>;

const PROPS: StudyPlansProps = {
    study_plans: [
        {
            id: 30,
            code: 'PE-2020',
            name: 'Plan 2020',
            program_id: 10,
            program_name: 'Ingeniería en Sistemas',
        },
    ],
    program_options: [
        { id: 10, name: 'Ingeniería en Sistemas' },
        { id: 11, name: 'Contaduría' },
    ],
};

function renderPage() {
    return render(
        <ThemeProvider>
            <LocaleProvider>
                <StudyPlans {...PROPS} />
            </LocaleProvider>
        </ThemeProvider>,
    );
}

describe('Study Plans catalog page', () => {
    afterEach(() => {
        cleanup();
        routerDelete.mockClear();
    });

    it('renders the study plan row with its program name', () => {
        renderPage();
        const table = screen.getByRole('table');
        expect(within(table).getByText('Plan 2020')).toBeInTheDocument();
        expect(within(table).getByText('Ingeniería en Sistemas')).toBeInTheDocument();
    });

    it('opens the edit modal pre-filled with the program selected', () => {
        renderPage();
        fireEvent.click(screen.getByRole('button', { name: 'Editar' }));
        const dialog = screen.getByRole('dialog');
        expect(within(dialog).getByDisplayValue('PE-2020')).toBeInTheDocument();
        const select = within(dialog).getByRole('combobox', {
            name: /Programa/,
        }) as HTMLSelectElement;
        expect(select.value).toBe('10');
    });

    it('fires router.delete on delete confirm', () => {
        renderPage();
        fireEvent.click(screen.getByRole('button', { name: 'Eliminar' }));
        const dialog = screen.getByRole('dialog');
        fireEvent.click(within(dialog).getByRole('button', { name: 'Eliminar' }));
        expect(routerDelete).toHaveBeenCalledWith(
            '/admin/catalogs/study-plans/30',
            expect.objectContaining({ preserveScroll: true }),
        );
    });
});
