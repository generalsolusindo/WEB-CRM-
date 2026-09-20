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
        {
            key: 'customer',
            label: 'Customer',
            render: (p) => (
                <div>
                    <div>{p.customer}</div>
                    {p.needs_outside_vendor && !p.vendor_service_status && <span className="badge badge-warning">Butuh vendor luar</span>}
                </div>
            ),
        },
        {
            key: 'items',
            label: 'Barang',
            align: 'right',
            render: (p) => (p.items_count > 0
                ? <span className="tabular-nums">{p.received_count}/{p.items_count} diterima</span>
                : <span className="text-text-muted">—</span>),
        },
        {
            key: 'vendor_service',
            label: 'Vendor Jasa',
            render: (p) => (p.vendor_service_status
                ? (
                    <div>
                        <div className="text-sm text-text">{p.vendor_name}</div>
                        <StatusBadge status={p.vendor_service_status} label={p.vendor_service_status_label} />
                    </div>
                )
                : <span className="text-text-muted">—</span>),
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
                    subtitle="Sourcing barang material per project lalu ajukan pembayaran, serta deal vendor jasa untuk pekerjaan di luar jangkauan."
                />
                <DataTable
                    columns={columns}
                    rows={projects}
                    rowKey="id"
                    rowHref={(p) => `/procurement/project-procurements/${p.id}`}
                    empty={<EmptyState title="Belum ada project yang butuh pengadaan barang atau vendor." />}
                />
            </div>
        </AppLayout>
    );
}
