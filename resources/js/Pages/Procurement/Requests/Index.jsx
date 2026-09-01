import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../../Layouts/AppLayout';
import Pagination from '../../../Components/Pagination';

const badge = {
    submitted: 'bg-warning/10 text-warning',
    searching: 'bg-info/10 text-info',
    ready: 'bg-success/10 text-success',
    rejected: 'bg-danger/10 text-danger',
    draft: 'bg-bg text-text-muted',
};

export default function Index({ requests, filters, statusOptions }) {
    const [form, setForm] = useState(filters);
    function set(name, value) { setForm((c) => ({ ...c, [name]: value })); }
    function submit(e) { e.preventDefault(); router.get('/procurement/procurement-requests', form, { preserveState: true, replace: true }); }

    return (
        <AppLayout>
            <Head title="Procurement Request" />
            <div className="mx-auto max-w-6xl space-y-5">
                <div>
                    <h1 className="text-2xl font-bold text-text">Procurement Request</h1>
                    <p className="text-sm text-text-muted">Antrean sourcing dari tim Sales. Yang perlu aksi ada di atas.</p>
                </div>

                <form onSubmit={submit} className="flex flex-wrap gap-3 rounded-xl border border-border bg-surface p-4 shadow-sm">
                    <input value={form.search} onChange={(e) => set('search', e.target.value)} placeholder="Cari nomor PR / customer" className="flex-1 rounded-lg border border-border px-3 py-2 text-sm outline-none focus:border-navy" />
                    <select value={form.status} onChange={(e) => set('status', e.target.value)} className="rounded-lg border border-border px-3 py-2 text-sm">
                        <option value="">Semua status</option>
                        {statusOptions.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                    </select>
                    <button className="rounded-lg bg-navy px-4 py-2 text-sm text-white">Filter</button>
                </form>

                <div className="overflow-hidden rounded-xl border border-border bg-surface shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-bg text-text-muted"><tr><th className="px-4 py-3">Nomor PR</th><th className="px-4 py-3">Customer</th><th className="px-4 py-3 text-right">Line</th><th className="px-4 py-3">Status</th><th className="px-4 py-3">Tanggal</th><th className="px-4 py-3 text-right">Aksi</th></tr></thead>
                            <tbody className="divide-y divide-border">
                                {requests.data.map((pr) => (
                                    <tr key={pr.id} className="hover:bg-bg/70">
                                        <td className="px-4 py-3 font-semibold text-text">PR-{String(pr.id).padStart(6, '0')}</td>
                                        <td className="px-4 py-3"><div className="font-medium text-text">{pr.lead.contact.name}</div><div className="text-xs text-text-muted">{pr.lead.contact.company_name || '—'}</div></td>
                                        <td className="px-4 py-3 text-right text-text-muted">{pr.lines_count}</td>
                                        <td className="px-4 py-3"><span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${badge[pr.status]}`}>{statusOptions.find((s) => s.value === pr.status)?.label ?? pr.status}</span></td>
                                        <td className="px-4 py-3 text-text-muted">{pr.created_at?.slice(0, 10)}</td>
                                        <td className="px-4 py-3 text-right"><Link href={`/procurement/procurement-requests/${pr.id}`} className="font-medium text-info hover:underline">Lihat</Link></td>
                                    </tr>
                                ))}
                                {requests.data.length === 0 && <tr><td colSpan="6" className="px-4 py-12 text-center text-text-muted">Tidak ada Procurement Request.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                    <div className="border-t border-border p-4"><Pagination links={requests.links} /></div>
                </div>
            </div>
        </AppLayout>
    );
}
