import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';

export default function Create({ salesOrder, defaultAddress, lines }) {
    const { data, setData, post, processing, errors, transform } = useForm({
        delivery_address: defaultAddress ?? '',
        shipper_name: '',
        approved_by_name: '',
        lines: lines.map((l) => ({ ...l, qty_delivered: l.qty_remaining, checked: true })),
    });

    transform((payload) => ({
        ...payload,
        lines: payload.lines
            .filter((l) => l.checked)
            .map((l) => ({ sales_order_line_id: l.sales_order_line_id, qty_delivered: l.qty_delivered })),
    }));

    function setLine(i, patch) {
        setData('lines', data.lines.map((l, idx) => (idx === i ? { ...l, ...patch } : l)));
    }

    function submit(e) {
        e.preventDefault();
        post(`/operational/sales-orders/${salesOrder.id}/delivery-notes`);
    }

    return (
        <AppLayout>
            <Head title="Buat Delivery Note" />
            <div className="mx-auto max-w-3xl space-y-5">
                <div>
                    <Link href={`/operational/sales-orders/${salesOrder.id}/delivery-notes`} className="text-sm text-info">← Kembali</Link>
                    <h1 className="mt-2 text-2xl font-bold text-text">Buat Delivery Note</h1>
                    <p className="text-sm text-text-muted">{salesOrder.number} · {salesOrder.customer}</p>
                </div>

                {errors.sales_order && <div className="rounded-lg border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger">{errors.sales_order}</div>}
                {errors.lines && <div className="rounded-lg border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger">{errors.lines}</div>}

                <form onSubmit={submit} className="space-y-5">
                    <section className="space-y-4 rounded-xl border border-border bg-surface p-6 shadow-sm">
                        <label className="block text-sm font-medium text-text">Alamat Pengiriman *
                            <textarea rows="3" value={data.delivery_address} onChange={(e) => setData('delivery_address', e.target.value)} className="input" />
                            <span className="mt-1 block text-xs text-text-muted">Default dari alamat customer, bisa diubah sesuai lokasi pengiriman.</span>
                            {errors.delivery_address && <span className="mt-1 block text-xs text-danger">{errors.delivery_address}</span>}
                        </label>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <label className="block text-sm font-medium text-text">Shipper (opsional)
                                <input value={data.shipper_name} onChange={(e) => setData('shipper_name', e.target.value)} className="input" placeholder="nama pengirim" />
                            </label>
                            <label className="block text-sm font-medium text-text">Approved by (opsional)
                                <input value={data.approved_by_name} onChange={(e) => setData('approved_by_name', e.target.value)} className="input" placeholder="nama yang menyetujui" />
                            </label>
                        </div>
                    </section>

                    <section className="overflow-hidden rounded-xl border border-border bg-surface shadow-sm">
                        <div className="border-b border-border p-5">
                            <h2 className="font-semibold text-text">Barang yang Dikirim</h2>
                            <p className="text-sm text-text-muted">Hanya baris material yang masih punya sisa belum terkirim. Centang & isi qty untuk yang mau dikirim sekarang.</p>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-bg text-text-muted"><tr><th className="px-3 py-3 w-10"></th><th className="px-3 py-3">Item</th><th className="px-3 py-3">Unit</th><th className="px-3 py-3 text-right">Sisa Belum Terkirim</th><th className="px-3 py-3 text-right">Qty Dikirim</th></tr></thead>
                                <tbody className="divide-y divide-border">
                                    {data.lines.map((line, i) => (
                                        <tr key={line.sales_order_line_id}>
                                            <td className="px-3 py-3"><input type="checkbox" checked={line.checked} onChange={(e) => setLine(i, { checked: e.target.checked })} /></td>
                                            <td className="px-3 py-3 text-text">{line.item_name}</td>
                                            <td className="px-3 py-3 text-text-muted">{line.unit}</td>
                                            <td className="px-3 py-3 text-right text-text-muted">{line.qty_remaining}</td>
                                            <td className="px-3 py-3 text-right">
                                                <input
                                                    type="number" min="0" max={line.qty_remaining} step="0.01"
                                                    disabled={!line.checked}
                                                    value={line.qty_delivered}
                                                    onChange={(e) => setLine(i, { qty_delivered: e.target.value })}
                                                    className="w-28 rounded-lg border border-border px-2 py-1.5 text-right text-sm disabled:bg-bg"
                                                />
                                            </td>
                                        </tr>
                                    ))}
                                    {data.lines.length === 0 && <tr><td colSpan="5" className="px-3 py-8 text-center text-text-muted">Semua material sudah terkirim lengkap.</td></tr>}
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <div className="flex justify-end gap-3">
                        <Link href={`/operational/sales-orders/${salesOrder.id}/delivery-notes`} className="rounded-lg border border-border px-4 py-2 text-sm">Batal</Link>
                        <button disabled={processing} className="rounded-lg bg-navy px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">{processing ? 'Menyimpan...' : 'Buat Delivery Note'}</button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
