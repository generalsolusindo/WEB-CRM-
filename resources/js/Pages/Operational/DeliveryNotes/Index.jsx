import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';

export default function Index({ salesOrder, deliveryNotes, canCreate }) {
    return (
        <AppLayout>
            <Head title={`Delivery Note — ${salesOrder.number}`} />
            <div className="mx-auto max-w-4xl space-y-5">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold text-text">Delivery Note</h1>
                        <p className="text-sm text-text-muted">{salesOrder.number} · {salesOrder.customer}</p>
                    </div>
                    {canCreate && (
                        <Link href={`/operational/sales-orders/${salesOrder.id}/delivery-notes/create`} className="rounded-lg bg-navy px-4 py-2 text-sm font-semibold text-white">
                            Buat Delivery Note
                        </Link>
                    )}
                </div>

                <div className="overflow-hidden rounded-xl border border-border bg-surface shadow-sm">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-bg text-text-muted"><tr><th className="px-4 py-3">Nomor</th><th className="px-4 py-3">Dibuat oleh</th><th className="px-4 py-3">Status</th><th className="px-4 py-3 text-right">Aksi</th></tr></thead>
                        <tbody className="divide-y divide-border">
                            {deliveryNotes.map((dn) => (
                                <tr key={dn.id} className="hover:bg-bg/70">
                                    <td className="px-4 py-3 font-medium text-text">{dn.number}</td>
                                    <td className="px-4 py-3 text-text-muted">{dn.created_by}</td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${dn.status === 'received' ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning'}`}>
                                            {dn.status === 'received' ? 'Diterima' : 'Terkirim'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-right"><Link href={`/operational/delivery-notes/${dn.id}`} className="font-medium text-info hover:underline">Lihat</Link></td>
                                </tr>
                            ))}
                            {deliveryNotes.length === 0 && <tr><td colSpan="4" className="px-4 py-12 text-center text-text-muted">Belum ada Delivery Note untuk Sales Order ini.</td></tr>}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
