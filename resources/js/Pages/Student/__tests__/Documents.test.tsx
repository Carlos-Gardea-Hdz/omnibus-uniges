import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { LocaleProvider } from '@/Contexts/LocaleContext';

/*
 * Unit test for the Annex III documents page (CONTRACT §12). The page is driven
 * by Inertia's useForm and a live Echo subscription; we mock both so it renders
 * in isolation (no Inertia runtime, no WebSocket in jsdom) and so we can inject a
 * server validation error to prove it surfaces inline. The Echo factory is mocked
 * to a chainable no-op channel so the mount effect neither opens a socket nor
 * throws. The form `data`/`errors` are controllable per test.
 *
 * The upload form is click-to-reveal: it only mounts for the row whose "subir /
 * replace" button was pressed, so the file-input and error assertions drive that
 * interaction first (mirroring the page's real UX).
 */

type FormState = {
    data: Record<string, unknown>;
    errors: Record<string, string>;
};

const formState: FormState = { data: {}, errors: {} };

vi.mock('@inertiajs/react', () => ({
    // Minimal Inertia surface used by the page.
    Head: ({ title }: { title?: string }) => <title>{title}</title>,
    Link: ({ children, ...props }: React.ComponentProps<'a'>) => (
        <a {...props}>{children}</a>
    ),
    router: { reload: vi.fn(), post: vi.fn() },
    useForm: () => ({
        data: formState.data,
        setData: (...args: unknown[]) => {
            // Support both setData(key, value) and setData(object) signatures.
            if (typeof args[0] === 'string') {
                formState.data[args[0]] = args[1];
            } else if (args[0] && typeof args[0] === 'object') {
                Object.assign(formState.data, args[0] as Record<string, unknown>);
            }
        },
        errors: formState.errors,
        processing: false,
        post: vi.fn(),
        put: vi.fn(),
        transform: vi.fn(),
        reset: vi.fn(),
        clearErrors: vi.fn(),
    }),
    usePage: () => ({ props: {} }),
}));

// A chainable no-op Echo channel: every listener returns the channel itself.
vi.mock('@/echo', () => {
    const channel = {
        subscribed: vi.fn(() => channel),
        listen: vi.fn(() => channel),
        stopListening: vi.fn(() => channel),
    };

    return {
        createEcho: () => ({
            private: () => channel,
            leave: vi.fn(),
            disconnect: vi.fn(),
        }),
    };
});

// Imported after the mocks so the page picks up the mocked modules.
import Documents from '@/Pages/Student/Documents';

/**
 * Props the controller shapes for the page (CONTRACT §12): top-level snake_case
 * `student_id`/`status` plus a per-row `documents[]` blob. One pending row (no
 * download) and one rejected row (with reason + a signed download link).
 */
const pendingRow = {
    id: 1,
    required_document_id: 10,
    name: 'Acta de nacimiento',
    description: 'Copia certificada.',
    status: 'pending',
    original_filename: null,
    rejection_reason: null,
    uploaded_at: null,
    download_url: null,
    allowed_mimes: 'application/pdf',
    max_size_kb: 10240,
};

const rejectedRow = {
    id: 2,
    required_document_id: 11,
    name: 'CURP',
    description: null,
    status: 'rejected',
    original_filename: 'curp.pdf',
    rejection_reason: 'El documento está ilegible.',
    uploaded_at: '2026-06-19T10:00:00Z',
    download_url: 'https://example.test/download/2?signature=abc',
    allowed_mimes: 'application/pdf,image/jpeg',
    max_size_kb: 10240,
};

const defaultProps = {
    student_id: 7,
    status: 'annex_iii_pending',
    documents: [pendingRow, rejectedRow],
};

function renderDocuments(props: Partial<typeof defaultProps> = {}) {
    return render(
        <LocaleProvider>
            <Documents {...defaultProps} {...props} />
        </LocaleProvider>,
    );
}

describe('Documents page', () => {
    afterEach(() => {
        cleanup();
        formState.data = {};
        formState.errors = {};
    });

    it('lists each required document by name', () => {
        renderDocuments();

        expect(screen.getByText('Acta de nacimiento')).toBeInTheDocument();
        expect(screen.getByText('CURP')).toBeInTheDocument();
    });

    it('shows the rejection reason for a rejected document', () => {
        renderDocuments({ documents: [rejectedRow] });

        const alert = screen.getByRole('alert');
        expect(alert).toHaveTextContent(/ilegible/i);
    });

    it('shows an inline upload form with a file input for an uploadable document', () => {
        renderDocuments({ documents: [pendingRow] });

        const form = document.querySelector('form');
        expect(form).toBeInTheDocument();
        expect(form?.querySelector('input[type="file"]')).toBeInTheDocument();
    });

    it('surfaces a server validation error inline on the open upload form', () => {
        formState.errors = { file: 'El archivo no es válido.' };

        renderDocuments({ documents: [pendingRow] });
        fireEvent.click(screen.getByRole('button', { name: /subir|upload/i }));

        const alerts = screen.getAllByRole('alert');
        expect(
            alerts.some((node) => /no es válido|invalid/i.test(node.textContent ?? '')),
        ).toBe(true);
    });
});
