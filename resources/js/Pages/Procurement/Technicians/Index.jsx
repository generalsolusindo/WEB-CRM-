import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { FiPlus } from 'react-icons/fi';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, Toolbar, SearchInput, Button, DataTable, StatusBadge, EmptyState, Pagination } from '../../../Components/ui';

export default function Index({ technicians, filters }) {
    const [search, setSearch] = useState(filters.search ?? '');

    function submit(e) {
        e.preventDefault();
        router.get('/procurement/technicians', { search }, { preserveState: true, replace: true });
    }

    const columns = [
        { key: 'name', label: 'Nama', render: (t) => <span className="font-medium text-text">{t.name}</span> },
        { key: 'email', label: 'Email', render: (t) => <span className="text-text-muted">{t.email}</span> },
        { key: 'phone', label: 'Telepon', render: (t) => t.phone || '—' },
        { key: 'origin', label: 'Asal' },
        {
            key: 'status',
            label: 'Status',
            render: (t) => <span className={`badge ${t.is_active ? 'badge-success' : 'badge-neutral'}`}>{t.is_active ? 'Aktif' : 'Nonaktif'}</span>,
        },
    ];

    return (
        <AppLayout>
            <Head title="Surveyor & Teknisi" />
            <div className="mx-auto max-w-5xl space-y-5">
                <PageHeader
                    title="Surveyor & Teknisi"
                    subtitle="Akun petugas lapangan — internal (HO) maupun milik vendor. Dipakai untuk survey dan pekerjaan teknis."
                    actions={<Button href="/procurement/technicians/create" icon={FiPlus}>Tambah Akun</Button>}
                />

                <form onSubmit={submit}>
                    <Toolbar>
                        <SearchInput value={search} onChange={setSearch} placeholder="Cari nama atau email" />
                        <Button type="submit">Cari</Button>
                    </Toolbar>
                </form>

                <DataTable
                    columns={columns}
                    rows={technicians.data}
                    rowKey="id"
                    rowHref={(t) => `/procurement/technicians/${t.id}/edit`}
                    empty={<EmptyState title="Belum ada akun surveyor/teknisi." />}
                    footer={<Pagination links={technicians.links} />}
                />
            </div>
        </AppLayout>
    );
}
