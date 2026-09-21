import { Head } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, DataTable, StatusBadge, EmptyState, Pagination } from '../../../Components/ui';

export default function Index({ surveys }) {
    const columns = [
        { key: 'code', label: 'Kode', render: (s) => <span className="font-medium text-text">{s.code}{s.revision > 1 ? ` · rev.${s.revision}` : ''}</span> },
        { key: 'customer', label: 'Customer', render: (s) => s.customer || '—' },
        { key: 'site_region', hideBelow: 'md', label: 'Lokasi' },
        { key: 'surveyor', label: 'Surveyor', render: (s) => s.surveyor || '—' },
        { key: 'status', label: 'Status', render: (s) => <StatusBadge status={s.status} label={s.status_label} /> },
    ];

    return (
        <AppLayout>
            <Head title="Survey — Operasional" />
            <div className="mx-auto max-w-5xl space-y-5">
                <PageHeader title="Survey" subtitle="Beri arahan ke surveyor, lalu verifikasi laporan hasil survey." />

                <DataTable
                    columns={columns}
                    rows={surveys.data}
                    rowKey="id"
                    rowHref={(s) => `/operational/surveys/${s.id}`}
                    empty={<EmptyState title="Tidak ada survey aktif." />}
                    footer={<Pagination links={surveys.links} />}
                />
            </div>
        </AppLayout>
    );
}
