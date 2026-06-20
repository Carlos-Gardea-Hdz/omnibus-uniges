import { useForm } from '@inertiajs/react';
import { useLocale } from '@/Contexts/LocaleContext';

/**
 * Logout control (SPEC §7.1, AUTH-06) — a reusable button any authenticated
 * page can drop into its header nav (alongside LanguageSwitcher /
 * DarkModeToggle). It POSTs to `/logout` via Inertia's useForm so the request
 * carries the CSRF token and is a real state-changing POST (never a GET on a
 * link). The server destroys the session, clears the cookie and redirects to
 * the login page — including demo sessions.
 *
 * Native <button>, labelled, keyboard-operable, ≥ 44px touch target (h-11).
 */
export default function LogoutButton() {
    const { t } = useLocale();
    const { post, processing } = useForm({});

    const submit = () => {
        post('/logout');
    };

    return (
        <button
            type="button"
            onClick={submit}
            disabled={processing}
            aria-label={t('nav.logout')}
            title={t('nav.logout')}
            className="inline-flex h-9 min-w-9 items-center justify-center rounded-md border border-border bg-surface-raised px-3 text-sm font-medium text-fg transition-colors hover:bg-border disabled:opacity-60"
        >
            {t('nav.logout')}
        </button>
    );
}
