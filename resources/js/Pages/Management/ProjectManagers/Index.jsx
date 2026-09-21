import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { FiPlus } from 'react-icons/fi';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, Toolbar, SearchInput, Button, DataTable, EmptyState, Pagination } from '../../../Components/ui';

export default function Index({ projectManagers, filters }) {
    const [search, setSearch] = useState(filters.search ?? '');

    function submit(e) {
        e.preventDefault();
        router.get('/management/project-managers', { search }, { preserveState: true, replace: true });
    }

    const columns = [
        { key: 'name', label: 'Nama', render: (pm) => <span className="font-medium text-text">{pm.name}</span> },
        { key: 'username', hideBelow: 'md', label: 'Username', render: (pm) => <span className="text-text-muted">{pm.username}</span> },
        { key: 'email', hideBelow: 'md', label: 'Email', render: (pm) => <span className="text-text-muted">{pm.email}</span> },
        { key: 'phone', hideBelow: 'sm', label: 'Telepon', render: (pm) => pm.phone || '—' },
        {
            key: 'status',
            label: 'Status',
            render: (pm) => <span className={`badge ${pm.is_active ? 'badge-success' : 'badge-neutral'}`}>{pm.is_active ? 'Aktif' : 'Nonaktif'}</span>,
        },
    ];

    return (
        <AppLayout>
            <Head title="Project Manager" />
            <div className="mx-auto max-w-5xl space-y-5">
                <PageHeader
                    title="Project Manager"
                    subtitle="Akun bawahan Manager — dipakai untuk delegasi project tertentu."
                    actions={<Button href="/management/project-managers/create" icon={FiPlus}>Tambah Akun</Button>}
                />
                <form onSubmit={submit}>
                    <Toolbar>
                        <SearchInput value={search} onChange={setSearch} placeholder="Cari nama atau email" />
                        <Button type="submit">Cari</Button>
                    </Toolbar>
                </form>
                <DataTable
                    columns={columns}
                    rows={projectManagers.data}
                    rowKey="id"
                    rowHref={(pm) => `/management/project-managers/${pm.id}/edit`}
                    empty={<EmptyState title="Belum ada akun Project Manager." />}
                    footer={<Pagination links={projectManagers.links} />}
                />
            </div>
        </AppLayout>
    );
}
