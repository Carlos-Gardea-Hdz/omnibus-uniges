import { useLocale } from '@/Contexts/LocaleContext';
import type { DocumentStatus } from '@/types/generated';

/**
 * Coloured status pill for a student document. Mirrors the backend
 * App\Domain\Graduation\Enums\DocumentStatus::color() mapping
 * (rejected→danger, approved→success, uploaded→primary, pending→warning) so
 * the visual state stays in lockstep with the server enum.
 *
 * NOTE: `generated.d.ts` is a declaration file (no runtime emit), so
 * `DocumentStatus` is imported as a *type only*. The runtime lookup below is
 * keyed by the enum's raw string values — exactly what the controller and the
 * broadcast payload put on the wire — never by a non-emitting enum value.
 *
 * Accessibility: colour is paired with a text label (the translated status),
 * so the state never depends on colour alone (WCAG 2.2 — 1.4.1 Use of Colour).
 */
interface DocumentStatusBadgeProps {
    status: string;
}

/** Status value → Tailwind colour token pair (border/background/text). */
const TONE_BY_STATUS: Record<DocumentStatus, string> = {
    rejected: 'border-danger/40 bg-danger/10 text-danger',
    approved: 'border-success/40 bg-success/10 text-success',
    uploaded: 'border-accent/40 bg-accent/10 text-accent',
    pending: 'border-warning/40 bg-warning/10 text-warning',
};

export default function DocumentStatusBadge({ status }: DocumentStatusBadgeProps) {
    const { t } = useLocale();

    const tone = TONE_BY_STATUS[status as DocumentStatus] ?? TONE_BY_STATUS.pending;

    return (
        <span
            className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium ${tone}`}
        >
            {t(`document_status.${status}`)}
        </span>
    );
}
