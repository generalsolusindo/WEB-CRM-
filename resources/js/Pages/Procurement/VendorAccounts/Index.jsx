import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { FiPlus } from 'react-icons/fi';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, Toolbar, SearchInput, Button, DataTable, EmptyState, Pagination } from '../../../Components/ui';

export default function Index({ accounts, filters }) {
    const [search, setSearch] = useState(filters.search ?? '');

    function submit(e) {
        e.preventDefault();
        router.get('/procurement/vendor-accounts', { search }, { preserveState: true, replace: true });
    }

    const columns = [
        { key: 'name', label: 'Nama', render: (a) => <span className="font-medium text-text">{a.name}</span> },
        { key: 'username', label: 'Username', render: (a) => <span className="text-text-muted">{a.username}</span> },
        { key: 'email', label: 'Email', render: (a) => <span className="text-text-muted">{a.email}</span> },
        { key: 'phone', label: 'Telepon', render: (a) => a.phone || '—' },
        { key: 'vendor', label: 'Vendor', render: (a) => a.vendor || '—' },
        {
            key: 'status',
            label: 'Status',
            render: (a) => <span className={`badge ${a.is_active ? 'badge-success' : 'badge-neutral'}`}>{a.is_active ? 'Aktif' : 'Nonaktif'}</span>,
        },
    ];

    return (
        <AppLayout>
            <Head title="Akun PIC Vendor" />
            <div className="mx-auto max-w-5xl space-y-5">
                <PageHeader
                    title="Akun PIC Vendor"
                    subtitle="Akun untuk PIC vendor eksternal — dipakai tanda tangan digital dokumen SOW. Satu vendor hanya boleh punya satu akun."
                    actions={<Button href="/procurement/vendor-accounts/create" icon={FiPlus}>Tambah Akun</Button>}
                />

                <form onSubmit={submit}>
                    <Toolbar>
                        <SearchInput value={search} onChange={setSearch} placeholder="Cari nama atau email" />
                        <Button type="submit">Cari</Button>
                    </Toolbar>
                </form>

                <DataTable
                    columns={columns}
                    rows={accounts.data}
                    rowKey="id"
                    rowHref={(a) => `/procurement/vendor-accounts/${a.id}/edit`}
                    empty={<EmptyState title="Belum ada akun PIC vendor." />}
                    footer={<Pagination links={accounts.links} />}
                />
            </div>
        </AppLayout>
    );
}
