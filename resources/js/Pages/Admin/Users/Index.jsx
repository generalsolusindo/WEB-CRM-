import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { FiPlus } from 'react-icons/fi';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, Toolbar, SearchInput, FilterSelect, Button, DataTable, EmptyState, Pagination } from '../../../Components/ui';

export default function Index({ users, filters, roleOptions }) {
    const [search, setSearch] = useState(filters.search ?? '');

    function submit(e) {
        e.preventDefault();
        router.get('/admin/users', { search, role: filters.role || undefined }, { preserveState: true, replace: true });
    }

    function setRole(role) {
        router.get('/admin/users', { search, role: role || undefined }, { preserveState: true, replace: true });
    }

    const columns = [
        {
            key: 'name',
            label: 'Nama',
            render: (u) => (
                <span className="flex items-center gap-2">
                    <span className="font-medium text-text">{u.name}</span>
                    {u.is_self && <span className="rounded-full bg-info-soft px-2 py-0.5 text-[10px] font-semibold text-info">Anda</span>}
                </span>
            ),
        },
        { key: 'username', hideBelow: 'md', label: 'Username', render: (u) => <span className="text-text-muted">{u.username}</span> },
        { key: 'role', label: 'Role', render: (u) => <span className="badge badge-neutral">{u.role_label}</span> },
        {
            key: 'status',
            label: 'Status',
            render: (u) => <span className={`badge ${u.is_active ? 'badge-success' : 'badge-neutral'}`}>{u.is_active ? 'Aktif' : 'Nonaktif'}</span>,
        },
    ];

    return (
        <AppLayout>
            <Head title="Manajemen User" />
            <div className="mx-auto max-w-5xl space-y-5">
                <PageHeader
                    title="Manajemen User"
                    subtitle="Semua akun lintas role dalam satu tempat — buat akun baru, reset password, atau nonaktifkan akun. Akun vendor tetap dibuat lewat Procurement > Akun PIC Vendor."
                    actions={<Button href="/admin/users/create" icon={FiPlus}>Tambah User</Button>}
                />

                <form onSubmit={submit}>
                    <Toolbar>
                        <SearchInput value={search} onChange={setSearch} placeholder="Cari nama atau username" />
                        <FilterSelect value={filters.role || ''} onChange={setRole}>
                            <option value="">Semua Role</option>
                            {Object.entries(roleOptions).map(([value, label]) => (
                                <option key={value} value={value}>{label}</option>
                            ))}
                        </FilterSelect>
                        <Button type="submit">Cari</Button>
                    </Toolbar>
                </form>

                <DataTable
                    columns={columns}
                    rows={users.data}
                    rowKey="id"
                    rowHref={(u) => `/admin/users/${u.id}/edit`}
                    empty={<EmptyState title="Tidak ada user ditemukan." />}
                    footer={<Pagination links={users.links} />}
                />
            </div>
        </AppLayout>
    );
}
