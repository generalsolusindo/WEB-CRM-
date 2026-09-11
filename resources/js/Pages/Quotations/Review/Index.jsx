import { Head } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, DataTable, EmptyState, Pagination } from '../../../Components/ui';

function ReviewBadge({ status }) {
    if (status === 'approved') return <span className="badge badge-success">Disetujui</span>;
    if (status === 'rejected') return <span className="badge badge-danger">Ditolak</span>;
    return <span className="badge badge-warning">Menunggu verifikasi</span>;
}

export default function Index({ quotations, role }) {
    const base = role === 'management' ? '/management/quotations' : '/project-manager/quotations';
    const isMgmt = role === 'management';

    const columns = [
        { key: 'number', label: 'Nomor', render: (q) => <span className="font-medium text-text">{q.number}</span> },
        { key: 'customer', label: 'Customer', render: (q) => q.company || q.customer || '—' },
        { key: 'sales', label: 'Sales', render: (q) => q.sales },
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
