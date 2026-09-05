import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import Pagination from '../../../Components/Pagination';

export default function Index({ sows }) {
    return (
        <AppLayout>
            <Head title="SOW Saya" />
            <div className="mx-auto max-w-4xl space-y-5">
                <div>
                    <h1 className="text-2xl font-bold text-text">SOW Saya</h1>
                    <p className="text-sm text-text-muted">Scope of Work yang ditugaskan kepada Anda — tanda tangani sebelum berangkat ke lapangan.</p>
                </div>

                <div className="overflow-hidden rounded-xl border border-border bg-surface shadow-sm">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-bg text-text-muted">
                            <tr><th className="px-4 py-3">Nomor</th><th className="px-4 py-3">Nama Proyek</th><th className="px-4 py-3">Status</th><th className="px-4 py-3 text-right">Aksi</th></tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {sows.data.map((s) => (
                                <tr key={s.id} className="hover:bg-bg/70">
                                    <td className="px-4 py-3 font-medium text-text">{s.number}</td>
                                    <td className="px-4 py-3 text-text-muted">{s.project_name}</td>
                                    <td className="px-4 py-3"><span className="rounded-full bg-info/10 px-2.5 py-1 text-xs font-semibold text-info">{s.status_label}</span></td>
                                    <td className="px-4 py-3 text-right"><Link href={`/technician/sows/${s.id}`} className="font-medium text-info hover:underline">Lihat</Link></td>
                                </tr>
                            ))}
                            {sows.data.length === 0 && <tr><td colSpan={4} className="px-4 py-12 text-center text-text-muted">Belum ada SOW untuk Anda.</td></tr>}
                        </tbody>
                    </table>
                    <div className="border-t border-border p-4"><Pagination links={sows.links} /></div>
                </div>
            </div>
        </AppLayout>
    );
}
