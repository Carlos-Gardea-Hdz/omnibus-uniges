import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { PageProps } from '@/types';
import { useLocale } from '@/Contexts/LocaleContext';
import ReportShell from '@/Components/report/ReportShell';
import ReportTable, { type ReportColumn } from '@/Components/report/ReportTable';
import ConfirmDialog from '@/Components/catalog/ConfirmDialog';

/**
 * Shared page wrapper for the six catalog CRUD index screens (SLICE 009,
 * CONTRACT §8). Owns the cross-cutting chrome so each catalog page only declares
 * its columns + its create/edit form:
 *
 *  - the Reporting chrome (header, DemoBanner, #main, one <h1>, back-to-hub link);
 *  - a "New" button toolbar;
 *  - the data table (ReportTable) with a trailing edit/delete action column;
 *  - an accessible delete-confirm dialog that fires router.delete to `baseRoute`;
 *  - surfacing the graceful in-use-delete error — the server flashes it as a
 *    `catalog` field error (bootstrap/app.php renders CatalogInUseException to
 *    back()->withErrors(['catalog' => ...])) or as flash.error. It is shown in a
 *    role="alert" banner; the referenced row is never removed (delete failed).
 *
 * The table itself stays a real <table> with <caption>/<th scope="col"> (WCAG
 * 2.2 AA). All copy flows through useLocale().t. Generic over the row type.
 */
export interface CatalogRow {
    id: number;
}

interface CatalogShellProps<TRow extends CatalogRow> {
    /** Dotted locale key for the page <h1> / head title. */
    titleKey: string;
    /** Dotted locale key for the lead paragraph. */
    subtitleKey: string;
    /** Resolved CRUD base route, e.g. "/admin/catalogs/departments". */
    baseRoute: string;
    columns: ReportColumn<TRow>[];
    rows: TRow[];
    /** Human label for a row, shown in the delete-confirm body. */
    rowLabel: (row: TRow) => string;
    /** Open the create form. */
    onNew: () => void;
    /** Open the edit form for a row. */
    onEdit: (row: TRow) => void;
}

export default function CatalogShell<TRow extends CatalogRow>({
    titleKey,
    subtitleKey,
    baseRoute,
    columns,
    rows,
    rowLabel,
    onNew,
    onEdit,
}: CatalogShellProps<TRow>) {
    const { t } = useLocale();
    const { errors, flash } = usePage<PageProps>().props;
    const [deleting, setDeleting] = useState<TRow | null>(null);
    const [processing, setProcessing] = useState(false);

    // The in-use guard surfaces either as a validation error keyed `catalog`
    // (CatalogInUseException render) or as a flash.error. Show whichever exists.
    const blockMessage = errors?.catalog ?? flash?.error;

    const confirmDelete = () => {
        if (!deleting) {
            return;
        }
        setProcessing(true);
        router.delete(`${baseRoute}/${deleting.id}`, {
            preserveScroll: true,
            onFinish: () => {
                setProcessing(false);
                setDeleting(null);
            },
        });
    };

    // The action column is appended to the caller's data columns so every page
    // gets identical, accessible edit/delete controls.
    const actionColumn: ReportColumn<TRow> = {
        key: 'actions',
        header: t('catalogs.col.actions'),
        align: 'right',
        cell: (row) => (
            <div className="flex justify-end gap-2">
                <button
                    type="button"
                    onClick={() => onEdit(row)}
                    className="inline-flex h-9 items-center rounded-md border border-border px-3 text-sm font-medium text-fg transition-colors hover:bg-border focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                >
                    {t('catalogs.edit')}
                </button>
                <button
                    type="button"
                    onClick={() => setDeleting(row)}
                    className="inline-flex h-9 items-center rounded-md border border-danger px-3 text-sm font-medium text-danger transition-colors hover:bg-danger/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-danger"
                >
                    {t('catalogs.delete')}
                </button>
            </div>
        ),
    };

    return (
        <ReportShell
            headTitle={t(titleKey)}
            title={t(titleKey)}
            subtitle={t(subtitleKey)}
            backHref="/admin/catalogs"
        >
            {blockMessage ? (
                <p
                    role="alert"
                    className="mt-6 rounded-md border border-danger bg-danger/10 px-4 py-3 text-sm font-medium text-danger"
                >
                    {blockMessage}
                </p>
            ) : null}

            <div className="mt-6 flex justify-end">
                <button
                    type="button"
                    onClick={onNew}
                    className="inline-flex h-11 items-center rounded-md bg-accent px-5 font-medium text-accent-fg transition-colors hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                >
                    {t('catalogs.new')}
                </button>
            </div>

            {rows.length === 0 ? (
                <p className="mt-4 rounded-md border border-border bg-surface-raised px-4 py-8 text-center text-fg-muted">
                    {t('catalogs.empty')}
                </p>
            ) : (
                <ReportTable
                    caption={t(titleKey)}
                    columns={[...columns, actionColumn]}
                    rows={rows}
                    rowKey={(row) => row.id}
                />
            )}

            {deleting ? (
                <ConfirmDialog
                    title={t('catalogs.delete')}
                    message={t('catalogs.delete.confirm').replace('{name}', rowLabel(deleting))}
                    processing={processing}
                    onConfirm={confirmDelete}
                    onCancel={() => setDeleting(null)}
                />
            ) : null}

            {/* The form modal (create/edit) is rendered by the parent page so it
                can own its own field set + useForm state. */}
        </ReportShell>
    );
}
