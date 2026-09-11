import { Head } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, DataTable, StatusBadge, EmptyState } from '../../../Components/ui';

export default function Index({ projects }) {
    const columns = [
        {
            key: 'number',
            label: 'Project',
            render: (p) => (
                <div>
                    <div className="font-semibold text-text">{p.number}</div>
                    <div className="text-xs text-text-muted">{p.sales_order}</div>
                </div>
            ),
        },
        { key: 'customer', label: 'Customer', render: (p) => p.customer },
        {
            key: 'items',
            label: 'Barang',
            align: 'right',
            render: (p) => <span className="tabular-nums">{p.received_count}/{p.items_count} diterima</span>,
        },
        {
            key: 'payment',
            label: 'Status Pengajuan',
            render: (p) => (p.payment_status
                ? <StatusBadge status={p.payment_status} label={p.payment_status_label} />
                : <span className="badge badge-neutral">Belum diajukan</span>),
        },
    ];

    return (
        <AppLayout>
            <Head title="Pengadaan Project" />
            <div className="mx-auto max-w-5xl space-y-5">
                <PageHeader
                    title="Pengadaan Project"
                    subtitle="Sourcing barang material per project, lalu ajukan pembayaran ke Project Manager & Finance."
                />
                <DataTable
                    columns={columns}
                    rows={projects}
                    rowKey="id"
                    rowHref={(p) => `/procurement/project-procurements/${p.id}`}
                    empty={<EmptyState title="Belum ada project yang butuh pengadaan barang." />}
                />
            </div>
        </AppLayout>
    );
}
