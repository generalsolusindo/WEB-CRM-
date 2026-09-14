import { Head, router } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, PillTabs, DataTable, StatusBadge, EmptyState, Pagination } from '../../../Components/ui';

function money(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(v || 0));
}

export default function Index({ invoices, filters, statusOptions }) {
    function setStatus(status) {
        router.get('/management/invoices', status ? { status } : {}, { preserveState: true, replace: true });
    }

    const tabs = [{ value: '', label: 'Semua' }, ...statusOptions.map((o) => ({ value: o.value, label: o.label }))];

    const columns = [
        { key: 'number', label: 'No. Invoice', render: (inv) => <span className="font-semibold text-text">{inv.number}</span> },
        {
            key: 'customer',
            label: 'Customer',
            render: (inv) => (
                <div>
                    <div className="font-medium text-text">{inv.customer || '—'}</div>
                    <div className="text-xs text-text-muted">{inv.company || '—'}</div>
                </div>
            ),
        },
        { key: 'phase', label: 'Fase', render: (inv) => <span className="text-xs uppercase text-text-muted">{inv.phase}</span> },
        { key: 'status', label: 'Status', render: (inv) => <StatusBadge status={inv.status} label={statusOptions.find((s) => s.value === inv.status)?.label} /> },
        { key: 'grand_total', label: 'Total Tagihan', align: 'right', render: (inv) => <span className="tabular-nums">{money(inv.grand_total)}</span> },
        { key: 'paid_total', label: 'Sudah Dibayar', align: 'right', render: (inv) => <span className="tabular-nums text-text-muted">{money(inv.paid_total)}</span> },
        { key: 'due_date', label: 'Jatuh Tempo', render: (inv) => inv.due_date || '—' },
    ];

    return (
        <AppLayout>
            <Head title="Invoice" />
            <div className="mx-auto max-w-6xl space-y-5">
                <PageHeader
                    title="Invoice"
                    subtitle="Monitoring lintas-departemen — read-only, tanpa aksi."
                    back={{ href: '/dashboard', label: 'Kembali ke Dashboard' }}
                />
                <PillTabs tabs={tabs} value={filters.status || ''} onChange={setStatus} />
                <DataTable
                    columns={columns}
                    rows={invoices.data}
                    rowKey="id"
                    empty={<EmptyState title="Tidak ada invoice." />}
                    footer={<Pagination links={invoices.links} />}
                />
            </div>
        </AppLayout>
    );
}
