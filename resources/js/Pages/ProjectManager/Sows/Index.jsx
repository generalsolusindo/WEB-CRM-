import { Head } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, DataTable, EmptyState, Pagination } from '../../../Components/ui';

export default function Index({ sows }) {
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
                    subtitle="SOW project yang didelegasikan ke Anda, sudah ditanda tangani Teknisi, PIC Vendor, dan Operasional — menunggu tanda tangan akhir Anda."
                />
                <DataTable
                    columns={columns}
                    rows={sows.data}
                    rowKey="id"
                    rowHref={(s) => `/project-manager/sows/${s.id}`}
                    empty={<EmptyState title="Tidak ada SOW yang menunggu tanda tangan Anda." />}
                    footer={<Pagination links={sows.links} />}
                />
            </div>
        </AppLayout>
    );
}
