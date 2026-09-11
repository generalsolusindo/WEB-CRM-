import { router } from '@inertiajs/react';
import { FiChevronRight } from 'react-icons/fi';
import EmptyState from './EmptyState';

/**
 * columns: [{ key, label, align?: 'right', width?, render?: (row) => node, className? }]
 * rows: array
 * rowKey: string | (row) => key
 * rowHref: (row) => url  → seluruh baris klik-able + kolom chevron otomatis
 * empty: node (default EmptyState)
 * footer: node (mis. <Pagination />)
 */
export default function DataTable({
    columns, rows = [], rowKey = 'id', rowHref, empty, footer, dense = false, className = '',
    title, titleAction,
}) {
    const padY = dense ? 'py-2.5' : 'py-3.5';
    const clickable = typeof rowHref === 'function';
    const cols = clickable ? [...columns, { key: '__chevron', align: 'right', width: 40, render: () => <FiChevronRight className="ml-auto h-4 w-4 text-text-faint" /> }] : columns;

    return (
        <div className={`card overflow-hidden p-0 ${className}`.trim()}>
            {title && (
                <div className="flex items-center justify-between gap-3 border-b border-border px-5 py-3.5">
                    <span className="text-sm font-bold tracking-tight text-text">{title}</span>
                    {titleAction}
                </div>
            )}
            <div className="overflow-x-auto">
                <table className="w-full text-left text-sm">
                    <thead>
                        <tr className="border-b border-border bg-surface-2">
                            {cols.map((c) => (
                                <th
                                    key={c.key}
                                    style={c.width ? { width: c.width } : undefined}
                                    className={`px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-text-faint ${c.align === 'right' ? 'text-right' : ''}`}
                                >
                                    {c.label ?? ''}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 ? (
                            <tr>
                                <td colSpan={cols.length}>
                                    {empty ?? <EmptyState title="Belum ada data" />}
                                </td>
                            </tr>
                        ) : (
                            rows.map((row) => {
                                const key = typeof rowKey === 'function' ? rowKey(row) : row[rowKey];
                                return (
                                    <tr
                                        key={key}
                                        onClick={clickable ? () => router.visit(rowHref(row)) : undefined}
                                        className={`border-b border-border transition last:border-0 ${clickable ? 'cursor-pointer hover:bg-bg' : 'hover:bg-surface-2'}`}
                                    >
                                        {cols.map((c) => (
                                            <td key={c.key} className={`px-4 ${padY} align-middle ${c.align === 'right' ? 'text-right' : ''} ${c.className ?? ''}`}>
                                                {c.render ? c.render(row) : row[c.key]}
                                            </td>
                                        ))}
                                    </tr>
                                );
                            })
                        )}
                    </tbody>
                </table>
            </div>
            {footer && <div className="border-t border-border px-4 py-3">{footer}</div>}
        </div>
    );
}
