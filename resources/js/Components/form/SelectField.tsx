import { useId } from 'react';
import FormError from '@/Components/form/FormError';

export interface SelectOption {
    value: string | number;
    label: string;
}

/**
 * Labelled <select> with the same a11y contract as TextField:
 * explicit label, `aria-describedby` error wiring, `aria-invalid`,
 * ≥ 44px target, visible focus ring. An optional placeholder renders as a
 * disabled, empty-valued first option so "nothing chosen" stays distinct.
 */
interface SelectFieldProps {
    label: string;
    value: string | number;
    onChange: (value: string) => void;
    options: readonly SelectOption[];
    error?: string;
    required?: boolean;
    placeholder?: string;
    disabled?: boolean;
}

export default function SelectField({
    label,
    value,
    onChange,
    options,
    error,
    required = false,
    placeholder,
    disabled = false,
}: SelectFieldProps) {
    const selectId = useId();
    const errorId = `${selectId}-error`;

    return (
        <div className="flex flex-col">
            <label htmlFor={selectId} className="mb-1 text-sm font-medium text-fg">
                {label}
                {required ? (
                    <span aria-hidden="true" className="ml-0.5 text-danger">
                        *
                    </span>
                ) : null}
            </label>

            <select
                id={selectId}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                required={required}
                disabled={disabled}
                aria-required={required || undefined}
                aria-invalid={error ? true : undefined}
                aria-describedby={error ? errorId : undefined}
                className="h-11 rounded-md border border-border bg-surface-raised px-3 text-fg transition-colors focus-visible:border-accent disabled:opacity-60 aria-[invalid=true]:border-danger"
            >
                {placeholder !== undefined ? (
                    <option value="" disabled>
                        {placeholder}
                    </option>
                ) : null}
                {options.map((option) => (
                    <option key={String(option.value)} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>

            <FormError id={errorId} message={error} />
        </div>
    );
}
