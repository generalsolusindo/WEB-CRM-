import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import Pagination from '../../../Components/Pagination';

function badge(status) {
    if (status === 'report_review') return 'bg-info/10 text-info';
    if (status === 'awaiting_briefing') return 'bg-warning/10 text-warning';
    return 'bg-navy/10 text-navy';
}

export default function Index({ surveys }) {
    return (
        <AppLayout>
            <Head title="Survey — Operasional" />
            <div className="mx-auto max-w-5xl space-y-5">
                <div>
                    <h1 className="text-2xl font-bold text-text">Survey</h1>
                    <p className="text-sm text-text-muted">Beri arahan ke surveyor, lalu verifikasi laporan hasil survey.</p>
                </div>

                <div className="overflow-hidden rounded-xl border border-border bg-surface shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-bg text-text-muted"><tr><th className="px-4 py-3">Kode</th><th className="px-4 py-3">Customer</th><th className="px-4 py-3">Lokasi</th><th className="px-4 py-3">Surveyor</th><th className="px-4 py-3">Status</th><th className="px-4 py-3 text-right">Aksi</th></tr></thead>
                            <tbody className="divide-y divide-border">
                                {surveys.data.map((s) => (
                                    <tr key={s.id} className="hover:bg-bg/70">
                                        <td className="px-4 py-3 font-medium text-text">{s.code}{s.revision > 1 ? ` · rev.${s.revision}` : ''}</td>
                                        <td className="px-4 py-3 text-text-muted">{s.customer || '—'}</td>
                                        <td className="px-4 py-3 text-text-muted">{s.site_region}</td>
                                        <td className="px-4 py-3 text-text-muted">{s.surveyor || '—'}</td>
                                        <td className="px-4 py-3"><span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${badge(s.status)}`}>{s.status_label}</span></td>
                                        <td className="px-4 py-3 text-right"><Link href={`/operational/surveys/${s.id}`} className="font-medium text-info hover:underline">Buka</Link></td>
                                    </tr>
                                ))}
                                {surveys.data.length === 0 && <tr><td colSpan="6" className="px-4 py-12 text-center text-text-muted">Tidak ada survey aktif.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                    <div className="border-t border-border p-4"><Pagination links={surveys.links} /></div>
                </div>
            </div>
        </AppLayout>
    );
}
