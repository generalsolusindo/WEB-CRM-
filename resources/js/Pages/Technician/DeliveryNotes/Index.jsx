import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';

export default function Index({ project, deliveryNotes }) {
    return (
        <AppLayout>
            <Head title={`Delivery Note — ${project.number}`} />
            <div className="mx-auto max-w-2xl space-y-5">
                <div>
                    <Link href="/technician/tasks" className="text-sm text-info">← Tugas Saya</Link>
                    <h1 className="mt-2 text-2xl font-bold text-text">Delivery Note</h1>
                    <p className="text-sm text-text-muted">{project.number} · {project.customer}</p>
                </div>

                <div className="overflow-hidden rounded-xl border border-border bg-surface shadow-sm">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-bg text-text-muted"><tr><th className="px-4 py-3">Nomor</th><th className="px-4 py-3">Status</th><th className="px-4 py-3 text-right">Aksi</th></tr></thead>
                        <tbody className="divide-y divide-border">
                            {deliveryNotes.map((dn) => (
                                <tr key={dn.id} className="hover:bg-bg/70">
                                    <td className="px-4 py-3 font-medium text-text">{dn.number}</td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${dn.status === 'received' ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning'}`}>
                                            {dn.status === 'received' ? 'Diterima' : 'Menunggu Konfirmasi'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-right"><Link href={`/technician/delivery-notes/${dn.id}`} className="font-medium text-info hover:underline">Lihat</Link></td>
                                </tr>
                            ))}
                            {deliveryNotes.length === 0 && <tr><td colSpan="3" className="px-4 py-12 text-center text-text-muted">Belum ada Delivery Note untuk project ini.</td></tr>}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
