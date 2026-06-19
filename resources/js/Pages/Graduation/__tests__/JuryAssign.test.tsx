import { useState } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import { LocaleProvider } from '@/Contexts/LocaleContext';

/*
 * Unit test for the staff jury-assignment page (CONTRACT §14). The page embeds
 * a per-student JuryAssignForm driven by Inertia's useForm; we mock
 * @inertiajs/react so it renders in isolation (no Inertia runtime). The mocked
 * `useForm` is *stateful* — it holds `data` in a real React `useState`, so each
 * select's onChange re-renders the form exactly as the real hook would. That is
 * what lets us exercise the all-different guard interactively.
 *
 * The guard under test (CONTRACT §12): choosing the same professor in two
 * non-empty selects disables submit and shows the distinct-professors message;
 * four distinct picks (with or without a substitute) enable submit. The server
 * `Different` rules + DB CHECK remain authoritative.
 */
vi.mock('@inertiajs/react', () => ({
    Head: ({ title }: { title?: string }) => <title>{title}</title>,
    Link: ({ children, ...props }: React.ComponentProps<'a'>) => <a {...props}>{children}</a>,
    router: { post: vi.fn(), reload: vi.fn() },
    // Stateful useForm stand-in: real React state so setData re-renders, which
    // the all-different guard depends on. errors stay empty (server-side).
    useForm: <T extends Record<string, unknown>>(initial: T) => {
        const [data, setDataState] = useState<T>(initial);
        const setData = (key: string, value: unknown) =>
            setDataState((prev) => ({ ...prev, [key]: value }));
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
    usePage: () => ({ props: {} }),
}));

// Imported after the mock so the page picks up the mocked Inertia module.
import JuryAssign from '@/Pages/Graduation/JuryAssign';

const professors = [
    { id: 1, full_name: 'Dra. Ana Pérez López' },
    { id: 2, full_name: 'Dr. Beto Ruiz Mora' },
    { id: 3, full_name: 'Dra. Carla Díaz Soto' },
    { id: 4, full_name: 'Dr. Diego Vega Luna' },
];

/** One verified student so the selects are enabled and submit is reachable. */
const verifiedStudent = {
    id: 7,
    control_number: '20210001',
    full_name: 'Estudiante Uno',
    program_name: 'Ingeniería en Sistemas',
    graduation_type_name: 'Tesis',
    payment_reference: 'PAY-12345678',
    paid_at: '2026-06-19T10:00:00Z',
    payment_verified: true,
};

const defaultProps = {
    students: { data: [verifiedStudent] },
    professors,
    roles: ['president', 'secretary', 'vocal', 'substitute'],
};

function renderPage(props: Partial<typeof defaultProps> = {}) {
    return render(
        <LocaleProvider>
            <JuryAssign {...defaultProps} {...props} />
        </LocaleProvider>,
    );
}

/** Selects, in the President/Secretary/Vocal/Substitute order they render. */
function selects(): HTMLSelectElement[] {
    const form = document.querySelector('form');
    return Array.from(form?.querySelectorAll('select') ?? []) as HTMLSelectElement[];
}

/** The four jury selects as a fixed tuple (asserts the form rendered them). */
function jurySelects(): [
    HTMLSelectElement,
    HTMLSelectElement,
    HTMLSelectElement,
    HTMLSelectElement,
] {
    const all = selects();
    expect(all).toHaveLength(4);
    return [all[0]!, all[1]!, all[2]!, all[3]!];
}

function submitButton(): HTMLButtonElement {
    const form = document.querySelector('form') as HTMLFormElement;
    return within(form).getByRole('button') as HTMLButtonElement;
}

describe('JuryAssign page — all-different guard', () => {
    afterEach(() => {
        cleanup();
    });

    it('renders a jury form with four professor selects for a verified student', () => {
        renderPage();
        expect(selects()).toHaveLength(4);
    });

    it('disables submit and shows the distinct message when two selects pick the same professor', () => {
        renderPage();
        const [president, secretary, vocal] = jurySelects();

        fireEvent.change(president, { target: { value: '1' } });
        fireEvent.change(secretary, { target: { value: '1' } });
        fireEvent.change(vocal, { target: { value: '2' } });

        expect(
            screen.getByText(/profesor distinto|different professor/i),
        ).toBeInTheDocument();
        expect(submitButton()).toBeDisabled();
    });

    it('enables submit when the three required selects are four-distinct (no substitute)', () => {
        renderPage();
        const [president, secretary, vocal] = jurySelects();

        fireEvent.change(president, { target: { value: '1' } });
        fireEvent.change(secretary, { target: { value: '2' } });
        fireEvent.change(vocal, { target: { value: '3' } });

        expect(
            screen.queryByText(/profesor distinto|different professor/i),
        ).not.toBeInTheDocument();
        expect(submitButton()).not.toBeDisabled();
    });

    it('enables submit when all four selects are distinct (with substitute)', () => {
        renderPage();
        const [president, secretary, vocal, substitute] = jurySelects();

        fireEvent.change(president, { target: { value: '1' } });
        fireEvent.change(secretary, { target: { value: '2' } });
        fireEvent.change(vocal, { target: { value: '3' } });
        fireEvent.change(substitute, { target: { value: '4' } });

        expect(
            screen.queryByText(/profesor distinto|different professor/i),
        ).not.toBeInTheDocument();
        expect(submitButton()).not.toBeDisabled();
    });

    it('keeps submit disabled until the required selects are filled', () => {
        renderPage();
        expect(submitButton()).toBeDisabled();
    });

    it('disables the jury selects until the payment is verified', () => {
        renderPage({
            students: {
                data: [{ ...verifiedStudent, id: 8, payment_verified: false }],
            },
        });

        for (const select of selects()) {
            expect(select).toBeDisabled();
        }
    });
});
