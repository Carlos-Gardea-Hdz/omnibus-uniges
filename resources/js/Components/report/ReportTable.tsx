import type { ReactNode } from 'react';

/**
 * A semantic, accessible report data table (SLICE 008). Reuses the table chrome
 * established by Graduation/Review.tsx — an `overflow-x-auto` rounded border
 * wrapper, a `<caption className="sr-only">`, a `<thead>` of `<th scope="col">`,
 * and zebra-free bordered rows.
 *
 * WCAG 2.2 AA: every table carries a `<caption>` (screen-reader-only by default,
 * since a visible <h2> usually precedes it) and every header cell is a
 * `<th scope="col">`. Generic over the row type so each report supplies its own
 * column definitions and cell renderers — no `any`.
 */
export interface ReportColumn<TRow> {
    /** Stable key for React + the column identity. */
    key: string;
    /** Already-translated header label. */
    header: string;
    /** Cell renderer for a row. */
    cell: (row: TRow) => ReactNode;
    /** Optional alignment; defaults to left. Numeric columns use 'right'. */
    align?: 'left' | 'right';
    /** Optional extra classes for the cell (e.g. font-mono, tabular-nums). */
    cellClassName?: string;
}

interface ReportTableProps<TRow> {
    /** Accessible table caption (already translated). */
    caption: string;
    columns: ReportColumn<TRow>[];
    rows: TRow[];
    /** Stable React key for each row. */
    rowKey: (row: TRow) => string | number;
}

export default function ReportTable<TRow>({
    caption,
    columns,
    rows,
    rowKey,
}: ReportTableProps<TRow>) {
    return (
        <div className="mt-6 overflow-x-auto rounded-lg border border-border">
            <table className="w-full border-collapse text-left text-sm">
                <caption className="sr-only">{caption}</caption>
                <thead className="bg-surface-raised text-fg-muted">
                    <tr>
                        {columns.map((column) => (
                            <th
                                key={column.key}
                                scope="col"
                                className={`px-4 py-3 font-medium ${
                                    column.align === 'right' ? 'text-right' : ''
                                }`}
                            >
                                {column.header}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr key={rowKey(row)} className="border-t border-border">
                            {columns.map((column) => (
                                <td
                                    key={column.key}
                                    className={`px-4 py-3 ${
                                        column.align === 'right' ? 'text-right' : ''
                                    } ${column.cellClassName ?? ''}`}
                                >
                                    {column.cell(row)}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
