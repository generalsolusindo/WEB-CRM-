import { Head, router } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, DataTable, EmptyState, Pagination, DateRangeFilter } from '../../../Components/ui';

function ReviewBadge({ status }) {
    if (status === 'approved') return <span className="badge badge-success">Disetujui</span>;
    if (status === 'rejected') return <span className="badge badge-danger">Ditolak</span>;
    return <span className="badge badge-warning">Menunggu verifikasi</span>;
}

export default function Index({ quotations, role, filters = {} }) {
    const base = role === 'management' ? '/management/quotations' : '/project-manager/quotations';
    const isMgmt = role === 'management';

    function applyDates(from, to) {
        router.get(base, { ...(from ? { from } : {}), ...(to ? { to } : {}) }, { preserveState: true, replace: true });
    }

    function resetDates() {
        router.get(base, {}, { preserveState: true, replace: true });
    }

    const columns = [
        {
            key: 'number',
            label: 'Nomor',
            render: (q) => (
                <span className="flex items-center gap-2">
                    <span className="font-medium text-text">{q.number}</span>
                    {q.is_addendum && <span className="rounded-full bg-info-soft px-2 py-0.5 text-[10px] font-semibold text-info">Tambahan</span>}
                </span>
            ),
        },
        { key: 'customer', label: 'Customer', render: (q) => q.company || q.customer || '—' },
        { key: 'sales', hideBelow: 'md', label: 'Sales', render: (q) => q.sales },
        { key: 'status', label: 'Status', render: (q) => <ReviewBadge status={isMgmt ? q.manager_review_status : q.pm_review_status} /> },
    ];

    return (
        <AppLayout>
            <Head title="Verifikasi Quotation" />
            <div className="mx-auto max-w-5xl space-y-5">
                <PageHeader
                    title="Verifikasi Quotation"
                    subtitle={isMgmt
                        ? 'Quotation yang sudah disetujui Project Manager, menunggu verifikasi akhir Anda.'
                        : 'Quotation dari opportunity yang didelegasikan kepada Anda, menunggu verifikasi Anda sebelum lanjut ke Manager.'}
                />
                {isMgmt && <DateRangeFilter from={filters.from} to={filters.to} onApply={applyDates} onReset={resetDates} />}
                <DataTable
                    columns={columns}
                    rows={quotations.data}
                    rowKey="id"
                    rowHref={(q) => `${base}/${q.id}`}
                    empty={<EmptyState title="Tidak ada quotation yang menunggu verifikasi Anda." />}
                    footer={<Pagination links={quotations.links} />}
                />
            </div>
        </AppLayout>
    );
}
