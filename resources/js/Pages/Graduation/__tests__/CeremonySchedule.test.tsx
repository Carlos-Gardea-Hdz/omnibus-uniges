import { useState } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import { LocaleProvider } from '@/Contexts/LocaleContext';

/*
 * Unit test for the staff ceremony page (CONTRACT §12). The page embeds a
 * per-student CeremonyScheduleForm driven by Inertia's useForm, and a
 * "mark as graduated" button driven by router.post. We mock @inertiajs/react so
 * the page renders in isolation (no Inertia runtime). The mocked `useForm` is
 * *stateful* — it holds `data` in a real React `useState`, so each input's
 * onChange re-renders the form exactly as the real hook would. That is what lets
 * us exercise the client date guard interactively.
 *
 * Guards under test (CONTRACT §10/§12):
 *   - schedule form: a past / weekend / before-08:00 / 18:00+ datetime disables
 *     submit and shows a guard message; a future weekday at 10:00 (with a
 *     location) enables it. The server App\Rules\CeremonyDate stays authoritative.
 *   - graduate button: disabled when can_graduate=false, enabled when true.
 */
// Hoisted so the vi.mock factory (also hoisted) can safely reference it.
const { routerPost } = vi.hoisted(() => ({ routerPost: vi.fn() }));

vi.mock('@inertiajs/react', () => ({
    Head: ({ title }: { title?: string }) => <title>{title}</title>,
    Link: ({ children, ...props }: React.ComponentProps<'a'>) => <a {...props}>{children}</a>,
    router: { post: routerPost, reload: vi.fn() },
    // Stateful useForm stand-in: real React state so setData re-renders, which
    // the client date guard depends on. errors stay empty (server-side).
    useForm: <T extends Record<string, unknown>>(initial: T) => {
        const [data, setDataState] = useState<T>(initial);
        const setData = (key: string, value: unknown) =>
            setDataState((prev) => ({ ...prev, [key]: value }));
        return {
            data,
            setData,
            errors: {} as Record<string, string>,
            processing: false,
            post: vi.fn(),
            put: vi.fn(),
            transform: vi.fn(),
            reset: vi.fn(),
            clearErrors: vi.fn(),
        };
    },
    usePage: () => ({ props: { flash: {} } }),
}));

// Imported after the mock so the page picks up the mocked Inertia module.
import CeremonySchedule from '@/Pages/Graduation/CeremonySchedule';

/** Build a "YYYY-MM-DDTHH:mm" datetime-local string from a Date (local time). */
function toLocalInput(date: Date): string {
    const pad = (n: number) => String(n).padStart(2, '0');
    return (
        `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}` +
        `T${pad(date.getHours())}:${pad(date.getMinutes())}`
    );
}

/** Next weekday (Mon–Fri) strictly in the future, at the given local hour:min. */
function nextWeekdayAt(hour: number, minute: number): string {
    const date = new Date();
    date.setDate(date.getDate() + 3); // jump clear of "today" boundaries
    while (date.getDay() === 0 || date.getDay() === 6) {
        date.setDate(date.getDate() + 1);
    }
    date.setHours(hour, minute, 0, 0);
    return toLocalInput(date);
}

/** Next Saturday strictly in the future, at the given local hour. */
function nextSaturdayAt(hour: number): string {
    const date = new Date();
    date.setDate(date.getDate() + 1);
    while (date.getDay() !== 6) {
        date.setDate(date.getDate() + 1);
    }
    date.setHours(hour, 0, 0, 0);
    return toLocalInput(date);
}

/** A weekday in the PAST at a valid business hour. */
function pastWeekdayAt(hour: number): string {
    const date = new Date();
    date.setDate(date.getDate() - 10);
    while (date.getDay() === 0 || date.getDay() === 6) {
        date.setDate(date.getDate() - 1);
    }
    date.setHours(hour, 0, 0, 0);
    return toLocalInput(date);
}

const schedulingRow = {
    id: 7,
    control_number: '20210001',
    full_name: 'Estudiante Uno',
    program_name: 'Ingeniería en Sistemas',
    graduation_type_name: 'Tesis',
};

const graduatableRow = {
    id: 20,
    control_number: '20200005',
    full_name: 'Estudiante Listo',
    ceremony_date: '2026-06-10T10:00:00-06:00',
    ceremony_location: 'Auditorio Central',
    can_graduate: true,
};

const notReadyRow = {
    id: 21,
    control_number: '20200006',
    full_name: 'Estudiante Pendiente',
    ceremony_date: '2099-06-10T10:00:00-06:00',
    ceremony_location: 'Auditorio Central',
    can_graduate: false,
};

