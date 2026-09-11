import { Head, router } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, PillTabs, DataTable, StatusBadge, EmptyState, Pagination } from '../../../Components/ui';

export default function Index({ surveys, filters, statusOptions }) {
    function setStatus(status) {
        router.get('/procurement/surveys', status ? { status } : {}, { preserveState: true, replace: true });
    }

    const tabs = [{ value: '', label: 'Semua' }, ...statusOptions.map((o) => ({ value: o.value, label: o.label }))];

    const columns = [
        { key: 'code', label: 'Kode', render: (s) => <span className="font-medium text-text">{s.code}</span> },
        { key: 'customer', label: 'Customer', render: (s) => s.customer || '—' },
        { key: 'site_region', label: 'Lokasi' },
        { key: 'delivery', label: 'Pelaksana', render: (s) => `${s.delivery_mode}${s.billable ? ' · ditagih' : ''}` },
        { key: 'team', label: 'Vendor / Tim', render: (s) => s.vendor || (s.team_count ? `${s.team_count} surveyor` : '—') },
        { key: 'status', label: 'Status', render: (s) => <StatusBadge status={s.status} label={s.status_label} /> },
    ];

    return (
        <AppLayout>
            <Head title="Survey" />
            <div className="mx-auto max-w-5xl space-y-5">
                <PageHeader title="Survey" subtitle="Permintaan survey dari Sales. Amankan vendor (bila di luar jangkauan) dan biayanya." />

                <PillTabs tabs={tabs} value={filters.status || ''} onChange={setStatus} />

                <DataTable
                    columns={columns}
                    rows={surveys.data}
                    rowKey="id"
                    rowHref={(s) => `/procurement/surveys/${s.id}`}
                    empty={<EmptyState title="Belum ada permintaan survey." />}
                    footer={<Pagination links={surveys.links} />}
                />
            </div>
        </AppLayout>
    );
}
