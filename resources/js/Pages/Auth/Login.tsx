import { Head, useForm } from '@inertiajs/react';
import { useId } from 'react';
import type { LoginData } from '@/types/generated';
import { useLocale } from '@/Contexts/LocaleContext';
import LanguageSwitcher from '@/Components/LanguageSwitcher';
import DarkModeToggle from '@/Components/DarkModeToggle';
import TextField from '@/Components/form/TextField';

/**
 * Login page (SPEC §7.1, AUTH-01) — email + password + "remember me".
 *
 * The typed `useForm<LoginData>` is the front-end SSOT companion to the Spatie
 * Data DTO `App\Domain\Identity\Data\LoginData`, which owns validation
 * server-side. `LoginData` is imported type-only from the generated `.d.ts`
 * (types-only file — no runtime value), so the form shape can never drift from
 * the backend contract. Snake_case throughout to match the DTO and the POST
 * payload the LoginController expects.
 *
 * Theme- and locale-aware: it renders the shared LanguageSwitcher +
 * DarkModeToggle, and every string flows through `useLocale().t`. The page is
 * stateless about *which* error came back — per AUTH-01 the server returns the
 * SAME generic message for not-found and wrong-password, surfaced inline on the
 * email field (the natural first field) and announced via the field's
 * `role="alert"` error node.
 */
export default function Login() {
    const { t } = useLocale();

    // useForm typed by the generated DTO contract. `remember` is a boolean per
    // LoginData; email/password are strings. Initial values mirror the shape.
    const { data, setData, post, processing, errors } = useForm<LoginData>({
        email: '',
        password: '',
        remember: false,
    });

    const rememberId = useId();

    // AUTH-01 returns one generic credential error; the backend flashes it on
    // `email`, so we treat that as the credential-level message and also pin a
    // password error if the server ever sends one.
    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        post('/login', { preserveScroll: true, onFinish: () => setData('password', '') });
    };

    return (
        <>
            <Head title={t('auth.login.title')} />
            <div className="flex min-h-dvh flex-col bg-surface text-fg">
                <header className="flex items-center justify-between border-b border-border px-6 py-4">
                    <span className="text-lg font-semibold text-gradient-primary">
                        {t('app.name')}
                    </span>
                    <nav className="flex items-center gap-2" aria-label="utilities">
                        <LanguageSwitcher />
                        <DarkModeToggle />
                    </nav>
                </header>

                <main
                    id="main"
                    className="mx-auto flex w-full max-w-md flex-1 flex-col justify-center px-6 py-12"
                >
                    <h1 className="text-2xl font-bold tracking-tight">{t('auth.login.title')}</h1>
                    <p className="mt-2 text-fg-muted">{t('auth.login.subtitle')}</p>

                    <form onSubmit={submit} noValidate className="mt-8 flex flex-col gap-5">
                        <TextField
                            label={t('auth.login.field.email')}
                            value={data.email}
                            onChange={(value) => setData('email', value)}
                            error={errors.email}
                            required
                            type="email"
                            inputMode="email"
                            autoComplete="email"
                            autoFocus
                        />

                        <TextField
                            label={t('auth.login.field.password')}
                            value={data.password}
                            onChange={(value) => setData('password', value)}
                            error={errors.password}
                            required
                            type="password"
                            autoComplete="current-password"
                        />

                        <label
                            htmlFor={rememberId}
                            className="flex cursor-pointer items-center gap-2 text-sm text-fg"
                        >
                            <input
                                id={rememberId}
                                type="checkbox"
                                checked={data.remember}
                                onChange={(event) => setData('remember', event.target.checked)}
                                className="h-4 w-4 rounded border-border bg-surface-raised text-accent focus-visible:border-accent"
                            />
                            {t('auth.login.remember')}
                        </label>

                        <div className="flex items-center gap-3">
                            <button
                                type="submit"
                                disabled={processing}
                                className="inline-flex h-11 items-center rounded-md bg-accent px-6 font-medium text-accent-fg transition-colors hover:bg-primary-dark disabled:opacity-60"
                            >
                                {t('auth.login.submit')}
                            </button>
                            {processing ? (
                                <span className="text-sm text-fg-muted" role="status">
                                    {t('auth.login.signing_in')}
                                </span>
                            ) : null}
                        </div>
                    </form>
                </main>
            </div>
        </>
    );
}
