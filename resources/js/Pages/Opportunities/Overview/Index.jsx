import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import Pagination from '../../../Components/Pagination';

export default function Index({ opportunities, role }) {
    const base = role === 'management' ? '/management/opportunities' : '/project-manager/opportunities';

    return (
        <AppLayout>
            <Head title={role === 'management' ? 'Opportunity' : 'Opportunity Saya'} />
            <div className="mx-auto max-w-5xl space-y-5">
                <div>
                    <h1 className="text-2xl font-bold text-text">{role === 'management' ? 'Opportunity' : 'Opportunity Saya'}</h1>
                    <p className="text-sm text-text-muted">
                        {role === 'management'
                            ? 'Monitoring semua opportunity — tunjuk Project Manager untuk tiap opportunity di sini.'
                            : 'Opportunity yang didelegasikan Manager kepada Anda.'}
                    </p>
                </div>

                <div className="overflow-hidden rounded-xl border border-border bg-surface shadow-sm">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-bg text-text-muted">
                            <tr>
                                <th className="px-4 py-3">Kode</th>
                                <th className="px-4 py-3">Customer</th>
                                <th className="px-4 py-3">Sales</th>
                                <th className="px-4 py-3">Tahap</th>
                                {role === 'management' && <th className="px-4 py-3">Didelegasikan ke</th>}
                                <th className="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {opportunities.data.map((o) => (
                                <tr key={o.id} className="hover:bg-bg/70">
                                    <td className="px-4 py-3 font-medium text-text">{o.code}</td>
                                    <td className="px-4 py-3 text-text-muted">{o.company || o.customer || '—'}</td>
                                    <td className="px-4 py-3 text-text-muted">{o.sales}</td>
                                    <td className="px-4 py-3"><span className="rounded-full bg-info/10 px-2.5 py-1 text-xs font-semibold text-info">{o.stage_label}</span></td>
                                    {role === 'management' && (
                                        <td className="px-4 py-3">
                                            {o.delegated_to
                                                ? <span className="text-text-muted">{o.delegated_to}</span>
                                                : <span className="rounded-full bg-warning/10 px-2 py-0.5 text-xs font-semibold text-warning">Belum ditunjuk</span>}
                                        </td>
                                    )}
                                    <td className="px-4 py-3 text-right"><Link href={`${base}/${o.id}`} className="font-medium text-info hover:underline">Lihat</Link></td>
                                </tr>
                            ))}
                            {opportunities.data.length === 0 && (
                                <tr><td colSpan={role === 'management' ? 6 : 5} className="px-4 py-12 text-center text-text-muted">
                                    {role === 'management' ? 'Belum ada opportunity.' : 'Belum ada opportunity yang didelegasikan kepada Anda.'}
                                </td></tr>
                            )}
                        </tbody>
                    </table>
                    <div className="border-t border-border p-4"><Pagination links={opportunities.links} /></div>
                </div>
            </div>
        </AppLayout>
    );
}
