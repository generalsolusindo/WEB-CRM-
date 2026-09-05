import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../../Layouts/AppLayout';
import Pagination from '../../../Components/Pagination';

export default function Index({ projectManagers, filters }) {
    const [search, setSearch] = useState(filters.search ?? '');

    function submit(e) {
        e.preventDefault();
        router.get('/management/project-managers', { search }, { preserveState: true, replace: true });
    }

    return (
        <AppLayout>
            <Head title="Project Manager" />
            <div className="mx-auto max-w-5xl space-y-5">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <h1 className="text-2xl font-bold text-text">Project Manager</h1>
                        <p className="text-sm text-text-muted">Akun bawahan Manager — dipakai untuk delegasi project tertentu.</p>
                    </div>
                    <Link href="/management/project-managers/create" className="rounded-lg bg-navy px-4 py-2 text-center text-sm font-semibold text-white hover:bg-navy-light">
                        Tambah Akun
                    </Link>
                </div>

                <div className="rounded-xl border border-border bg-surface shadow-sm">
                    <form onSubmit={submit} className="flex gap-2 border-b border-border p-4">
                        <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Cari nama atau email" className="w-full rounded-lg border border-border px-3 py-2 text-sm outline-none focus:border-navy" />
                        <button className="rounded-lg bg-navy px-4 py-2 text-sm font-medium text-white">Cari</button>
                    </form>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-bg text-text-muted"><tr><th className="px-4 py-3">Nama</th><th className="px-4 py-3">Email</th><th className="px-4 py-3">Telepon</th><th className="px-4 py-3">Status</th><th className="px-4 py-3 text-right">Aksi</th></tr></thead>
                            <tbody className="divide-y divide-border">
                                {projectManagers.data.map((pm) => (
                                    <tr key={pm.id} className="hover:bg-bg/70">
                                        <td className="px-4 py-3 font-medium text-text">{pm.name}</td>
                                        <td className="px-4 py-3 text-text-muted">{pm.email}</td>
                                        <td className="px-4 py-3 text-text-muted">{pm.phone || '—'}</td>
                                        <td className="px-4 py-3"><span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${pm.is_active ? 'bg-success/10 text-success' : 'bg-text-muted/10 text-text-muted'}`}>{pm.is_active ? 'Aktif' : 'Nonaktif'}</span></td>
                                        <td className="px-4 py-3 text-right"><Link href={`/management/project-managers/${pm.id}/edit`} className="font-medium text-info hover:underline">Edit</Link></td>
                                    </tr>
                                ))}
                                {projectManagers.data.length === 0 && <tr><td colSpan="5" className="px-4 py-12 text-center text-text-muted">Belum ada akun Project Manager.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                    <div className="border-t border-border p-4"><Pagination links={projectManagers.links} /></div>
                </div>
            </div>
        </AppLayout>
    );
}
