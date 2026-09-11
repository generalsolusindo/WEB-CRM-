import { Head } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, DataTable, EmptyState, Pagination } from '../../../Components/ui';

export default function Index({ sows }) {
    const columns = [
        { key: 'number', label: 'Nomor', render: (s) => <span className="font-medium text-text">{s.number}</span> },
        { key: 'project_name', label: 'Nama Proyek' },
        { key: 'customer', label: 'Customer', render: (s) => s.customer || '—' },
        { key: 'submitted_at', label: 'Dikirim', render: (s) => (s.submitted_at ? new Date(s.submitted_at).toLocaleString('id-ID') : '—') },
    ];

    return (
        <AppLayout>
            <Head title="Review SOW" />
            <div className="mx-auto max-w-4xl space-y-5">
                <PageHeader title="Review SOW" subtitle="SOW yang dikirim Operasional, menunggu review Anda sebelum diteruskan ke Teknisi." />
                <DataTable
                    columns={columns}
                    rows={sows.data}
                    rowKey="id"
                    rowHref={(s) => `/hr/sows/${s.id}`}
                    empty={<EmptyState title="Tidak ada SOW yang menunggu review." />}
                    footer={<Pagination links={sows.links} />}
                />
            </div>
        </AppLayout>
    );
}
