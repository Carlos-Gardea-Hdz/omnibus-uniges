import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, render, screen } from '@testing-library/react';
import { LocaleProvider } from '@/Contexts/LocaleContext';
import { ThemeProvider } from '@/Contexts/ThemeContext';

/*
 * Unit test for the catalog hub (SLICE 009). A pure render of snake_case props
 * from Admin\Catalog\CatalogHubController. `Link` becomes a plain anchor (so we
 * can assert each card href is a real link — no dead-end); `usePage` returns a
 * null `demo` prop so the shared DemoBanner stays inert.
 */
vi.mock('@inertiajs/react', () => ({
    Head: ({ title }: { title?: string }) => <title>{title}</title>,
    Link: ({ children, ...props }: React.ComponentProps<'a'>) => <a {...props}>{children}</a>,
    usePage: () => ({ props: { demo: null } }),
}));

import CatalogIndex from '@/Pages/Admin/Catalogs/Index';

type IndexProps = React.ComponentProps<typeof CatalogIndex>;

const CATALOGS: IndexProps['catalogs'] = [
    {
        key: 'departments',
        title_key: 'catalogs.departments.title',
        description_key: 'catalogs.departments.description',
        count: 3,
        route: '/admin/catalogs/departments',
    },
    {
        key: 'programs',
        title_key: 'catalogs.programs.title',
        description_key: 'catalogs.programs.description',
        count: 5,
        route: '/admin/catalogs/programs',
    },
    {
        key: 'professors',
        title_key: 'catalogs.professors.title',
        description_key: 'catalogs.professors.description',
        count: 12,
        route: '/admin/catalogs/professors',
    },
    {
        key: 'graduation_types',
        title_key: 'catalogs.graduation_types.title',
        description_key: 'catalogs.graduation_types.description',
        count: 4,
        route: '/admin/catalogs/graduation-types',
    },
    {
        key: 'study_plans',
        title_key: 'catalogs.study_plans.title',
        description_key: 'catalogs.study_plans.description',
        count: 2,
        route: '/admin/catalogs/study-plans',
    },
    {
        key: 'required_documents',
        title_key: 'catalogs.required_documents.title',
        description_key: 'catalogs.required_documents.description',
        count: 6,
        route: '/admin/catalogs/required-documents',
    },
];

function renderHub(overrides: Partial<IndexProps> = {}) {
    return render(
        <ThemeProvider>
            <LocaleProvider>
                <CatalogIndex catalogs={CATALOGS} {...overrides} />
            </LocaleProvider>
        </ThemeProvider>,
    );
}

describe('Catalog hub page', () => {
    afterEach(cleanup);

    it('renders exactly six catalog cards, each a real link to its route', () => {
        renderHub();

        const links = screen.getAllByRole('link');
        expect(links).toHaveLength(6);

        expect(screen.getByRole('link', { name: /Departamentos/ })).toHaveAttribute(
            'href',
            '/admin/catalogs/departments',
        );
        expect(screen.getByRole('link', { name: /Documentos requeridos/ })).toHaveAttribute(
            'href',
            '/admin/catalogs/required-documents',
        );
    });

    it('renders each card count badge', () => {
        renderHub();
        // The "professors" card carries a count of 12.
        const professorsLink = screen.getByRole('link', { name: /Profesores/ });
        expect(professorsLink).toHaveTextContent('12');
    });

    it('renders one h1 for the hub', () => {
        renderHub();
        expect(
            screen.getByRole('heading', { level: 1, name: 'Catálogos académicos' }),
        ).toBeInTheDocument();
    });
});
