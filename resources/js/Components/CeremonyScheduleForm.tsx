import { useForm } from '@inertiajs/react';
import { useLocale } from '@/Contexts/LocaleContext';
import TextField from '@/Components/form/TextField';

/**
 * Per-student ceremony-scheduling form (workflow step 7→8).
 *
 * Mirrors App\Domain\Ceremony\Data\ScheduleCeremonyData: a `ceremony_date`
 * (entered via a native `datetime-local` control) and a `ceremony_location`
 * string (max 200). Each student row owns its own Inertia `useForm`, so several
 * rows can be filled in independently without colliding (same isolation pattern
 * as JuryAssignForm / DocumentUploadForm).
 *
 * A client-side guard mirrors the authoritative server rule (App\Rules\
 * CeremonyDate): the picked datetime must be in the future, fall on a weekday
 * (Mon–Fri) and sit within business hours (08:00–17:59). When it fails, submit
 * is disabled and a guard message names the specific reason. The server remains
 * the single source of truth; `errors.ceremony_date` / `errors.ceremony_location`
 * surface its session errors (web validation = 302 + session errors, not 422).
 *
 * snake_case field names match the controller route payload and the DTO.
 */
interface CeremonyScheduleFormProps {
    studentId: number;
}

/** Mirrors App\Domain\Ceremony\Data\ScheduleCeremonyData. */
type ScheduleValues = {
    ceremony_date: string;
    ceremony_location: string;
};

const EMPTY: ScheduleValues = {
    ceremony_date: '',
    ceremony_location: '',
};

/**
 * Client mirror of App\Rules\CeremonyDate. Returns the first failing guard key
 * (a lang key under `admin.ceremony.guard.*`) or null when the value passes.
 * A blank value is treated as "no guard yet" — the required attribute and the
 * server handle emptiness — so the form does not show a guard before input.
 */
export function ceremonyDateGuard(value: string): string | null {
    if (value === '') {
        return null;
    }

    // `datetime-local` yields e.g. "2026-06-22T10:30"; parse it locally.
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return 'admin.ceremony.guard.future';
    }

    if (date.getTime() <= Date.now()) {
        return 'admin.ceremony.guard.future';
    }

    const day = date.getDay(); // 0 = Sunday, 6 = Saturday
    if (day === 0 || day === 6) {
        return 'admin.ceremony.guard.weekday';
    }

    const hour = date.getHours();
    // Boundary mirrors the server: 08:00 valid, last valid 17:59, 18:00 invalid.
    if (hour < 8 || hour >= 18) {
        return 'admin.ceremony.guard.hours';
    }

    return null;
}

export default function CeremonyScheduleForm({ studentId }: CeremonyScheduleFormProps) {
    const { t } = useLocale();

    const { data, setData, post, processing, errors } = useForm<ScheduleValues>(EMPTY);

    const guardKey = ceremonyDateGuard(data.ceremony_date);
    const locationFilled = data.ceremony_location.trim() !== '';
    const canSubmit =
        data.ceremony_date !== '' && guardKey === null && locationFilled && !processing;

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        if (!canSubmit) {
            return;
        }
        post(`/admin/graduation/ceremony/${studentId}/schedule`, { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} noValidate className="mt-4 flex flex-col gap-4">
            <div className="grid gap-4 sm:grid-cols-2">
                <TextField
                    type="datetime-local"
                    label={t('admin.ceremony.field.date')}
                    value={data.ceremony_date}
                    onChange={(value) => setData('ceremony_date', value)}
                    error={errors.ceremony_date}
                    required
                />
                <TextField
                    label={t('admin.ceremony.field.location')}
                    value={data.ceremony_location}
                    onChange={(value) => setData('ceremony_location', value)}
                    error={errors.ceremony_location}
                    required
                    maxLength={200}
                />
            </div>

            {/* Client guard message (role="alert" announces it). Server is authoritative. */}
            {guardKey ? (
                <p role="alert" className="text-sm font-medium text-danger">
                    {t(guardKey)}
                </p>
            ) : null}

            <div>
                <button
                    type="submit"
                    disabled={!canSubmit}
                    className="inline-flex h-11 items-center rounded-md bg-accent px-6 font-medium text-accent-fg transition-colors hover:bg-primary-dark disabled:opacity-60"
                >
                    {processing ? t('form.sending') : t('admin.ceremony.schedule')}
                </button>
            </div>
        </form>
    );
}
