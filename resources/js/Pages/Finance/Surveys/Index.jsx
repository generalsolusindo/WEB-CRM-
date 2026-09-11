import { Head } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, DataTable, StatusBadge, EmptyState, Pagination } from '../../../Components/ui';

function money(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(v || 0));
}

export default function Index({ surveys }) {
    const columns = [
        { key: 'code', label: 'Kode', render: (s) => <span className="font-semibold text-text">{s.code}</span> },
        { key: 'customer', label: 'Customer', render: (s) => s.customer || '—' },
        { key: 'site_region', label: 'Lokasi' },
        { key: 'billing', label: 'Penagihan', render: (s) => (s.billable ? 'Ditagih ke customer' : `Biaya internal (${s.delivery_mode})`) },
        { key: 'cost', label: 'Biaya', align: 'right', render: (s) => <span className="tabular-nums">{money(s.cost)}</span> },
        { key: 'status', label: 'Status', render: (s) => <StatusBadge status={s.status} label={s.status_label} tone="warning" /> },
    ];

    return (
        <AppLayout>
            <Head title="Survey — Finance" />
            <div className="mx-auto max-w-5xl space-y-5">
                <PageHeader
                    title="Survey Menunggu Finance"
                    subtitle="Terbitkan invoice bila survey ditagihkan ke customer, atau catat biaya vendor tanpa tagihan."
                />

                <DataTable
                    columns={columns}
                    rows={surveys.data}
                    rowKey="id"
                    rowHref={(s) => `/finance/surveys/${s.id}`}
                    empty={<EmptyState title="Tidak ada survey yang menunggu Finance." />}
                    footer={<Pagination links={surveys.links} />}
                />
            </div>
        </AppLayout>
    );
}
