import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../../Layouts/AppLayout';
import Pagination from '../../../Components/Pagination';

export default function Index({ vendors, filters }) {
    const [search, setSearch] = useState(filters.search ?? '');

    function submit(e) {
        e.preventDefault();
        router.get('/procurement/vendors', { search }, { preserveState: true, replace: true });
    }

    return (
        <AppLayout>
            <Head title="Vendor & Katalog Produk" />
            <div className="mx-auto max-w-5xl space-y-5">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <h1 className="text-2xl font-bold text-text">Vendor & Katalog Produk</h1>
                        <p className="text-sm text-text-muted">Daftar vendor dan produk/jasa beserta harga acuan.</p>
                    </div>
                    <Link href="/procurement/vendors/create" className="rounded-lg bg-navy px-4 py-2 text-center text-sm font-semibold text-white hover:bg-navy-light">
                        Tambah Vendor
                    </Link>
                </div>

                <div className="rounded-xl border border-border bg-surface shadow-sm">
                    <form onSubmit={submit} className="flex gap-2 border-b border-border p-4">
                        <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Cari vendor, PIC, email" className="w-full rounded-lg border border-border px-3 py-2 text-sm outline-none focus:border-navy" />
                        <button className="rounded-lg bg-navy px-4 py-2 text-sm font-medium text-white">Cari</button>
                    </form>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-bg text-text-muted"><tr><th className="px-4 py-3">Vendor</th><th className="px-4 py-3">Kontak</th><th className="px-4 py-3 text-right">Produk</th><th className="px-4 py-3 text-right">Aksi</th></tr></thead>
                            <tbody className="divide-y divide-border">
                                {vendors.data.map((vendor) => (
                                    <tr key={vendor.id} className="hover:bg-bg/70">
                                        <td className="px-4 py-3"><div className="font-medium text-text">{vendor.name}</div><div className="text-xs text-text-muted">{vendor.contact_person || '—'}</div></td>
                                        <td className="px-4 py-3 text-text-muted"><div>{vendor.email || '—'}</div><div>{vendor.phone || ''}</div></td>
                                        <td className="px-4 py-3 text-right text-text-muted">{vendor.products_count}</td>
                                        <td className="px-4 py-3 text-right"><Link href={`/procurement/vendors/${vendor.id}`} className="font-medium text-info hover:underline">Lihat</Link></td>
                                    </tr>
                                ))}
                                {vendors.data.length === 0 && <tr><td colSpan="4" className="px-4 py-12 text-center text-text-muted">Belum ada vendor.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                    <div className="border-t border-border p-4"><Pagination links={vendors.links} /></div>
                </div>
            </div>
        </AppLayout>
    );
}
