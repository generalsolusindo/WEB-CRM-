import { Head } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, DataTable, StatusBadge, EmptyState } from '../../../Components/ui';

export default function Index({ payments }) {
    const columns = [
        { key: 'number', label: 'Nomor', render: (p) => <span className="font-medium text-text">{p.number}</span> },
        {
            key: 'project',
            label: 'Project',
            render: (p) => (
                <div>
                    <div className="font-medium text-text">{p.project_number}</div>
                    <div className="text-xs text-text-muted">{p.customer} · {p.sales_order}</div>
                </div>
            ),
        },
        { key: 'status', label: 'Status', render: (p) => <StatusBadge status={p.status} label={p.status_label} /> },
    ];

    return (
        <AppLayout>
            <Head title="Pembayaran Vendor" />
            <div className="mx-auto max-w-4xl space-y-5">
                <PageHeader
                    title="Pembayaran Vendor"
                    subtitle="Pengajuan pembelian barang yang sudah disetujui Project Manager — catat pembayaran ke vendor di sini."
                />
                <DataTable
                    columns={columns}
                    rows={payments}
                    rowKey="id"
                    rowHref={(p) => `/finance/procurement-payments/${p.id}`}
                    empty={<EmptyState title="Belum ada pengajuan yang siap dibayar." />}
                />
            </div>
        </AppLayout>
    );
}
