import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import Pagination from '../../../Components/Pagination';

function badge(status) {
    if (['verified', 'closed'].includes(status)) return 'bg-success/10 text-success';
    if (status === 'cancelled') return 'bg-text-muted/10 text-text-muted';
    if (status === 'requested') return 'bg-info/10 text-info';
    return 'bg-warning/10 text-warning';
}

export default function Index({ surveys, filters, statusOptions }) {
    function setStatus(status) {
        router.get('/procurement/surveys', { status }, { preserveState: true, replace: true });
    }

    return (
        <AppLayout>
            <Head title="Survey" />
            <div className="mx-auto max-w-5xl space-y-5">
                <div>
                    <h1 className="text-2xl font-bold text-text">Survey</h1>
                    <p className="text-sm text-text-muted">Permintaan survey dari Sales. Tetapkan surveyor (internal/vendor) dan biayanya.</p>
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
                            <thead className="bg-bg text-text-muted"><tr><th className="px-4 py-3">Kode</th><th className="px-4 py-3">Customer</th><th className="px-4 py-3">Lokasi</th><th className="px-4 py-3">Pelaksana</th><th className="px-4 py-3">Surveyor</th><th className="px-4 py-3">Status</th><th className="px-4 py-3 text-right">Aksi</th></tr></thead>
                            <tbody className="divide-y divide-border">
                                {surveys.data.map((s) => (
                                    <tr key={s.id} className="hover:bg-bg/70">
                                        <td className="px-4 py-3 font-medium text-text">{s.code}</td>
                                        <td className="px-4 py-3 text-text-muted">{s.customer || '—'}</td>
                                        <td className="px-4 py-3 text-text-muted">{s.site_region}</td>
                                        <td className="px-4 py-3 text-text-muted">{s.delivery_mode}{s.billable ? ' · ditagih' : ''}</td>
                                        <td className="px-4 py-3 text-text-muted">{s.surveyor || '—'}</td>
                                        <td className="px-4 py-3"><span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${badge(s.status)}`}>{s.status_label}</span></td>
                                        <td className="px-4 py-3 text-right"><Link href={`/procurement/surveys/${s.id}`} className="font-medium text-info hover:underline">Lihat</Link></td>
                                    </tr>
                                ))}
                                {surveys.data.length === 0 && <tr><td colSpan="7" className="px-4 py-12 text-center text-text-muted">Belum ada permintaan survey.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                    <div className="border-t border-border p-4"><Pagination links={surveys.links} /></div>
                </div>
            </div>
        </AppLayout>
    );
}
