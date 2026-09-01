import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import Pagination from '../../../Components/Pagination';

function money(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(v || 0));
}

export default function Index({ surveys }) {
    return (
        <AppLayout>
            <Head title="Survey — Finance" />
            <div className="mx-auto max-w-5xl space-y-5">
                <div>
                    <h1 className="text-2xl font-bold text-text">Survey Menunggu Finance</h1>
                    <p className="text-sm text-text-muted">Terbitkan invoice bila survey ditagihkan ke customer, atau catat biaya vendor tanpa tagihan.</p>
                </div>

                <div className="overflow-hidden rounded-xl border border-border bg-surface shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-bg text-text-muted"><tr><th className="px-4 py-3">Kode</th><th className="px-4 py-3">Customer</th><th className="px-4 py-3">Lokasi</th><th className="px-4 py-3">Penagihan</th><th className="px-4 py-3 text-right">Biaya</th><th className="px-4 py-3">Status</th><th className="px-4 py-3 text-right">Aksi</th></tr></thead>
                            <tbody className="divide-y divide-border">
                                {surveys.data.map((s) => (
                                    <tr key={s.id} className="hover:bg-bg/70">
                                        <td className="px-4 py-3 font-medium text-text">{s.code}</td>
                                        <td className="px-4 py-3 text-text-muted">{s.customer || '—'}</td>
                                        <td className="px-4 py-3 text-text-muted">{s.site_region}</td>
                                        <td className="px-4 py-3 text-text-muted">{s.billable ? 'Ditagih ke customer' : `Biaya internal (${s.delivery_mode})`}</td>
                                        <td className="px-4 py-3 text-right text-text-muted">{money(s.cost)}</td>
                                        <td className="px-4 py-3"><span className="rounded-full bg-warning/10 px-2.5 py-1 text-xs font-semibold text-warning">{s.status_label}</span></td>
                                        <td className="px-4 py-3 text-right"><Link href={`/finance/surveys/${s.id}`} className="font-medium text-info hover:underline">Proses</Link></td>
                                    </tr>
                                ))}
                                {surveys.data.length === 0 && <tr><td colSpan="7" className="px-4 py-12 text-center text-text-muted">Tidak ada survey yang menunggu Finance.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                    <div className="border-t border-border p-4"><Pagination links={surveys.links} /></div>
                </div>
            </div>
        </AppLayout>
    );
}
