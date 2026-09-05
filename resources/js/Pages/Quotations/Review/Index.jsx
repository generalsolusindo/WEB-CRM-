import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import Pagination from '../../../Components/Pagination';

export default function Index({ quotations, role }) {
    const base = role === 'management' ? '/management/quotations' : '/project-manager/quotations';
    const title = role === 'management' ? 'Verifikasi Quotation' : 'Verifikasi Quotation';

    return (
        <AppLayout>
            <Head title={title} />
            <div className="mx-auto max-w-5xl space-y-5">
                <div>
                    <h1 className="text-2xl font-bold text-text">{title}</h1>
                    <p className="text-sm text-text-muted">
                        {role === 'management'
                            ? 'Quotation yang sudah disetujui Project Manager, menunggu verifikasi akhir Anda.'
                            : 'Quotation dari opportunity yang didelegasikan kepada Anda, menunggu verifikasi Anda sebelum lanjut ke Manager.'}
                    </p>
                </div>

                <div className="overflow-hidden rounded-xl border border-border bg-surface shadow-sm">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-bg text-text-muted">
                            <tr>
                                <th className="px-4 py-3">Nomor</th>
                                <th className="px-4 py-3">Customer</th>
                                <th className="px-4 py-3">Sales</th>
                                <th className="px-4 py-3">Status</th>
                                <th className="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {quotations.data.map((q) => (
                                <tr key={q.id} className="hover:bg-bg/70">
                                    <td className="px-4 py-3 font-medium text-text">{q.number}</td>
                                    <td className="px-4 py-3 text-text-muted">{q.company || q.customer || '—'}</td>
                                    <td className="px-4 py-3 text-text-muted">{q.sales}</td>
                                    <td className="px-4 py-3">
                                        <ReviewBadge status={role === 'management' ? q.manager_review_status : q.pm_review_status} />
                                    </td>
                                    <td className="px-4 py-3 text-right"><Link href={`${base}/${q.id}`} className="font-medium text-info hover:underline">Lihat</Link></td>
                                </tr>
                            ))}
                            {quotations.data.length === 0 && (
                                <tr><td colSpan={5} className="px-4 py-12 text-center text-text-muted">Tidak ada quotation yang menunggu verifikasi Anda.</td></tr>
                            )}
                        </tbody>
                    </table>
                    <div className="border-t border-border p-4"><Pagination links={quotations.links} /></div>
                </div>
            </div>
        </AppLayout>
    );
}

function ReviewBadge({ status }) {
    if (status === 'approved') return <span className="rounded-full bg-success/10 px-2.5 py-1 text-xs font-semibold text-success">Disetujui</span>;
    if (status === 'rejected') return <span className="rounded-full bg-danger/10 px-2.5 py-1 text-xs font-semibold text-danger">Ditolak</span>;
    return <span className="rounded-full bg-warning/10 px-2.5 py-1 text-xs font-semibold text-warning">Menunggu verifikasi</span>;
}
