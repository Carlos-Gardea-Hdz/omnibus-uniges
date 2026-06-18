import { useTheme, type ThemePreference } from '@/Contexts/ThemeContext';
import { useLocale } from '@/Contexts/LocaleContext';

const ORDER: ThemePreference[] = ['light', 'dark', 'system'];

const ICON: Record<ThemePreference, string> = {
    light: '☀️',
    dark: '🌙',
    system: '💻',
};

/**
 * Cycles light → dark → system. Native <button>, keyboard-operable,
 * labelled for screen readers, and ≥ 24×24px target (a11y §5).
 */
export default function DarkModeToggle() {
    const { preference, setPreference } = useTheme();
    const { t } = useLocale();

    const next = ORDER[(ORDER.indexOf(preference) + 1) % ORDER.length] ?? 'system';

    return (
        <button
            type="button"
            onClick={() => setPreference(next)}
            aria-label={`${t('theme.toggle')}: ${t(`theme.${preference}`)}`}
            title={t('theme.toggle')}
            className="inline-flex h-9 min-w-9 items-center justify-center rounded-md border border-border bg-surface-raised px-2 text-fg transition-colors hover:bg-border"
        >
            <span aria-hidden="true">{ICON[preference]}</span>
            <span className="sr-only">{t(`theme.${preference}`)}</span>
        </button>
    );
}
