import { Head } from '@inertiajs/react';
import { FiPlus } from 'react-icons/fi';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, Button, DataTable, EmptyState } from '../../../Components/ui';

export default function Index({ salesOrder, deliveryNotes, canCreate }) {
    const columns = [
        { key: 'number', label: 'Nomor', render: (dn) => <span className="font-medium text-text">{dn.number}</span> },
        { key: 'created_by', label: 'Dibuat oleh', render: (dn) => dn.created_by },
        {
            key: 'status',
            label: 'Status',
            render: (dn) => <span className={`badge ${dn.status === 'received' ? 'badge-success' : 'badge-warning'}`}>{dn.status === 'received' ? 'Diterima' : 'Terkirim'}</span>,
        },
    ];

    return (
        <AppLayout>
            <Head title={`Delivery Note — ${salesOrder.number}`} />
            <div className="mx-auto max-w-4xl space-y-5">
                <PageHeader
                    title="Delivery Note"
                    subtitle={`${salesOrder.number} · ${salesOrder.customer}`}
                    actions={canCreate && <Button href={`/operational/sales-orders/${salesOrder.id}/delivery-notes/create`} icon={FiPlus}>Buat Delivery Note</Button>}
                />

                <DataTable
                    columns={columns}
                    rows={deliveryNotes}
                    rowKey="id"
                    rowHref={(dn) => `/operational/delivery-notes/${dn.id}`}
                    empty={<EmptyState title="Belum ada Delivery Note untuk Sales Order ini." />}
                />
            </div>
        </AppLayout>
    );
}
