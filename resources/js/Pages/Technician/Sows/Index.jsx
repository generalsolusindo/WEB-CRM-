import { Head } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, DataTable, StatusBadge, EmptyState, Pagination } from '../../../Components/ui';

export default function Index({ sows }) {
    const columns = [
        { key: 'number', label: 'Nomor', render: (s) => <span className="font-medium text-text">{s.number}</span> },
        { key: 'project_name', label: 'Nama Proyek' },
        { key: 'status', label: 'Status', render: (s) => <StatusBadge status={s.status} label={s.status_label} /> },
    ];

    return (
        <AppLayout>
            <Head title="SOW Saya" />
            <div className="mx-auto min-w-0 break-words max-w-4xl space-y-5">
                <PageHeader title="SOW Saya" subtitle="Scope of Work yang ditugaskan kepada Anda — tanda tangani sebelum berangkat ke lapangan." />
                <DataTable
                    columns={columns}
                    rows={sows.data}
                    rowKey="id"
                    rowHref={(s) => `/technician/sows/${s.id}`}
                    empty={<EmptyState title="Belum ada SOW untuk Anda." />}
                    footer={<Pagination links={sows.links} />}
                />
            </div>
        </AppLayout>
    );
}
