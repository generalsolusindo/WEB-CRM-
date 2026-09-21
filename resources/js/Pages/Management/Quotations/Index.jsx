import { Head, router } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, PillTabs, DataTable, StatusBadge, EmptyState, Pagination, DateRangeFilter } from '../../../Components/ui';

export default function Index({ quotations, filters, statusOptions }) {
    function query(overrides) {
        return {
            ...(filters.status ? { status: filters.status } : {}),
            ...(filters.from ? { from: filters.from } : {}),
            ...(filters.to ? { to: filters.to } : {}),
            ...overrides,
        };
    }

    function setStatus(status) {
        router.get('/management/quotations-overview', query({ status: status || undefined }), { preserveState: true, replace: true });
    }

    function applyDates(from, to) {
        router.get('/management/quotations-overview', query({ from: from || undefined, to: to || undefined }), { preserveState: true, replace: true });
    }

    function resetDates() {
        router.get('/management/quotations-overview', query({ from: undefined, to: undefined }), { preserveState: true, replace: true });
    }

    const tabs = [{ value: '', label: 'Semua' }, ...statusOptions.map((o) => ({ value: o.value, label: o.label }))];

    const columns = [
        {
            key: 'number',
            label: 'Nomor',
            render: (q) => (
                <span className="flex items-center gap-2">
                    <span className="font-medium text-text">{q.number}</span>
                    {q.is_addendum && <span className="rounded-full bg-info-soft px-2 py-0.5 text-[10px] font-semibold text-info">Addendum</span>}
                </span>
            ),
        },
        {
            key: 'customer',
            label: 'Customer',
            render: (q) => (
                <div>
                    <div className="font-medium text-text">{q.customer || '—'}</div>
                    <div className="text-xs text-text-muted">{q.company || '—'}</div>
                </div>
            ),
        },
        { key: 'sales', hideBelow: 'md', label: 'Sales', render: (q) => q.sales || '—' },
        { key: 'status', label: 'Status', render: (q) => <StatusBadge status={q.status} label={statusOptions.find((s) => s.value === q.status)?.label} /> },
        { key: 'created_at', hideBelow: 'md', label: 'Tanggal', render: (q) => q.created_at?.slice(0, 10) },
    ];

    return (
        <AppLayout>
            <Head title="Semua Quotation" />
            <div className="mx-auto max-w-6xl space-y-5">
                <PageHeader
                    title="Semua Quotation"
                    subtitle="Tracking seluruh quotation — read-only, tanpa aksi. Untuk approve/reject, pakai menu Verifikasi Quotation."
                    back={{ href: '/dashboard', label: 'Kembali ke Dashboard' }}
                />
                <PillTabs tabs={tabs} value={filters.status || ''} onChange={setStatus} />
                <DateRangeFilter from={filters.from} to={filters.to} onApply={applyDates} onReset={resetDates} />
                <DataTable
                    columns={columns}
                    rows={quotations.data}
                    rowKey="id"
                    rowHref={(q) => `/management/quotations-overview/${q.id}`}
                    empty={<EmptyState title="Belum ada quotation." />}
                    footer={<Pagination links={quotations.links} />}
                />
            </div>
        </AppLayout>
    );
}
