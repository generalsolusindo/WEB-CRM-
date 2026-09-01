import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../../Layouts/AppLayout';
import Pagination from '../../../Components/Pagination';

const statusBadge = {
    draft: 'bg-warning/10 text-warning',
    planning: 'bg-info/10 text-info',
    waiting_resource: 'bg-warning/10 text-warning',
    ready: 'bg-info/10 text-info',
    in_progress: 'bg-info/10 text-info',
    verification: 'bg-warning/10 text-warning',
    completed: 'bg-success/10 text-success',
};

function prj(id) {
    return `PRJ-${String(id).padStart(6, '0')}`;
}

export default function Index({ projects, filters, statusOptions }) {
    const [form, setForm] = useState(filters);

    function applyFilter(next) {
        const merged = { ...form, ...next };
        setForm(merged);
        router.get('/operational/projects', merged, { preserveState: true, replace: true });
    }

    return (
        <AppLayout>
            <Head title="Project" />
            <div className="mx-auto max-w-6xl space-y-5">
                <div>
                    <h1 className="text-2xl font-bold text-text">Project</h1>
                    <p className="text-sm text-text-muted">Project otomatis muncul saat invoice muka Sales Order lunas.</p>
                </div>

                <div className="flex flex-wrap gap-3 rounded-xl border border-border bg-surface p-4 shadow-sm">
                    <input value={form.search} onChange={(e) => setForm({ ...form, search: e.target.value })} onKeyDown={(e) => e.key === 'Enter' && applyFilter({})} placeholder="Cari nomor SO / customer" className="flex-1 rounded-lg border border-border px-3 py-2 text-sm outline-none focus:border-navy" />
                    <select value={form.status} onChange={(e) => applyFilter({ status: e.target.value })} className="rounded-lg border border-border px-3 py-2 text-sm">
                        <option value="">Semua status</option>
                        {statusOptions.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                    </select>
                </div>

                <div className="overflow-hidden rounded-xl border border-border bg-surface shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-bg text-text-muted"><tr><th className="px-4 py-3">Project</th><th className="px-4 py-3">Customer</th><th className="px-4 py-3">Status</th><th className="px-4 py-3 text-right">Aksi</th></tr></thead>
                            <tbody className="divide-y divide-border">
                                {projects.data.map((p) => (
                                    <tr key={p.id} className="hover:bg-bg/70">
                                        <td className="px-4 py-3 font-semibold text-text">{prj(p.id)}<div className="text-xs font-normal text-text-muted">{p.sales_order.number}</div></td>
                                        <td className="px-4 py-3 text-text-muted">{p.sales_order.contact.name}</td>
                                        <td className="px-4 py-3"><span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${statusBadge[p.status]}`}>{statusOptions.find((s) => s.value === p.status)?.label ?? p.status}</span></td>
                                        <td className="px-4 py-3 text-right"><Link href={`/operational/projects/${p.id}`} className="font-medium text-info hover:underline">Lihat</Link></td>
                                    </tr>
                                ))}
                                {projects.data.length === 0 && <tr><td colSpan="4" className="px-4 py-12 text-center text-text-muted">Belum ada project.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                    <div className="border-t border-border p-4"><Pagination links={projects.links} /></div>
                </div>
            </div>
        </AppLayout>
    );
}
