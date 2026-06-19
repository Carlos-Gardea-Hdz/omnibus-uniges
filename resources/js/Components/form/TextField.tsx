import { useId, type InputHTMLAttributes } from 'react';
import FormError from '@/Components/form/FormError';

/**
 * Labelled text input wired for accessibility:
 * - explicit <label htmlFor> association
 * - `aria-describedby` → error node, `aria-invalid` on error
 * - `aria-required` mirrors the required flag
 * - ≥ 44px touch target (h-11), visible focus ring inherited from :focus-visible
 *
 * Native `type` (text/number/date/tel/...) is forwarded so the same control
 * backs every textual field in Form B.
 */
type NativeProps = Omit<
    InputHTMLAttributes<HTMLInputElement>,
    'id' | 'value' | 'onChange' | 'className'
>;

interface TextFieldProps extends NativeProps {
    label: string;
    value: string | number;
    onChange: (value: string) => void;
    error?: string;
    required?: boolean;
    hint?: string;
}

export default function TextField({
    label,
    value,
    onChange,
    error,
    required = false,
    hint,
    type = 'text',
    ...rest
}: TextFieldProps) {
    const inputId = useId();
    const errorId = `${inputId}-error`;
    const hintId = `${inputId}-hint`;

    const describedBy =
        [hint ? hintId : null, error ? errorId : null].filter(Boolean).join(' ') || undefined;

    return (
        <div className="flex flex-col">
            <label htmlFor={inputId} className="mb-1 text-sm font-medium text-fg">
                {label}
                {required ? (
                    <span aria-hidden="true" className="ml-0.5 text-danger">
                        *
                    </span>
                ) : null}
            </label>

            {hint ? (
                <span id={hintId} className="mb-1 text-xs text-fg-muted">
                    {hint}
                </span>
            ) : null}

            <input
                id={inputId}
                type={type}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                required={required}
                aria-required={required || undefined}
                aria-invalid={error ? true : undefined}
                aria-describedby={describedBy}
                className="h-11 rounded-md border border-border bg-surface-raised px-3 text-fg transition-colors placeholder:text-fg-muted focus-visible:border-accent disabled:opacity-60 aria-[invalid=true]:border-danger"
                {...rest}
            />

            <FormError id={errorId} message={error} />
        </div>
    );
}
