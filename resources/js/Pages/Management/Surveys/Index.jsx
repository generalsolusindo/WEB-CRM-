import { Head, router } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, PillTabs, DataTable, StatusBadge, EmptyState, Pagination, DateRangeFilter } from '../../../Components/ui';

export default function Index({ surveys, filters, statusOptions }) {
    function query(overrides) {
        return {
            ...(filters.status ? { status: filters.status } : {}),
            ...(filters.from ? { from: filters.from } : {}),
            ...(filters.to ? { to: filters.to } : {}),
            ...overrides,
        };
    }

    function setStatus(status) {
        router.get('/management/surveys', query({ status: status || undefined }), { preserveState: true, replace: true });
    }

    function applyDates(from, to) {
        router.get('/management/surveys', query({ from: from || undefined, to: to || undefined }), { preserveState: true, replace: true });
    }

    function resetDates() {
        router.get('/management/surveys', query({ from: undefined, to: undefined }), { preserveState: true, replace: true });
    }

    const tabs = [{ value: '', label: 'Semua' }, ...statusOptions.map((o) => ({ value: o.value, label: o.label }))];

    const columns = [
        { key: 'code', label: 'Kode Survey', render: (s) => <span className="font-semibold text-text">{s.code}</span> },
        {
            key: 'customer',
            label: 'Customer',
            render: (s) => (
                <div>
                    <div className="font-medium text-text">{s.customer || '—'}</div>
                    <div className="text-xs text-text-muted">{s.company || '—'}</div>
                </div>
            ),
        },
        { key: 'site_region', label: 'Lokasi', render: (s) => s.site_region || '—' },
        { key: 'status', label: 'Status', render: (s) => <StatusBadge status={s.status} label={statusOptions.find((o) => o.value === s.status)?.label} /> },
        { key: 'created_at', label: 'Tanggal', render: (s) => s.created_at },
    ];

    return (
        <AppLayout>
            <Head title="Survey" />
            <div className="mx-auto max-w-6xl space-y-5">
                <PageHeader
                    title="Survey"
                    subtitle="Monitoring lintas-departemen — read-only, tanpa aksi."
                    back={{ href: '/dashboard', label: 'Kembali ke Dashboard' }}
                />
                <PillTabs tabs={tabs} value={filters.status || ''} onChange={setStatus} />
                <DateRangeFilter from={filters.from} to={filters.to} onApply={applyDates} onReset={resetDates} />
                <DataTable
                    columns={columns}
                    rows={surveys.data}
                    rowKey="id"
                    empty={<EmptyState title="Tidak ada survey." />}
                    footer={<Pagination links={surveys.links} />}
                />
            </div>
        </AppLayout>
    );
}
