import { useLocale } from '@/Contexts/LocaleContext';

/**
 * Toggles ES ↔ EN. Native <button>, labelled, keyboard-operable.
 */
export default function LanguageSwitcher() {
    const { locale, setLocale, t } = useLocale();
    const next = locale === 'es' ? 'en' : 'es';

    return (
        <button
            type="button"
            onClick={() => setLocale(next)}
            aria-label={t('locale.toggle')}
            title={t('locale.toggle')}
            className="inline-flex h-9 min-w-9 items-center justify-center rounded-md border border-border bg-surface-raised px-3 text-sm font-medium text-fg uppercase transition-colors hover:bg-border"
        >
            {locale}
        </button>
    );
}
