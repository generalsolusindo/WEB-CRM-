import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { FiUserPlus, FiUsers } from 'react-icons/fi';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, Toolbar, SearchInput, Button, DataTable, Pagination, EmptyState } from '../../../Components/ui';

export default function Index({ contacts, filters }) {
    const [search, setSearch] = useState(filters.search ?? '');

    function submit(e) {
        e?.preventDefault();
        router.get('/sales/contacts', { search }, { preserveState: true, replace: true });
    }

    const columns = [
        { key: 'name', label: 'Nama', render: (c) => <span className="font-semibold text-text">{c.name}</span> },
        { key: 'company_name', label: 'Perusahaan', render: (c) => <span className="text-text-muted">{c.company_name || '—'}</span> },
        {
            key: 'contact', label: 'Kontak',
            render: (c) => (
                <div className="text-text-muted">
                    <div>{c.email || '—'}</div>
                    {c.phone && <div className="text-xs">{c.phone}</div>}
                </div>
            ),
        },
        { key: 'leads_count', label: 'Lead', align: 'right', render: (c) => <span className="tabular-nums text-text-muted">{c.leads_count}</span> },
    ];

    return (
        <AppLayout>
            <Head title="Contacts" />
            <div className="mx-auto max-w-6xl space-y-5">
                <PageHeader
                    title="Contacts"
                    subtitle="Kelola customer dan PIC milik Anda."
                    actions={<Button href="/sales/contacts/create" icon={FiUserPlus}>Tambah Contact</Button>}
                />

                <form onSubmit={submit}>
                    <Toolbar>
                        <SearchInput value={search} onChange={setSearch} placeholder="Cari nama, perusahaan, email, atau telepon" />
                        <Button type="submit">Cari</Button>
                    </Toolbar>
                </form>

                <DataTable
                    columns={columns}
                    rows={contacts.data}
                    rowHref={(c) => `/sales/contacts/${c.id}`}
                    footer={<Pagination links={contacts.links} />}
                    empty={<EmptyState icon={FiUsers} title="Belum ada contact" description="Tambahkan customer atau PIC untuk mulai membangun pipeline." action={<Button href="/sales/contacts/create" icon={FiUserPlus}>Tambah Contact</Button>} />}
                />
            </div>
        </AppLayout>
    );
}
