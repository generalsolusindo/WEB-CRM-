import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import Pagination from '../../../Components/Pagination';

const statusBadge = {
    draft: 'bg-text-muted/10 text-text-muted',
    planning: 'bg-warning/10 text-warning',
    waiting_resource: 'bg-warning/10 text-warning',
    ready: 'bg-info/10 text-info',
    in_progress: 'bg-info/10 text-info',
    verification: 'bg-warning/10 text-warning',
    completed: 'bg-success/10 text-success',
};

export default function Index({ projects, filters, statusOptions, role }) {
    const base = role === 'management' ? '/management/projects' : '/project-manager/projects';

    function setStatus(status) {
        router.get(base, { status }, { preserveState: true, replace: true });
    }

    return (
        <AppLayout>
            <Head title={role === 'management' ? 'Semua Project' : 'Project Saya'} />
            <div className="mx-auto max-w-5xl space-y-5">
                <div>
                    <h1 className="text-2xl font-bold text-text">{role === 'management' ? 'Semua Project' : 'Project Saya'}</h1>
                    <p className="text-sm text-text-muted">
                        {role === 'management'
                            ? 'Monitoring seluruh project — read-only. Eksekusi harian tetap di tangan Operational.'
                            : 'Project yang didelegasikan Manager kepada Anda.'}
                    </p>
                </div>

                <div className="rounded-xl border border-border bg-surface shadow-sm">
                    <div className="flex flex-wrap gap-2 border-b border-border p-4">
                        <button onClick={() => setStatus('')} className={`rounded-lg px-3 py-1.5 text-sm ${!filters.status ? 'bg-navy text-white' : 'border border-border'}`}>Semua</button>
                        {statusOptions.map((o) => (
                            <button key={o.value} onClick={() => setStatus(o.value)} className={`rounded-lg px-3 py-1.5 text-sm ${filters.status === o.value ? 'bg-navy text-white' : 'border border-border'}`}>{o.label}</button>
                        ))}
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-bg text-text-muted">
                                <tr>
                                    <th className="px-4 py-3">Nomor</th>
                                    <th className="px-4 py-3">Customer</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3">Material</th>
                                    {role === 'management' && <th className="px-4 py-3">Didelegasikan ke</th>}
                                    <th className="px-4 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {projects.data.map((p) => (
                                    <tr key={p.id} className="hover:bg-bg/70">
                                        <td className="px-4 py-3 font-medium text-text">{p.number}</td>
                                        <td className="px-4 py-3 text-text-muted">{p.customer || '—'}</td>
                                        <td className="px-4 py-3"><span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${statusBadge[p.status] ?? ''}`}>{p.status_label}</span></td>
                                        <td className="px-4 py-3">
                                            {p.material_status && p.material_status.total > 0 ? (
                                                <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${p.material_status.is_complete ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning'}`}>
                                                    {p.material_status.complete}/{p.material_status.total} lengkap
                                                </span>
                                            ) : '—'}
                                        </td>
                                        {role === 'management' && <td className="px-4 py-3 text-text-muted">{p.delegated_to || '—'}</td>}
                                        <td className="px-4 py-3 text-right"><Link href={`${base}/${p.id}`} className="font-medium text-info hover:underline">Lihat</Link></td>
                                    </tr>
                                ))}
                                {projects.data.length === 0 && (
                                    <tr><td colSpan={role === 'management' ? 6 : 5} className="px-4 py-12 text-center text-text-muted">
                                        {role === 'management' ? 'Belum ada project.' : 'Belum ada project yang didelegasikan kepada Anda.'}
                                    </td></tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <div className="border-t border-border p-4"><Pagination links={projects.links} /></div>
                </div>
            </div>
        </AppLayout>
    );
}
