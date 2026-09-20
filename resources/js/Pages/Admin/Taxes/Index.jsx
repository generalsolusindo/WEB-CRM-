import { Head } from '@inertiajs/react';
import { FiPlus } from 'react-icons/fi';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, Button, DataTable, EmptyState } from '../../../Components/ui';
import { feedback } from '../../../Components/feedback';

export default function Index({ taxes }) {
    function destroy(tax) {
        feedback.act({
            method: 'delete',
            url: `/admin/taxes/${tax.id}`,
            confirm: { tone: 'danger', title: 'Hapus pajak?', text: `"${tax.name}" akan dihapus. Pajak yang sudah dipakai di dokumen tidak bisa dihapus.`, confirmLabel: 'Ya, hapus' },
            success: { title: 'Pajak dihapus' },
        });
    }

    const columns = [
        { key: 'name', label: 'Nama', render: (t) => <span className="font-medium text-text">{t.name}</span> },
        { key: 'rate', label: 'Tarif', align: 'right', render: (t) => `${Number(t.rate)}%` },
        {
            key: 'status',
            label: 'Status',
            render: (t) => <span className={`badge ${t.is_active ? 'badge-success' : 'badge-neutral'}`}>{t.is_active ? 'Aktif' : 'Nonaktif'}</span>,
        },
        {
            key: 'actions',
            label: 'Aksi',
            align: 'right',
            render: (t) => (
                <span className="whitespace-nowrap">
                    <a href={`/admin/taxes/${t.id}/edit`} className="font-medium text-primary hover:underline">Edit</a>
                    <button onClick={() => destroy(t)} className="ml-4 font-medium text-danger hover:underline">Hapus</button>
                </span>
            ),
        },
    ];

    return (
        <AppLayout>
            <Head title="Master Pajak" />
            <div className="mx-auto max-w-4xl space-y-5">
                <PageHeader
                    title="Master Pajak"
                    subtitle="Konfigurasi tarif pajak (PPN dll). Dipakai per baris di Quotation dan Sales Order."
                    actions={<Button href="/admin/taxes/create" icon={FiPlus}>Tambah Pajak</Button>}
                />
                <DataTable
                    columns={columns}
                    rows={taxes}
                    rowKey="id"
                    empty={<EmptyState title="Belum ada pajak." />}
                />
            </div>
        </AppLayout>
    );
}
