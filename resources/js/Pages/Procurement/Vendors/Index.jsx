import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { FiPlus } from 'react-icons/fi';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, Toolbar, SearchInput, Button, DataTable, EmptyState, Pagination } from '../../../Components/ui';

export default function Index({ vendors, filters }) {
    const [search, setSearch] = useState(filters.search ?? '');

    function submit(e) {
        e.preventDefault();
        router.get('/procurement/vendors', { search }, { preserveState: true, replace: true });
    }

    const columns = [
        {
            key: 'name',
            label: 'Vendor',
            render: (v) => (
                <div>
                    <div className="font-medium text-text">{v.name}</div>
                    <div className="text-xs text-text-muted">{v.contact_person || '—'}</div>
                </div>
            ),
        },
        {
            key: 'contact',
            label: 'Kontak',
            render: (v) => (
                <div className="text-text-muted">
                    <div>{v.email || '—'}</div>
                    <div>{v.phone || ''}</div>
                </div>
            ),
        },
        { key: 'products_count', label: 'Produk', align: 'right', render: (v) => <span className="tabular-nums">{v.products_count}</span> },
    ];

    return (
        <AppLayout>
            <Head title="Vendor & Katalog Produk" />
            <div className="mx-auto max-w-5xl space-y-5">
                <PageHeader
                    title="Vendor & Katalog Produk"
                    subtitle="Daftar vendor dan produk/jasa beserta harga acuan."
                    actions={<Button href="/procurement/vendors/create" icon={FiPlus}>Tambah Vendor</Button>}
                />

                <form onSubmit={submit}>
                    <Toolbar>
                        <SearchInput value={search} onChange={setSearch} placeholder="Cari vendor, PIC, email" />
                        <Button type="submit">Cari</Button>
                    </Toolbar>
                </form>

                <DataTable
                    columns={columns}
                    rows={vendors.data}
                    rowKey="id"
                    rowHref={(v) => `/procurement/vendors/${v.id}`}
                    empty={<EmptyState title="Belum ada vendor." />}
                    footer={<Pagination links={vendors.links} />}
                />
            </div>
        </AppLayout>
    );
}
