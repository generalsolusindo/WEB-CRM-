import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, Card, CardHeader, Field, Input, Select, Textarea, FormActions } from '../../../Components/ui';

function Alert({ text }) {
    return <div className="rounded-xl border border-danger/25 bg-danger-soft px-4 py-3 text-sm font-medium text-danger">{text}</div>;
}

export default function Create({ salesOrder, defaultAddress, lines }) {
    const { data, setData, post, processing, errors, transform } = useForm({
        delivery_method: 'ekspedisi',
        delivery_address: defaultAddress ?? '',
        shipper_name: '',
        tracking_number: '',
        approved_by_name: '',
        dispatch_proof: null,
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
        post(`/operational/sales-orders/${salesOrder.id}/delivery-notes`, { forceFormData: true });
    }

    const isEkspedisi = data.delivery_method === 'ekspedisi';

    return (
        <AppLayout>
            <Head title="Buat Delivery Note" />
            <div className="mx-auto max-w-3xl space-y-5">
                <PageHeader
                    title="Buat Delivery Note"
                    subtitle={`${salesOrder.number} · ${salesOrder.customer}`}
                    back={{ href: `/operational/sales-orders/${salesOrder.id}/delivery-notes`, label: 'Kembali' }}
                />

                {errors.sales_order && <Alert text={errors.sales_order} />}
                {errors.lines && <Alert text={errors.lines} />}

                <form onSubmit={submit} className="space-y-5">
                    <Card className="space-y-4">
                        <Field label="Metode Pengiriman" required error={errors.delivery_method}>
                            <Select value={data.delivery_method} onChange={(e) => setData('delivery_method', e.target.value)}>
                                <option value="ekspedisi">Ekspedisi</option>
                                <option value="sendiri">Diantar Sendiri</option>
                            </Select>
                        </Field>
                        <Field label="Alamat Pengiriman" required hint="Default dari alamat customer, bisa diubah sesuai lokasi pengiriman." error={errors.delivery_address}>
                            <Textarea rows={3} value={data.delivery_address} onChange={(e) => setData('delivery_address', e.target.value)} />
                        </Field>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label={isEkspedisi ? 'Nama Ekspedisi (opsional)' : 'Nama Pengantar (opsional)'} error={errors.shipper_name}>
                                <Input value={data.shipper_name} onChange={(e) => setData('shipper_name', e.target.value)} placeholder={isEkspedisi ? 'contoh: JNE, J&T' : 'nama yang mengantar'} />
                            </Field>
                            {isEkspedisi && (
                                <Field label="Nomor Resi (opsional)" error={errors.tracking_number}>
                                    <Input value={data.tracking_number} onChange={(e) => setData('tracking_number', e.target.value)} placeholder="nomor resi" />
                                </Field>
                            )}
                        </div>
                        <Field label="Approved by (opsional)" error={errors.approved_by_name}>
                            <Input value={data.approved_by_name} onChange={(e) => setData('approved_by_name', e.target.value)} placeholder="nama yang menyetujui" />
                        </Field>
                        <Field
                            label={isEkspedisi ? 'Bukti Serah ke Kurir' : 'Bukti Barang Dibawa'}
                            required
                            error={errors.dispatch_proof}
                            hint="Wajib — foto resi/tanda terima saat barang diserahkan ke kurir, atau foto barang saat dibawa kalau diantar sendiri."
                        >
                            <input
                                type="file" accept=".jpg,.jpeg,.png"
                                onChange={(e) => setData('dispatch_proof', e.target.files[0] ?? null)}
                                className="block w-full text-sm text-text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-primary-soft file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-primary-strong"
                            />
                        </Field>
                    </Card>

                    <Card padded={false}>
                        <CardHeader title="Barang yang Dikirim" subtitle="Hanya baris material yang masih punya sisa belum terkirim. Centang & isi qty untuk yang mau dikirim sekarang." />
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b border-border bg-surface-2 text-[11px] font-bold uppercase tracking-wider text-text-faint">
                                        <th className="w-10 px-3 py-3"></th><th className="px-3 py-3">Item</th><th className="px-3 py-3">Unit</th><th className="px-3 py-3 text-right">Sisa Belum Terkirim</th><th className="px-3 py-3 text-right">Qty Dikirim</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {data.lines.map((line, i) => (
                                        <tr key={line.sales_order_line_id}>
                                            <td className="px-3 py-3"><input type="checkbox" checked={line.checked} onChange={(e) => setLine(i, { checked: e.target.checked })} className="accent-navy" /></td>
                                            <td className="px-3 py-3 font-medium text-text">{line.item_name}</td>
                                            <td className="px-3 py-3 text-text-muted">{line.unit}</td>
                                            <td className="px-3 py-3 text-right tabular-nums text-text-muted">{line.qty_remaining}</td>
                                            <td className="px-3 py-3 text-right">
                                                <input
                                                    type="number" min="0" max={line.qty_remaining} step="0.01"
                                                    disabled={!line.checked}
                                                    value={line.qty_delivered}
                                                    onChange={(e) => setLine(i, { qty_delivered: e.target.value })}
                                                    className="w-28 rounded-lg border border-border-strong bg-surface px-2 py-1.5 text-right text-sm outline-none focus:border-primary disabled:bg-bg"
                                                />
                                            </td>
                                        </tr>
                                    ))}
                                    {data.lines.length === 0 && <tr><td colSpan="5" className="px-3 py-8 text-center text-text-muted">Semua material sudah terkirim lengkap.</td></tr>}
                                </tbody>
                            </table>
                        </div>
                    </Card>

                    <FormActions
                        cancelHref={`/operational/sales-orders/${salesOrder.id}/delivery-notes`}
                        submitLabel="Buat Delivery Note"
                        processing={processing}
                    />
                </form>
            </div>
        </AppLayout>
    );
}
