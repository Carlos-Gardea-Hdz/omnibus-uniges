import { useLocale } from '@/Contexts/LocaleContext';
import type { GraduationStatus } from '@/types/generated';

/**
 * Nine-step progress bar for the graduation workflow. Mirrors the backend
 * GraduationStatus::step() ordering (1–9) EXACTLY — see the enum's step()
 * method. The `form_b_rejected` branch keeps its own step number (3) but is
 * coloured red so a rejection reads as a problem, not as lost progress.
 *
 * NOTE: `generated.d.ts` is a declaration file (no runtime emit), so the
 * GraduationStatus enum is imported as a *type only*. The runtime map below is
 * keyed by the enum's string values — which are exactly the values broadcast
 * over the wire — keeping us in lockstep with the backend without relying on a
 * non-emitting enum at runtime.
 *
 * Accessibility: exposed as an ARIA progressbar with min/max/now and a text
 * label, so screen-reader users get the same "step N of 9" signal as the
 * visual fill. Decorative colour is paired with the numeric/textual state.
 */
const STEP_BY_STATUS: Record<GraduationStatus, number> = {
    form_b_pending: 1,
    form_b_review: 2,
    form_b_rejected: 3,
    annexes_pending: 4,
    annex_iii_pending: 5,
    payment_pending: 6,
    jury_assigned: 7,
    ceremony_scheduled: 8,
    graduated: 9,
};

const TOTAL_STEPS = 9;

interface GraduationProgressProps {
    status: GraduationStatus;
    /**
     * Optional bar-fill percentage pushed live over the WebSocket
     * (StudentStatusChanged.progress). When present it drives the fill width
     * directly, so the live bar never recomputes a number that diverges from
     * the backend's round(step / 9 * 100).
     */
    progress?: number;
}

export default function GraduationProgress({ status, progress }: GraduationProgressProps) {
    const { t } = useLocale();

    const current = STEP_BY_STATUS[status] ?? 1;
    const rejected = status === 'form_b_rejected';
    const percent = progress ?? Math.round((current / TOTAL_STEPS) * 100);

    const statusLabel = t(`status.${status}`);
    const stepLabel = t('progress.step')
        .replace('{current}', String(current))
        .replace('{total}', String(TOTAL_STEPS));

    return (
        <div className="flex flex-col gap-2">
            <div className="flex items-baseline justify-between">
                <span className="text-sm font-medium text-fg">{statusLabel}</span>
                <span className="text-xs text-fg-muted">{stepLabel}</span>
            </div>

            <div
                role="progressbar"
                aria-valuemin={0}
                aria-valuemax={TOTAL_STEPS}
                aria-valuenow={current}
                aria-valuetext={`${stepLabel} — ${statusLabel}`}
                className="h-2.5 w-full overflow-hidden rounded-full bg-border"
            >
                <div
                    className={`h-full rounded-full transition-[width] duration-500 ${
                        rejected ? 'bg-danger' : 'bg-accent'
                    }`}
                    style={{ width: `${percent}%` }}
                />
            </div>

            <ol className="flex w-full gap-1" aria-hidden="true">
                {Array.from({ length: TOTAL_STEPS }, (_, index) => {
                    const stepNumber = index + 1;
                    const done = stepNumber <= current;
                    return (
                        <li
                            key={stepNumber}
                            className={`h-1.5 flex-1 rounded-full ${
                                done ? (rejected ? 'bg-danger' : 'bg-accent') : 'bg-border'
                            }`}
                        />
                    );
                })}
            </ol>
        </div>
    );
}
