import { Head } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, DataTable, EmptyState } from '../../../Components/ui';

export default function Index({ project, deliveryNotes }) {
    const columns = [
        { key: 'number', label: 'Nomor', render: (dn) => <span className="font-medium text-text">{dn.number}</span> },
        {
            key: 'status',
            label: 'Status',
            render: (dn) => <span className={`badge ${dn.status === 'received' ? 'badge-success' : 'badge-warning'}`}>{dn.status === 'received' ? 'Diterima' : 'Menunggu Konfirmasi'}</span>,
        },
    ];

    return (
        <AppLayout>
            <Head title={`Delivery Note — ${project.number}`} />
            <div className="mx-auto max-w-2xl space-y-5">
                <PageHeader
                    title="Delivery Note"
                    subtitle={`${project.number} · ${project.customer}`}
                    back={{ href: '/technician/tasks', label: 'Tugas Saya' }}
                />
                <DataTable
                    columns={columns}
                    rows={deliveryNotes}
                    rowKey="id"
                    rowHref={(dn) => `/technician/delivery-notes/${dn.id}`}
                    empty={<EmptyState title="Belum ada Delivery Note untuk project ini." />}
                />
            </div>
        </AppLayout>
    );
}
