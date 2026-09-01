import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../../Layouts/AppLayout';
import Pagination from '../../../Components/Pagination';

export default function Index({ contacts, filters }) {
    const [search, setSearch] = useState(filters.search ?? '');

    function submit(event) {
        event.preventDefault();
        router.get('/sales/contacts', { search }, { preserveState: true, replace: true });
    }

    return (
        <AppLayout>
            <Head title="Contacts" />
            <div className="mx-auto max-w-7xl space-y-5">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <h1 className="text-2xl font-bold text-text">Contacts</h1>
                        <p className="text-sm text-text-muted">Kelola customer dan PIC milik Anda.</p>
                    </div>
                    <Link href="/sales/contacts/create" className="rounded-lg bg-navy px-4 py-2 text-center text-sm font-semibold text-white hover:bg-navy-light">
                        Tambah Contact
                    </Link>
                </div>

                <div className="rounded-xl border border-border bg-surface shadow-sm">
                    <form onSubmit={submit} className="flex gap-2 border-b border-border p-4">
                        <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Cari nama, perusahaan, email, atau telepon" className="w-full rounded-lg border border-border px-3 py-2 text-sm outline-none focus:border-navy" />
                        <button className="rounded-lg bg-navy px-4 py-2 text-sm font-medium text-white">Cari</button>
                    </form>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-bg text-text-muted"><tr><th className="px-4 py-3">Nama</th><th className="px-4 py-3">Perusahaan</th><th className="px-4 py-3">Kontak</th><th className="px-4 py-3">Lead</th><th className="px-4 py-3 text-right">Aksi</th></tr></thead>
                            <tbody className="divide-y divide-border">
                                {contacts.data.map((contact) => (
                                    <tr key={contact.id} className="hover:bg-bg/70">
                                        <td className="px-4 py-3 font-medium text-text">{contact.name}</td>
                                        <td className="px-4 py-3 text-text-muted">{contact.company_name || '—'}</td>
                                        <td className="px-4 py-3 text-text-muted"><div>{contact.email || '—'}</div><div>{contact.phone || ''}</div></td>
                                        <td className="px-4 py-3 text-text-muted">{contact.leads_count}</td>
                                        <td className="px-4 py-3 text-right"><Link href={`/sales/contacts/${contact.id}`} className="font-medium text-info hover:underline">Lihat</Link></td>
                                    </tr>
                                ))}
                                {contacts.data.length === 0 && <tr><td colSpan="5" className="px-4 py-12 text-center text-text-muted">Belum ada contact yang sesuai.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                    <div className="border-t border-border p-4"><Pagination links={contacts.links} /></div>
                </div>
            </div>
        </AppLayout>
    );
}
