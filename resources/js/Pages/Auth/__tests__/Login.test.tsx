import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, render, screen } from '@testing-library/react';
import { LocaleProvider } from '@/Contexts/LocaleContext';
import { ThemeProvider } from '@/Contexts/ThemeContext';

/*
 * Unit test for the Login page. The page is driven by Inertia's useForm; we mock
 * @inertiajs/react so it renders in isolation (no Inertia runtime) and so we can
 * inject a server validation error to prove it surfaces inline on the email
 * field — AUTH-01 returns a single generic credential message flashed on
 * `email`. The form `data`/`errors` are controllable per test.
 */

type FormState = {
    data: Record<string, unknown>;
    errors: Record<string, string>;
};

const formState: FormState = { data: { email: '', password: '', remember: false }, errors: {} };

vi.mock('@inertiajs/react', () => ({
    Head: ({ title }: { title?: string }) => <title>{title}</title>,
    useForm: () => ({
        data: formState.data,
        setData: (key: string, value: unknown) => {
            formState.data[key] = value;
        },
        errors: formState.errors,
        processing: false,
        post: vi.fn(),
        reset: vi.fn(),
        clearErrors: vi.fn(),
    }),
}));

// Imported after the mock so the page picks up the mocked Inertia module.
import Login from '@/Pages/Auth/Login';

function renderLogin() {
    return render(
        <ThemeProvider>
            <LocaleProvider>
                <Login />
            </LocaleProvider>
        </ThemeProvider>,
    );
}

describe('Login page', () => {
    afterEach(() => {
        cleanup();
        formState.data = { email: '', password: '', remember: false };
        formState.errors = {};
    });

    it('renders the login form with a submit control', () => {
        const { container } = renderLogin();

        expect(container.querySelector('form')).toBeInTheDocument();
        expect(container.querySelector('[type="submit"]')).toBeInTheDocument();
        // email + password fields plus the remember-me checkbox.
        expect(container.querySelector('input[type="email"]')).toBeInTheDocument();
        expect(container.querySelector('input[type="password"]')).toBeInTheDocument();
        expect(container.querySelector('input[type="checkbox"]')).toBeInTheDocument();
    });

    it('surfaces the generic credential error inline', () => {
        formState.errors = { email: 'These credentials do not match our records.' };

        renderLogin();

        const alert = screen.getByRole('alert');
        expect(alert).toHaveTextContent(/credentials|registros|coinciden/i);
    });
});
