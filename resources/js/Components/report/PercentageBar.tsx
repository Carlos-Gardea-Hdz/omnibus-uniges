/**
 * Accessible terminal-efficiency rate bar (SLICE 008). The rate is ALWAYS an
 * integer percent 0..100 from the server (D-RATE-INT) — never a float, never a
 * pre-formatted string.
 *
 * WCAG 2.2 AA: the bar is a real `role="progressbar"` with aria-valuenow /min /
 * max, and the percentage is ALSO rendered as visible `N%` text beside it, so
 * the colour fill is a redundant cue (never the only signal). The fill width is
 * the only dynamic style; the track + fill colours are static literal classes so
 * Tailwind v4 keeps them in the build.
 */
interface PercentageBarProps {
    /** Integer percent 0..100. */
    rate: number;
    /** Already-translated accessible label, e.g. the cohort/program name. */
    label: string;
}

export default function PercentageBar({ rate, label }: PercentageBarProps) {
    // Guard the visual width even if a caller passes an out-of-range value.
    const width = Math.min(100, Math.max(0, rate));

    return (
        <div className="flex items-center gap-3">
            <div
                role="progressbar"
                aria-valuenow={rate}
                aria-valuemin={0}
                aria-valuemax={100}
                aria-label={label}
                className="h-2 w-full min-w-24 overflow-hidden rounded-full bg-border"
            >
                <div
                    className="h-full rounded-full bg-accent transition-[width]"
                    style={{ width: `${width}%` }}
                />
            </div>
            <span className="w-10 shrink-0 text-right text-sm font-semibold tabular-nums text-fg">
                {rate}%
            </span>
        </div>
    );
}
