import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, render, screen } from '@testing-library/react';
import { LocaleProvider } from '@/Contexts/LocaleContext';

/*
 * Unit test for the Formato B intake page. The page is driven by Inertia's
 * useForm; we mock @inertiajs/react so the page renders in isolation (no
 * Inertia runtime) and so we can inject a server validation error to prove it
 * surfaces inline. The form `data`/`errors` are controllable per test.
 */

type FormState = {
    data: Record<string, unknown>;
    errors: Record<string, string>;
};

const formState: FormState = { data: {}, errors: {} };

vi.mock('@inertiajs/react', () => ({
    // Minimal Inertia surface used by the page.
    Head: ({ title }: { title?: string }) => <title>{title}</title>,
    Link: ({ children, ...props }: React.ComponentProps<'a'>) => (
        <a {...props}>{children}</a>
    ),
    useForm: () => ({
        data: formState.data,
         
        setData: (key: string, value: unknown) => {
            formState.data[key] = value;
        },
        errors: formState.errors,
        processing: false,
        post: vi.fn(),
        put: vi.fn(),
        transform: vi.fn(),
        reset: vi.fn(),
        clearErrors: vi.fn(),
    }),
    usePage: () => ({ props: {} }),
}));

// Imported after the mock so the page picks up the mocked Inertia module.
import FormB from '@/Pages/Student/FormB';

/**
 * Props the controller shapes for the page: nested snake_case `catalogs` and
 * the acting student's own record (null here — a brand-new applicant).
 */
const defaultProps = {
    catalogs: {
        programs: [{ id: 1, name: 'Ingeniería en Sistemas Computacionales' }],
        graduation_types: [{ id: 1, name: 'Tesis', requires_advisor: true }],
        study_plans: [{ id: 1, name: 'Plan 2019', program_id: 1 }],
        professors: [{ id: 1, name: 'Dra. Pérez' }],
    },
    student: null,
};

function renderFormB(props: Partial<typeof defaultProps> = {}) {
    return render(
        <LocaleProvider>
            <FormB {...defaultProps} {...props} />
        </LocaleProvider>,
    );
}

describe('FormB page', () => {
    afterEach(() => {
        cleanup();
        formState.data = {};
        formState.errors = {};
    });

    it('renders the Formato B intake form', () => {
        const { container } = renderFormB();

        // The form element proves the page mounted, regardless of i18n labels.
        expect(container.querySelector('form')).toBeInTheDocument();
        // …with a submit control to send it.
        expect(container.querySelector('[type="submit"]')).toBeInTheDocument();
    });

    it('surfaces a server validation error inline', () => {
        formState.errors = { control_number: 'El número de control no es válido.' };

        renderFormB();

        const alert = screen.getByRole('alert');
        expect(alert).toHaveTextContent(/no es válido|invalid/i);
    });
});
