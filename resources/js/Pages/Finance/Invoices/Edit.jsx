import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, Card, CardHeader, Field, Input, Textarea, FormActions, CurrencyInput } from '../../../Components/ui';

function money(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(v || 0));
}
function r2(n) { return Math.round(n * 100) / 100; }

function Alert({ text }) {
    return <div className="rounded-xl border border-danger/25 bg-danger-soft px-4 py-3 text-sm font-medium text-danger">{text}</div>;
}

export default function Edit({ invoice, linesEditable }) {
    const { data, setData, put, processing, errors } = useForm({
        due_date: invoice.due_date ?? '',
        notes: invoice.notes ?? '',
        ...(linesEditable ? {
            lines: invoice.lines.map((l) => ({
                sales_order_line_id: l.sales_order_line_id,
                item_name: l.item_name,
                category: l.category,
                qty: l.qty,
                unit_price: l.unit_price,
                discount_amount: l.discount_amount,
                tax_rate: l.tax_rate,
            })),
        } : {}),
    });

    function submit(e) {
        e.preventDefault();
        put(`/finance/invoices/${invoice.id}`);
    }

    function updateLine(index, field, value) {
        setData('lines', data.lines.map((l, i) => (i === index ? { ...l, [field]: value } : l)));
    }

    function removeLine(index) {
        setData('lines', data.lines.filter((_, i) => i !== index));
    }

    function addLine() {
        setData('lines', [...data.lines, {
            sales_order_line_id: null, item_name: '', category: 'material', qty: 1, unit_price: 0, discount_amount: 0, tax_rate: 0,
        }]);
    }

    const sourceLines = linesEditable ? data.lines : invoice.lines;
    const computed = sourceLines.map((l) => {
        const qty = Number(l.qty) || 0;
        const unitPrice = Number(l.unit_price) || 0;
        const discount = Number(l.discount_amount) || 0;
        const rate = Number(l.tax_rate) || 0;
        const subtotal = r2(qty * unitPrice - discount);
        const tax = r2(subtotal * rate / 100);
        return { ...l, subtotal, tax, discount };
    });
    const grossTotal = r2(computed.reduce((s, l) => s + (Number(l.qty) || 0) * (Number(l.unit_price) || 0), 0));
    const discountTotal = r2(computed.reduce((s, l) => s + l.discount, 0));
    const subtotalTotal = r2(computed.reduce((s, l) => s + l.subtotal, 0));
    const taxTotal = r2(computed.reduce((s, l) => s + l.tax, 0));
    const grandTotal = r2(subtotalTotal + taxTotal);
    const serviceDpp = r2(computed.filter((l) => l.category === 'service').reduce((s, l) => s + l.subtotal, 0));
    const pph23Preview = invoice.pph23_enabled ? Math.round(serviceDpp * Number(invoice.pph23_rate || 0) / 100) : 0;

    return (
        <AppLayout>
            <Head title={`Edit ${invoice.number}`} />
            <div className="mx-auto max-w-5xl space-y-5">
                <PageHeader
                    title={`Edit Invoice — ${invoice.number}`}
                    subtitle={`${invoice.sales_order?.number ?? ''} · ${invoice.sales_order?.contact?.name ?? '—'}`}
                    back={{ href: `/finance/invoices/${invoice.id}`, label: 'Kembali ke Invoice' }}
                />

                {linesEditable ? (
                    <div className="rounded-xl border border-warning/25 bg-warning-soft px-4 py-3 text-sm font-medium text-warning">
                        Menyimpan perubahan akan mengembalikan status invoice ke Draft — perlu dikirim ulang ke customer. Phase invoice ({invoice.invoice_phase}) tidak bisa diubah di sini.
                    </div>
                ) : (
                    <div className="rounded-xl border border-border bg-surface-2 px-4 py-3 text-sm font-medium text-text-muted">
                        Invoice ini sudah ada pembayaran tercatat, jadi rincian baris & nominal terkunci supaya tidak mismatch dengan uang yang sudah masuk. Anda tetap bisa mengubah jatuh tempo dan catatan.
                    </div>
                )}
                {linesEditable && invoice.whatsapp_sent_at && (
                    <div className="rounded-xl border border-danger/25 bg-danger-soft px-4 py-3 text-sm font-medium text-danger">
                        Invoice ini sudah pernah dikirim via WhatsApp ({new Date(invoice.whatsapp_sent_at).toLocaleString('id-ID')}) dengan total tagihan yang lama tertulis di teks pesan. Setelah menyimpan perubahan ini, <strong>kirim ulang via WhatsApp</strong> supaya customer tidak pegang total yang sudah tidak sesuai.
                    </div>
                )}
                {errors.invoice && <Alert text={errors.invoice} />}

                <form onSubmit={submit} className="space-y-5">
                    <Card>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Jatuh Tempo" error={errors.due_date}>
                                <Input type="date" value={data.due_date} onChange={(e) => setData('due_date', e.target.value)} />
                            </Field>
                            <Field label={<>Catatan <span className="font-normal text-text-muted">(opsional)</span></>} error={errors.notes}>
                                <Textarea rows={2} value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                            </Field>
                        </div>
                    </Card>

                    <Card padded={false}>
                        <CardHeader title="Rincian Baris" />
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[820px] text-left text-sm">
                                <thead>
                                    <tr className="border-b border-border bg-surface-2 text-[11px] font-bold uppercase tracking-wider text-text-faint">
                                        <th className="px-4 py-3">Item</th>
                                        <th className="px-4 py-3">Kategori</th>
                                        <th className="px-4 py-3">Qty</th>
                                        <th className="px-4 py-3 text-right">Harga Satuan</th>
                                        <th className="px-4 py-3 text-right">Diskon</th>
                                        <th className="px-4 py-3">Pajak (%)</th>
                                        <th className="px-4 py-3 text-right">DPP</th>
                                        <th className="px-4 py-3"></th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {computed.map((l, i) => linesEditable ? (
                                        <tr key={i}>
                                            <td className="px-4 py-2">
                                                <input value={l.item_name} onChange={(e) => updateLine(i, 'item_name', e.target.value)} className="w-full min-w-[160px] rounded-lg border border-border px-2 py-1.5 outline-none focus:border-navy" />
                                                {errors[`lines.${i}.item_name`] && <span className="text-xs text-danger">{errors[`lines.${i}.item_name`]}</span>}
                                            </td>
                                            <td className="px-4 py-2">
                                                <select value={l.category} onChange={(e) => updateLine(i, 'category', e.target.value)} className="rounded-lg border border-border px-2 py-1.5 outline-none focus:border-navy">
                                                    <option value="material">Material</option>
                                                    <option value="service">Jasa</option>
                                                    <option value="reimburse">Biaya Reimburse</option>
                                                </select>
                                            </td>
                                            <td className="px-4 py-2">
                                                <input type="number" min="0.01" step="0.01" value={l.qty} onChange={(e) => updateLine(i, 'qty', e.target.value)} className="w-20 rounded-lg border border-border px-2 py-1.5 outline-none focus:border-navy" />
                                            </td>
                                            <td className="px-4 py-2 text-right">
                                                <CurrencyInput value={l.unit_price} onChange={(e) => updateLine(i, 'unit_price', e.target.value)} className="w-32 rounded-lg border border-border px-2 py-1.5 text-right outline-none focus:border-navy" />
                                            </td>
                                            <td className="px-4 py-2 text-right">
                                                <CurrencyInput value={l.discount_amount} onChange={(e) => updateLine(i, 'discount_amount', e.target.value)} className="w-28 rounded-lg border border-border px-2 py-1.5 text-right outline-none focus:border-navy" />
                                            </td>
                                            <td className="px-4 py-2">
                                                <input type="number" min="0" max="100" step="0.01" value={l.tax_rate} onChange={(e) => updateLine(i, 'tax_rate', e.target.value)} className="w-20 rounded-lg border border-border px-2 py-1.5 outline-none focus:border-navy" />
                                            </td>
                                            <td className="px-4 py-2 text-right font-medium tabular-nums text-text">{money(l.subtotal)}</td>
                                            <td className="px-4 py-2 text-right">
                                                {data.lines.length > 1 && (
                                                    <button type="button" onClick={() => removeLine(i)} className="rounded-lg border border-danger/30 px-2 py-1 text-xs font-semibold text-danger">Hapus</button>
                                                )}
                                            </td>
                                        </tr>
                                    ) : (
                                        <tr key={i} className="text-text-muted">
                                            <td className="px-4 py-2 text-text">{l.item_name}</td>
                                            <td className="px-4 py-2 capitalize">{l.category}</td>
                                            <td className="px-4 py-2 tabular-nums">{Number(l.qty).toFixed(2)}</td>
                                            <td className="px-4 py-2 text-right tabular-nums">{money(l.unit_price)}</td>
                                            <td className="px-4 py-2 text-right tabular-nums">{money(l.discount_amount)}</td>
                                            <td className="px-4 py-2 tabular-nums">{Number(l.tax_rate).toFixed(2)}</td>
                                            <td className="px-4 py-2 text-right font-medium tabular-nums text-text">{money(l.subtotal)}</td>
                                            <td className="px-4 py-2"></td>
                                        </tr>
                                    ))}
                                </tbody>
                                {linesEditable && (
                                    <tfoot>
                                        <tr>
                                            <td colSpan="8" className="px-4 py-2">
                                                <button type="button" onClick={addLine} className="rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-text-muted hover:text-text">+ Tambah Item</button>
                                            </td>
                                        </tr>
                                    </tfoot>
                                )}
                            </table>
                        </div>
                        {/* Ringkasan total di luar tabel supaya selalu terlihat penuh, tidak ikut
                            ter-scroll bersama tabel rincian baris yang lebar di layar sempit. */}
                        <div className="space-y-1.5 border-t border-border bg-surface-2 px-4 py-4 text-sm text-text">
                            <div className="flex items-center justify-between text-text-muted"><span>Subtotal Bruto</span><span className="font-medium tabular-nums text-text">{money(grossTotal)}</span></div>
                            <div className="flex items-center justify-between text-text-muted"><span>Total Diskon</span><span className="font-medium tabular-nums text-danger">{discountTotal > 0 ? `− ${money(discountTotal)}` : money(0)}</span></div>
                            <div className="flex items-center justify-between text-text-muted"><span>DPP</span><span className="font-medium tabular-nums text-text">{money(subtotalTotal)}</span></div>
                            <div className="flex items-center justify-between text-text-muted"><span>Total PPN</span><span className="font-medium tabular-nums text-text">{money(taxTotal)}</span></div>
                            <div className="flex items-center justify-between border-t border-border pt-2 text-base font-semibold text-text"><span>Total Tagihan</span><span className="text-lg font-bold tabular-nums">{money(grandTotal)}</span></div>
                            {invoice.pph23_enabled && (
                                <div className="flex items-center justify-between text-xs text-text-muted"><span>PPh 23 ({Number(invoice.pph23_rate)}%) — dihitung ulang otomatis dari baris jasa</span><span className="font-medium tabular-nums text-warning">− {money(pph23Preview)}</span></div>
                            )}
                        </div>
                    </Card>

                    <FormActions
                        cancelHref={`/finance/invoices/${invoice.id}`}
                        submitLabel="Simpan Perubahan"
                        processing={processing}
                        disabled={linesEditable && data.lines.length === 0}
                    />
                </form>
            </div>
        </AppLayout>
    );
}
