import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';

export default function Show({ deliveryNote: dn, canReceive }) {
    function confirmReceive() {
        if (confirm('Konfirmasi bahwa barang di Delivery Note ini sudah diterima di lokasi?')) {
            router.post(`/technician/delivery-notes/${dn.id}/receive`);
        }
    }

    return (
        <AppLayout>
            <Head title={dn.number} />
            <div className="mx-auto max-w-2xl space-y-5">
                <div>
                    <Link href="/technician/tasks" className="text-sm text-info">← Tugas Saya</Link>
                    <div className="mt-2 flex items-center gap-3">
                        <h1 className="text-2xl font-bold text-text">{dn.number}</h1>
                        <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${dn.status === 'received' ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning'}`}>
                            {dn.status === 'received' ? 'Diterima' : 'Menunggu Konfirmasi'}
                        </span>
                    </div>
                    <p className="text-sm text-text-muted">{dn.sales_order.number} · {dn.sales_order.customer}</p>
                </div>

                <section className="rounded-xl border border-border bg-surface p-6 shadow-sm">
                    <div className="text-xs font-semibold uppercase tracking-wide text-text-muted">Alamat Pengiriman</div>
                    <div className="mt-1 whitespace-pre-line text-sm text-text">{dn.delivery_address}</div>
                </section>

                <section className="overflow-hidden rounded-xl border border-border bg-surface shadow-sm">
                    <div className="border-b border-border p-5"><h2 className="font-semibold text-text">Barang Dikirim</h2></div>
                    <table className="w-full text-left text-sm">
                        <thead className="bg-bg text-text-muted"><tr><th className="px-4 py-3">Item</th><th className="px-4 py-3 text-right">Qty Dikirim</th></tr></thead>
                        <tbody className="divide-y divide-border">
                            {dn.lines.map((l, i) => (
                                <tr key={i}>
                                    <td className="px-4 py-3 text-text">{l.item_name}</td>
                                    <td className="px-4 py-3 text-right text-text">{l.qty_delivered} {l.unit}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>

                {dn.status === 'received' ? (
                    <section className="rounded-lg border border-success/20 bg-success/10 px-4 py-3 text-sm text-success">
                        Sudah dikonfirmasi diterima oleh {dn.received_by} · {new Date(dn.received_at).toLocaleString('id-ID')}
                    </section>
                ) : canReceive ? (
                    <div className="flex justify-end">
                        <button onClick={confirmReceive} className="rounded-lg bg-navy px-5 py-2 text-sm font-semibold text-white">Konfirmasi Diterima</button>
                    </div>
                ) : (
                    <p className="text-sm text-text-muted">Hanya leader tim yang bisa konfirmasi penerimaan.</p>
                )}
            </div>
        </AppLayout>
    );
}
