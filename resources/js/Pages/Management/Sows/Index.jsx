import { Head, router } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, DataTable, EmptyState, Pagination, DateRangeFilter } from '../../../Components/ui';

export default function Index({ sows, filters = {} }) {
    function applyDates(from, to) {
        router.get('/management/sows', { ...(from ? { from } : {}), ...(to ? { to } : {}) }, { preserveState: true, replace: true });
    }

    function resetDates() {
        router.get('/management/sows', {}, { preserveState: true, replace: true });
    }

    const columns = [
        { key: 'number', label: 'Nomor', render: (s) => <span className="font-medium text-text">{s.number}</span> },
        { key: 'project_name', label: 'Nama Proyek' },
        { key: 'customer', label: 'Customer', render: (s) => s.customer || '—' },
    ];

    return (
        <AppLayout>
            <Head title="SOW Menunggu TTD" />
            <div className="mx-auto max-w-4xl space-y-5">
                <PageHeader
                    title="SOW Menunggu Tanda Tangan"
                    subtitle="SOW yang sudah ditanda tangani Teknisi, PIC Vendor, dan Admin Project — menunggu tanda tangan akhir Anda."
                />
                <DateRangeFilter from={filters.from} to={filters.to} onApply={applyDates} onReset={resetDates} />
                <DataTable
                    columns={columns}
                    rows={sows.data}
                    rowKey="id"
                    rowHref={(s) => `/management/sows/${s.id}`}
                    empty={<EmptyState title="Tidak ada SOW yang menunggu tanda tangan Anda." />}
                    footer={<Pagination links={sows.links} />}
                />
            </div>
        </AppLayout>
    );
}