const defaultProps = {
    scheduling: { data: [schedulingRow] },
    graduating: { data: [graduatableRow, notReadyRow] },
};

function renderPage(props: Partial<typeof defaultProps> = {}) {
    return render(
        <LocaleProvider>
            <CeremonySchedule {...defaultProps} {...props} />
        </LocaleProvider>,
    );
}

/** The schedule form's inputs (datetime first, location second). */
function scheduleInputs(): { date: HTMLInputElement; location: HTMLInputElement } {
    const form = document.querySelector('form') as HTMLFormElement;
    const date = form.querySelector('input[type="datetime-local"]') as HTMLInputElement;
    const location = form.querySelector('input[type="text"]') as HTMLInputElement;
    return { date, location };
}

function scheduleButton(): HTMLButtonElement {
    const form = document.querySelector('form') as HTMLFormElement;
    return within(form).getByRole('button') as HTMLButtonElement;
}

const guardRe = /futura|future|día hábil|weekday|horario|business hours/i;

describe('CeremonySchedule — client date guard', () => {
    afterEach(() => {
        cleanup();
        routerPost.mockClear();
    });

    it('keeps schedule submit disabled until a valid date and location are entered', () => {
        renderPage();
        expect(scheduleButton()).toBeDisabled();
    });

    it('enables schedule submit for a future weekday at 10:00 with a location', () => {
        renderPage();
        const { date, location } = scheduleInputs();

        fireEvent.change(date, { target: { value: nextWeekdayAt(10, 0) } });
        fireEvent.change(location, { target: { value: 'Auditorio Central' } });

        expect(screen.queryByText(guardRe)).not.toBeInTheDocument();
        expect(scheduleButton()).not.toBeDisabled();
    });

    it('disables submit and shows a guard for a past date', () => {
        renderPage();
        const { date, location } = scheduleInputs();

        fireEvent.change(date, { target: { value: pastWeekdayAt(10) } });
        fireEvent.change(location, { target: { value: 'Auditorio Central' } });

        expect(screen.getByText(guardRe)).toBeInTheDocument();
        expect(scheduleButton()).toBeDisabled();
    });

    it('disables submit and shows a guard for a weekend date', () => {
        renderPage();
        const { date, location } = scheduleInputs();

        fireEvent.change(date, { target: { value: nextSaturdayAt(10) } });
        fireEvent.change(location, { target: { value: 'Auditorio Central' } });

        expect(screen.getByText(guardRe)).toBeInTheDocument();
        expect(scheduleButton()).toBeDisabled();
    });

    it('disables submit and shows a guard before 08:00 (07:30)', () => {
        renderPage();
        const { date, location } = scheduleInputs();

        fireEvent.change(date, { target: { value: nextWeekdayAt(7, 30) } });
        fireEvent.change(location, { target: { value: 'Auditorio Central' } });

        expect(screen.getByText(guardRe)).toBeInTheDocument();
        expect(scheduleButton()).toBeDisabled();
    });

    it('disables submit and shows a guard at/after 18:00 (18:30)', () => {
        renderPage();
        const { date, location } = scheduleInputs();

        fireEvent.change(date, { target: { value: nextWeekdayAt(18, 30) } });
        fireEvent.change(location, { target: { value: 'Auditorio Central' } });

        expect(screen.getByText(guardRe)).toBeInTheDocument();
        expect(scheduleButton()).toBeDisabled();
    });
});

describe('CeremonySchedule — graduate button', () => {
    afterEach(() => {
        cleanup();
        routerPost.mockClear();
    });

    it('enables graduate for a ready student and disables it for a not-ready one', () => {
        renderPage();
        const buttons = screen.getAllByRole('button', { name: /titulado|graduated/i });
        // Two graduate buttons, in row order: ready (enabled), not-ready (disabled).
        expect(buttons).toHaveLength(2);
        expect(buttons[0]).not.toBeDisabled();
        expect(buttons[1]).toBeDisabled();
    });

    it('posts to the graduate route when a ready student is graduated', () => {
        renderPage();
        const buttons = screen.getAllByRole('button', { name: /titulado|graduated/i });

        fireEvent.click(buttons[0]!);

        expect(routerPost).toHaveBeenCalledWith(
            '/admin/graduation/ceremony/20/graduate',
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('does not post when a not-ready student button is clicked', () => {
        renderPage();
        const buttons = screen.getAllByRole('button', { name: /titulado|graduated/i });

        fireEvent.click(buttons[1]!);

        expect(routerPost).not.toHaveBeenCalled();
    });
});
