import { Head, Link } from '@inertiajs/react';
import { useLocale } from '@/Contexts/LocaleContext';
import DarkModeToggle from '@/Components/DarkModeToggle';
import LanguageSwitcher from '@/Components/LanguageSwitcher';

interface WelcomeProps {
    appVersion: string;
}

export default function Welcome({ appVersion }: WelcomeProps) {
    const { t } = useLocale();

    return (
        <>
            <Head title={t('landing.welcome')} />
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
                    className="mx-auto flex w-full max-w-3xl flex-1 flex-col items-center justify-center px-6 text-center"
                >
                    <h1 className="motion-decorative animate-fade-in text-4xl font-bold tracking-tight sm:text-5xl">
                        {t('landing.welcome')}
                    </h1>
                    <p className="mt-4 max-w-xl text-lg text-fg-muted">
                        {t('landing.subtitle')}
                    </p>

                    <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
                        <Link
                            href="/login"
                            className="inline-flex h-11 items-center rounded-md bg-accent px-6 font-medium text-accent-fg transition-colors hover:bg-primary-dark"
                        >
                            {t('landing.cta.login')}
                        </Link>
                        <Link
                            href="/demo"
                            className="inline-flex h-11 items-center rounded-md border border-border bg-surface-raised px-6 font-medium text-fg transition-colors hover:bg-border"
                        >
                            {t('landing.cta.demo')}
                        </Link>
                    </div>
                </main>

                <footer className="border-t border-border px-6 py-4 text-center text-sm text-fg-muted">
                    {t('app.tagline')} · v{appVersion}
                </footer>
            </div>
        </>
    );
}
