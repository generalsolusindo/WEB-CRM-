import { Head, router } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, PillTabs, DataTable, StatusBadge, EmptyState, Pagination, DateRangeFilter } from '../../../Components/ui';

export default function Index({ requests, filters, statusOptions }) {
    function query(overrides) {
        return {
            ...(filters.status ? { status: filters.status } : {}),
            ...(filters.from ? { from: filters.from } : {}),
            ...(filters.to ? { to: filters.to } : {}),
            ...overrides,
        };
    }

    function setStatus(status) {
        router.get('/management/procurement-requests', query({ status: status || undefined }), { preserveState: true, replace: true });
    }

    function applyDates(from, to) {
        router.get('/management/procurement-requests', query({ from: from || undefined, to: to || undefined }), { preserveState: true, replace: true });
    }

    function resetDates() {
        router.get('/management/procurement-requests', query({ from: undefined, to: undefined }), { preserveState: true, replace: true });
    }

    const tabs = [{ value: '', label: 'Semua' }, ...statusOptions.map((o) => ({ value: o.value, label: o.label }))];

    const columns = [
        { key: 'number', label: 'Nomor PR', render: (pr) => <span className="font-semibold text-text">{pr.number}</span> },
        {
            key: 'customer',
            label: 'Customer',
            render: (pr) => (
                <div>
                    <div className="font-medium text-text">{pr.customer || '—'}</div>
                    <div className="text-xs text-text-muted">{pr.company || '—'}</div>
                </div>
            ),
        },
        { key: 'lines_count', hideBelow: 'md', label: 'Line', align: 'right', render: (pr) => <span className="tabular-nums">{pr.lines_count}</span> },
        { key: 'status', label: 'Status', render: (pr) => <StatusBadge status={pr.status} label={statusOptions.find((s) => s.value === pr.status)?.label} /> },
        { key: 'created_at', hideBelow: 'md', label: 'Tanggal', render: (pr) => pr.created_at },
    ];

    return (
        <AppLayout>
            <Head title="Procurement Request" />
            <div className="mx-auto max-w-6xl space-y-5">
                <PageHeader
                    title="Procurement Request"
                    subtitle="Monitoring lintas-departemen — read-only, tanpa aksi."
                    back={{ href: '/dashboard', label: 'Kembali ke Dashboard' }}
                />
                <PillTabs tabs={tabs} value={filters.status || ''} onChange={setStatus} />
                <DateRangeFilter from={filters.from} to={filters.to} onApply={applyDates} onReset={resetDates} />
                <DataTable
                    columns={columns}
                    rows={requests.data}
                    rowKey="id"
                    empty={<EmptyState title="Tidak ada Procurement Request." />}
                    footer={<Pagination links={requests.links} />}
                />
            </div>
        </AppLayout>
    );
}
