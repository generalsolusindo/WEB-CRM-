import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';

export default function Show({ deliveryNote: dn }) {
    const complete = dn.lines.every((l) => l.qty_balance <= 0);

    return (
        <AppLayout>
            <Head title={dn.number} />
            <div className="mx-auto max-w-3xl space-y-5">
                <div>
                    <Link href={`/operational/sales-orders/${dn.sales_order.id}/delivery-notes`} className="text-sm text-info">← Kembali</Link>
                    <div className="mt-2 flex items-center gap-3">
                        <h1 className="text-2xl font-bold text-text">{dn.number}</h1>
                        <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${dn.status === 'received' ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning'}`}>
                            {dn.status === 'received' ? 'Diterima' : 'Terkirim'}
                        </span>
                        <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${complete ? 'bg-success/10 text-success' : 'bg-info/10 text-info'}`}>
                            {complete ? 'Material Lengkap' : 'Sebagian'}
                        </span>
                    </div>
                    <p className="text-sm text-text-muted">{dn.sales_order.number} · {dn.sales_order.customer}</p>
                </div>

                <div className="flex justify-end">
                    <a href={`/operational/delivery-notes/${dn.id}/pdf`} target="_blank" rel="noreferrer" className="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-text">Lihat / Cetak PDF</a>
                </div>

                <section className="grid gap-4 rounded-xl border border-border bg-surface p-6 shadow-sm sm:grid-cols-2">
                    <Info label="Alamat Pengiriman" value={dn.delivery_address} />
                    <Info label="Nomor PO" value={dn.sales_order.po_number} />
                    <Info label="Invoice Terkait" value={dn.invoice_number} />
                    <Info label="Dibuat oleh" value={dn.created_by} />
                    <Info label="Diterima oleh (Teknisi)" value={dn.received_by ? `${dn.received_by} · ${new Date(dn.received_at).toLocaleString('id-ID')}` : 'Belum dikonfirmasi'} />
                </section>

                <section className="overflow-hidden rounded-xl border border-border bg-surface shadow-sm">
                    <div className="border-b border-border p-5"><h2 className="font-semibold text-text">Barang</h2></div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-bg text-text-muted"><tr><th className="px-4 py-3">Item</th><th className="px-4 py-3">Unit</th><th className="px-4 py-3 text-right">Ordered</th><th className="px-4 py-3 text-right">Previous Balance</th><th className="px-4 py-3 text-right">Delivered</th><th className="px-4 py-3 text-right">Balance</th></tr></thead>
                            <tbody className="divide-y divide-border">
                                {dn.lines.map((l, i) => (
                                    <tr key={i}>
                                        <td className="px-4 py-3 text-text">{l.item_name}</td>
                                        <td className="px-4 py-3 text-text-muted">{l.unit}</td>
                                        <td className="px-4 py-3 text-right text-text-muted">{l.qty_ordered}</td>
                                        <td className="px-4 py-3 text-right text-text-muted">{l.qty_previous_balance}</td>
                                        <td className="px-4 py-3 text-right font-medium text-text">{l.qty_delivered}</td>
                                        <td className={`px-4 py-3 text-right ${l.qty_balance > 0 ? 'text-warning' : 'text-success'}`}>{l.qty_balance}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}

function Info({ label, value }) {
    return <div><div className="text-xs font-semibold uppercase tracking-wide text-text-muted">{label}</div><div className="mt-1 whitespace-pre-line text-sm text-text">{value || '—'}</div></div>;
}
