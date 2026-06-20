import { Head, router } from '@inertiajs/react';
import type { DemoPreset } from '@/types/generated';
import { useLocale } from '@/Contexts/LocaleContext';
import LanguageSwitcher from '@/Components/LanguageSwitcher';
import DarkModeToggle from '@/Components/DarkModeToggle';

/**
 * Demo preset chooser (SLICE 006, SPEC §13) — the public entry point reached
 * from the Welcome "Probar la demo" CTA (`GET /demo`). It lists the six demo
 * presets the backend exposes (four sustentante stages + staff + admin) as
 * accessible cards; choosing one POSTs the enum value to `/demo-login`, where
 * ProvisionDemoSessionAction mints an isolated, throw-away demo session and
 * logs the visitor straight into the matching role's landing screen.
 *
 * Props are snake_case, matching DemoLoginController::create() exactly (the
 * controller render payload is the single source of truth; an Inertia
 * prop-contract test enforces the shape). The `value` field carries the raw
 * DemoPreset enum value string — `DemoPreset` is imported TYPE-ONLY from the
 * generated `.d.ts` (a types-only file, no runtime value), so the prop shape
 * can never drift from the backend enum while never pulling a runtime import
 * into the Vite build.
 *
 * Welcome chrome is reused (LanguageSwitcher + DarkModeToggle), every string
 * flows through `useLocale().t`, and the cards are keyboard-operable native
 * <button>s (WCAG 2.2 AA, dark/light, ES/EN).
 */
interface DemoPresetRow {
    value: DemoPreset;
    role: string;
    title_key: string;
    description_key: string;
    control_number: string | null;
}

interface DemoChooserProps {
    presets: DemoPresetRow[];
}

export default function DemoChooser({ presets }: DemoChooserProps) {
    const { t } = useLocale();

    // POST the chosen preset value to demo.store. The value is already the raw
    // enum string from the backend; the server casts it back to DemoPreset and
    // rejects anything invalid with a 302 + session error (never a 422).
    const start = (preset: DemoPreset) => {
        router.post('/demo-login', { preset });
    };

    return (
        <>
            <Head title={t('demo.chooser.title')} />
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
                    className="mx-auto flex w-full max-w-4xl flex-1 flex-col px-6 py-12"
                >
                    <h1 className="text-2xl font-bold tracking-tight">
                        {t('demo.chooser.title')}
                    </h1>
                    <p className="mt-2 max-w-2xl text-fg-muted">
                        {t('demo.chooser.subtitle')}
                    </p>

                    <ul className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {presets.map((preset) => (
                            <li key={preset.value} className="flex">
                                <button
                                    type="button"
                                    onClick={() => start(preset.value)}
                                    className="flex w-full flex-col rounded-lg border border-border bg-surface-raised p-5 text-left transition-colors hover:border-accent hover:bg-border focus-visible:border-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                                >
                                    <span className="text-base font-semibold text-fg">
                                        {t(preset.title_key)}
                                    </span>
                                    <span className="mt-1 flex-1 text-sm text-fg-muted">
                                        {t(preset.description_key)}
                                    </span>
                                    {preset.control_number ? (
                                        <span className="mt-3 inline-flex w-fit items-center rounded-md border border-border bg-surface px-2 py-1 font-mono text-xs text-fg-muted">
                                            {preset.control_number}
                                        </span>
                                    ) : null}
                                    <span
                                        aria-hidden="true"
                                        className="mt-3 inline-flex h-9 items-center justify-center rounded-md bg-accent px-4 text-sm font-medium text-accent-fg"
                                    >
                                        {t('demo.start')}
                                    </span>
                                </button>
                            </li>
                        ))}
                    </ul>
                </main>
            </div>
        </>
    );
}
