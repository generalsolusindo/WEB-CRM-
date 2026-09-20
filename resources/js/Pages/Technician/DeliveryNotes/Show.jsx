import { Head } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader } from '../../../Components/ui';
import { feedback } from '../../../Components/feedback';

export default function Show({ deliveryNote: dn, canReceive }) {
    function confirmReceive() {
        feedback.act({
            url: `/technician/delivery-notes/${dn.id}/receive`,
            confirm: { tone: 'question', title: 'Barang sudah diterima?', text: 'Pastikan semua barang di Delivery Note ini sudah sampai di lokasi.', confirmLabel: 'Ya, sudah diterima' },
            success: { title: 'Penerimaan tercatat', style: 'popup' },
        });
    }

    return (
        <AppLayout>
            <Head title={dn.number} />
            <div className="mx-auto min-w-0 break-words max-w-2xl space-y-5">
                <PageHeader
                    title={(
                        <span className="flex flex-wrap items-center gap-3">
                            {dn.number}
                            <span className={`badge ${dn.status === 'received' ? 'badge-success' : 'badge-warning'}`}>{dn.status === 'received' ? 'Diterima' : 'Menunggu Konfirmasi'}</span>
                        </span>
                    )}
                    subtitle={`${dn.sales_order.number} · ${dn.sales_order.customer}`}
                    back={{ href: '/technician/tasks', label: 'Tugas Saya' }}
                />

                <section className="card p-4 sm:p-6">
                    <div className="text-[11px] font-bold uppercase tracking-wider text-text-faint">Alamat Pengiriman</div>
                    <div className="mt-1 whitespace-pre-line text-sm text-text">{dn.delivery_address}</div>
                </section>

                <section className="card overflow-hidden p-0">
                    <div className="border-b border-border p-5"><h2 className="font-semibold text-text">Barang Dikirim</h2></div>
                    <table className="w-full table-fixed text-left text-sm">
                        <thead className="bg-surface-2 text-[11px] font-bold uppercase tracking-wider text-text-faint"><tr><th className="px-4 py-3">Item</th><th className="px-4 py-3 text-right">Qty Dikirim</th></tr></thead>
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
                        <button onClick={confirmReceive} className="btn btn-primary">Konfirmasi Diterima</button>
                    </div>
                ) : (
                    <p className="text-sm text-text-muted">Hanya leader tim yang bisa konfirmasi penerimaan.</p>
                )}
            </div>
        </AppLayout>
    );
}
