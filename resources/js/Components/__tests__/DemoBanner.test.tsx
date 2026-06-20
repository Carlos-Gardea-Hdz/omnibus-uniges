import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { LocaleProvider } from '@/Contexts/LocaleContext';

/*
 * Unit test for the demo-mode banner (CONTRACT §11/§15). The banner reads the
 * shared Inertia `demo` prop via usePage and posts to /logout to exit, so we
 * mock @inertiajs/react: `usePage` returns a controllable `demo` prop and
 * `router.post` is a spy. The component must render NOTHING for real visitors
 * (demo === null) and, when active, show the label, the remaining whole minutes
 * derived from `expires_at`, and an exit control that POSTs to /logout.
 */
type DemoState = { active: true; preset: string; expires_at: number } | null;

// Hoisted so the vi.mock factory (also hoisted) can safely reference them —
// otherwise the factory runs before these top-level consts initialise (TDZ).
const { pageState, routerPost } = vi.hoisted(() => ({
    pageState: { demo: null as DemoState },
    routerPost: vi.fn(),
}));

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: { demo: pageState.demo } }),
    router: { post: routerPost },
}));

// Imported after the mock so the component picks up the mocked Inertia module.
import DemoBanner from '@/Components/DemoBanner';

function renderBanner() {
    return render(
        <LocaleProvider>
            <DemoBanner />
        </LocaleProvider>,
    );
}

describe('DemoBanner', () => {
    afterEach(() => {
        cleanup();
        pageState.demo = null;
        routerPost.mockClear();
    });

    it('renders nothing for a real (non-demo) session', () => {
        pageState.demo = null;

        const { container } = renderBanner();

        expect(container).toBeEmptyDOMElement();
        expect(screen.queryByRole('status')).not.toBeInTheDocument();
    });

    it('renders the demo bar with an exit control when a demo session is active', () => {
        pageState.demo = { active: true, preset: 'admin', expires_at: Math.floor(Date.now() / 1000) + 1800 };

        renderBanner();

        expect(screen.getByRole('status')).toBeInTheDocument();
        // Exit control posts to /logout (state-changing, never a GET link).
        expect(screen.getByRole('button')).toBeInTheDocument();
    });

    it('reflects the remaining whole minutes derived from expires_at', () => {
        // ~10 minutes ahead → Math.ceil rounds up to 10 (or 11 at sub-minute drift).
        pageState.demo = { active: true, preset: 'sustentante_1', expires_at: Math.floor(Date.now() / 1000) + 600 };

        renderBanner();

        const status = screen.getByRole('status');
        expect(status.textContent).toMatch(/\b1[01]\b/);
    });

    it('shows 0 remaining minutes once the session has expired', () => {
        pageState.demo = { active: true, preset: 'admin', expires_at: Math.floor(Date.now() / 1000) - 60 };

        renderBanner();

        expect(screen.getByRole('status').textContent).toMatch(/\b0\b/);
    });

    it('POSTs to /logout when the exit control is activated', async () => {
        const user = userEvent.setup();
        pageState.demo = { active: true, preset: 'personal', expires_at: Math.floor(Date.now() / 1000) + 1800 };

        renderBanner();
        await user.click(screen.getByRole('button'));

        expect(routerPost).toHaveBeenCalledWith('/logout');
    });
});
