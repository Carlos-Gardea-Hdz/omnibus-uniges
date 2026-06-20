import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { LocaleProvider } from '@/Contexts/LocaleContext';
import { ThemeProvider } from '@/Contexts/ThemeContext';

/*
 * Unit test for the demo preset chooser (CONTRACT §11/§15). The page lists the
 * six backend presets as cards and POSTs the chosen enum value to /demo-login.
 * We mock @inertiajs/react so it renders without the Inertia runtime and so
 * router.post is a spy we can assert the posted `preset` against.
 */
// Hoisted so the vi.mock factory (also hoisted) can safely reference it —
// otherwise the factory runs before this top-level const initialises (TDZ).
const { routerPost } = vi.hoisted(() => ({ routerPost: vi.fn() }));

vi.mock('@inertiajs/react', () => ({
    Head: ({ title }: { title?: string }) => <title>{title}</title>,
    router: { post: routerPost },
}));

// Imported after the mock so the page picks up the mocked Inertia module.
import DemoChooser from '@/Pages/Auth/DemoChooser';

/** The six snake_case rows DemoLoginController::create() renders. */
const PRESETS = [
    { value: 'sustentante_1', role: 'student', title_key: 'demo.preset.sustentante_1.title', description_key: 'demo.preset.sustentante_1.desc', control_number: '20180001' },
    { value: 'sustentante_2', role: 'student', title_key: 'demo.preset.sustentante_2.title', description_key: 'demo.preset.sustentante_2.desc', control_number: '20180002' },
    { value: 'sustentante_3', role: 'student', title_key: 'demo.preset.sustentante_3.title', description_key: 'demo.preset.sustentante_3.desc', control_number: '20180003' },
    { value: 'sustentante_4', role: 'student', title_key: 'demo.preset.sustentante_4.title', description_key: 'demo.preset.sustentante_4.desc', control_number: '20180004' },
    { value: 'personal', role: 'assistant_secretary', title_key: 'demo.preset.personal.title', description_key: 'demo.preset.personal.desc', control_number: null },
    { value: 'admin', role: 'admin', title_key: 'demo.preset.admin.title', description_key: 'demo.preset.admin.desc', control_number: null },
] as unknown as React.ComponentProps<typeof DemoChooser>['presets'];

function renderChooser() {
    return render(
        <ThemeProvider>
            <LocaleProvider>
                <DemoChooser presets={PRESETS} />
            </LocaleProvider>
        </ThemeProvider>,
    );
}

describe('DemoChooser page', () => {
    afterEach(() => {
        cleanup();
        routerPost.mockClear();
    });

    it('renders exactly six preset cards', () => {
        renderChooser();

        // Each card is a native button; plus LanguageSwitcher + DarkModeToggle
        // in the header chrome, so we count by accessible card titles instead.
        const items = screen.getAllByRole('listitem');
        expect(items).toHaveLength(6);
    });

    it('POSTs the matching preset value to /demo-login when a card is chosen', async () => {
        const user = userEvent.setup();
        renderChooser();

        const buttons = screen
            .getAllByRole('listitem')
            .map((item) => item.querySelector('button') as HTMLButtonElement);

        // Activate the first card (sustentante_1).
        await user.click(buttons[0]!);
        expect(routerPost).toHaveBeenCalledWith('/demo-login', { preset: 'sustentante_1' });

        // And the last card (admin).
        await user.click(buttons[5]!);
        expect(routerPost).toHaveBeenCalledWith('/demo-login', { preset: 'admin' });
    });

    it('shows the control-number chip only for student presets', () => {
        renderChooser();

        // The four student presets carry their fictional control numbers.
        expect(screen.getByText('20180001')).toBeInTheDocument();
        expect(screen.getByText('20180004')).toBeInTheDocument();
    });
});
