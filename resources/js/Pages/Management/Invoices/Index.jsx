import { Head, router } from '@inertiajs/react';
import { FiFileText } from 'react-icons/fi';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, PillTabs, DataTable, StatusBadge, EmptyState, Pagination, Button, DateRangeFilter } from '../../../Components/ui';

function money(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(v || 0));
}

export default function Index({ invoices, filters, statusOptions }) {
    function query(overrides) {
        return {
            ...(filters.status ? { status: filters.status } : {}),
            ...(filters.from ? { from: filters.from } : {}),
            ...(filters.to ? { to: filters.to } : {}),
            ...overrides,
        };
    }

    function setStatus(status) {
        router.get('/management/invoices', query({ status: status || undefined }), { preserveState: true, replace: true });
    }

    function applyDates(from, to) {
        router.get('/management/invoices', query({ from: from || undefined, to: to || undefined }), { preserveState: true, replace: true });
    }

    function resetDates() {
        router.get('/management/invoices', query({ from: undefined, to: undefined }), { preserveState: true, replace: true });
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
        { key: 'phase', hideBelow: 'md', label: 'Fase', render: (inv) => <span className="text-xs uppercase text-text-muted">{inv.phase}</span> },
        { key: 'status', label: 'Status', render: (inv) => <StatusBadge status={inv.status} label={statusOptions.find((s) => s.value === inv.status)?.label} /> },
        { key: 'grand_total', label: 'Total Tagihan', align: 'right', render: (inv) => <span className="tabular-nums">{money(inv.grand_total)}</span> },
        { key: 'paid_total', hideBelow: 'md', label: 'Sudah Dibayar', align: 'right', render: (inv) => <span className="tabular-nums text-text-muted">{money(inv.paid_total)}</span> },
        { key: 'due_date', label: 'Jatuh Tempo', render: (inv) => inv.due_date || '—' },
        {
            key: 'pdf',
            label: '',
            align: 'right',
            render: (inv) => (
                <Button href={`/finance/invoices/${inv.id}/pdf`} external variant="outline" icon={FiFileText} className="text-xs">
                    Lihat PDF
                </Button>
            ),
        },
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
                <DateRangeFilter from={filters.from} to={filters.to} onApply={applyDates} onReset={resetDates} />
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
