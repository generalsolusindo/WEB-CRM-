import { Head } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, DataTable, StatusBadge, EmptyState } from '../../../Components/ui';

function money(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(v || 0));
}

export default function Index({ payments }) {
    const columns = [
        {
            key: 'number',
            label: 'Deal',
            render: (p) => (
                <div>
                    <div className="font-semibold text-text">{p.number}</div>
                    <div className="text-xs text-text-muted">{p.project_number}</div>
                </div>
            ),
        },
        { key: 'customer', label: 'Customer', render: (p) => p.customer },
        { key: 'vendor', label: 'Vendor', render: (p) => p.vendor },
        { key: 'fee', label: 'Total Fee', align: 'right', render: (p) => <span className="tabular-nums">{money(p.total_fee)}</span> },
        { key: 'status', label: 'Status', render: (p) => <StatusBadge status={p.status} label={p.status_label} /> },
        {
            key: 'next',
            label: 'Langkah Berikutnya',
            render: (p) => (p.next === 'Bayar DP' || p.next === 'Bayar pelunasan'
                ? <span className="badge badge-warning">{p.next}</span>
                : <span className="text-sm text-text-muted">{p.next}</span>),
        },
    ];

    return (
        <AppLayout>
            <Head title="Pembayaran Vendor Jasa" />
            <div className="mx-auto max-w-6xl space-y-5">
                <PageHeader
                    title="Pembayaran Vendor Jasa"
                    subtitle="DP dan pelunasan vendor luar untuk pekerjaan di luar jangkauan. Pelunasan baru bisa dibayar setelah BAST diverifikasi Operasional."
                />
                <DataTable
                    columns={columns}
                    rows={payments}
                    rowKey="id"
                    rowHref={(p) => `/finance/vendor-service-payments/${p.id}`}
                    empty={<EmptyState title="Belum ada deal vendor jasa dari Procurement." />}
                />
            </div>
        </AppLayout>
    );
}
