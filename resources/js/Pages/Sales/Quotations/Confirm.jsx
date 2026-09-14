import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { Totals } from './Show';
import CategoryBadge from '../../../Components/CategoryBadge';
import { pickFile } from '../../../utils/fileValidation';
import { PageHeader, Card, CardHeader, Field, Input, FormActions } from '../../../Components/ui';

function money(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(v || 0));
}

export default function Confirm({ quotation, orderTypes, totals }) {
    const { data, setData, setError, clearErrors, post, processing, errors } = useForm({
        order_type: '', signed_quotation: null, purchase_order: null, po_number: '', po_date: '',
    });
    const selected = orderTypes.find((t) => t.value === data.order_type);
    function submit(e) { e.preventDefault(); post(`/sales/quotations/${quotation.id}/confirm`, { forceFormData: true }); }

    return (
        <AppLayout>
            <Head title="Confirm Deal" />
            <div className="mx-auto max-w-4xl space-y-5">
                <PageHeader
                    title="Confirm Deal"
                    subtitle={`QT-${String(quotation.id).padStart(6, '0')} / R${quotation.revision_number} · ${quotation.contact.name}`}
                    back={{ href: `/sales/quotations/${quotation.id}`, label: 'Kembali ke Quotation' }}
                />

                <form onSubmit={submit} className="space-y-5">
                    {errors.quotation && (
                        <div className="rounded-xl border border-danger/25 bg-danger-soft px-4 py-3 text-sm font-medium text-danger">{errors.quotation}</div>
                    )}

                    <Card padded={false}>
                        <CardHeader title="Pilih Order Type" />
                        <div className="p-5">
                            <div className="grid gap-3 sm:grid-cols-3">
                                {orderTypes.map((t) => (
                                    <label key={t.value} className={`cursor-pointer rounded-xl border p-4 transition ${data.order_type === t.value ? 'border-navy bg-navy/5 ring-1 ring-navy/20' : 'border-border hover:border-border-strong'}`}>
                                        <input type="radio" name="order_type" value={t.value} checked={data.order_type === t.value} onChange={(e) => setData('order_type', e.target.value)} className="mr-2 accent-[var(--color-navy)]" />
                                        <span className="font-semibold text-text">{t.label}</span>
                                        <div className="mt-2 text-xs text-text-muted">Payment rule: {t.payment_rule === 'full_100' ? 'Full Payment 100%' : 'Down Payment 50%'}</div>
                                    </label>
                                ))}
                            </div>
                            {errors.order_type && <span className="mt-2 block text-sm text-danger">{errors.order_type}</span>}
                            {selected && (
                                <div className="mt-4 rounded-xl border border-primary/20 bg-primary-soft p-4 text-sm text-primary-strong">
                                    Payment rule ditentukan otomatis: <strong>{selected.payment_rule === 'full_100' ? 'Full Payment 100%' : 'Down Payment 50%'}</strong>. Sales tidak dapat mengubahnya manual.
                                </div>
                            )}
                        </div>
                    </Card>

                    <Card>
                        <CardHeader title="Bukti Persetujuan Customer" subtitle="Konfirmasi deal wajib disertai bukti asli dari customer, bukan sekadar klik tombol." className="-mx-5 -mt-5 mb-4 px-5" />
                        <div className="space-y-4">
                            <Field label="Dokumen Quotation (ditandatangani & distempel)" required error={errors.signed_quotation} hint="PDF/gambar, maks 1 MB. Stempel wajib bila customer berupa perusahaan/instansi.">
                                <input type="file" accept=".pdf,.jpg,.jpeg,.png" onChange={(e) => pickFile({ setData, setError, clearErrors }, 'signed_quotation', e.target.files[0], 1)} className="mt-1 block w-full text-sm text-text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-primary-soft file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-primary-strong" />
                            </Field>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field label="Purchase Order dari Customer (opsional)" error={errors.purchase_order} hint="maks 1 MB">
                                    <input type="file" accept=".pdf,.jpg,.jpeg,.png" onChange={(e) => pickFile({ setData, setError, clearErrors }, 'purchase_order', e.target.files[0], 1)} className="mt-1 block w-full text-sm text-text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-primary-soft file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-primary-strong" />
                                </Field>
                                <Field label="Nomor PO (opsional)" error={errors.po_number}>
                                    <Input value={data.po_number} onChange={(e) => setData('po_number', e.target.value)} placeholder="mis. PO/2026/00123" />
                                </Field>
                                <Field label="Tanggal PO (opsional)" error={errors.po_date}>
                                    <Input type="date" value={data.po_date} onChange={(e) => setData('po_date', e.target.value)} />
                                </Field>
                            </div>
                        </div>
                    </Card>

                    <Card padded={false}>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b border-border bg-surface-2 text-[11px] font-bold uppercase tracking-wider text-text-faint">
                                        <th className="px-4 py-3">Item</th><th className="px-4 py-3">Qty</th><th className="px-4 py-3 text-right">Selling Price</th><th className="px-4 py-3 text-right">Diskon</th><th className="px-4 py-3 text-right">DPP</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {quotation.lines.map((line) => (
                                        <tr key={line.id}>
                                            <td className="px-4 py-3.5 font-medium text-text">{line.item_name}<CategoryBadge category={line.category} />{line.sourcing_note && <div className="mt-0.5 text-[11px] font-normal italic text-text-muted">Opsi: {line.sourcing_note}</div>}</td>
                                            <td className="px-4 py-3.5 text-text-muted">{line.qty} {line.unit}</td>
                                            <td className="px-4 py-3.5 text-right tabular-nums">{money(line.selling_price)}</td>
                                            <td className="px-4 py-3.5 text-right tabular-nums text-text-muted">{Number(line.discount_amount) > 0 ? `${money(line.discount_amount)} (${line.discount_percent ?? 0}%)` : '—'}</td>
                                            <td className="px-4 py-3.5 text-right font-medium tabular-nums">{money(line.subtotal)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                                <Totals totals={totals} span={4} />
                            </table>
                        </div>
                    </Card>

                    <FormActions
                        cancelHref={`/sales/quotations/${quotation.id}`}
                        submitLabel="Confirm & Buat Sales Order"
                        processing={processing}
                        disabled={!data.order_type || !data.signed_quotation}
                    />
                </form>
            </div>
        </AppLayout>
    );
}
