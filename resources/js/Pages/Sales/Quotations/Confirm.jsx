import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { Totals } from './Show';
import CategoryBadge from '../../../Components/CategoryBadge';

export default function Confirm({ quotation, orderTypes, totals }) {
    const { data, setData, post, processing, errors } = useForm({
        order_type: '',
        signed_quotation: null,
        purchase_order: null,
        po_number: '',
    });
    const selected = orderTypes.find((type) => type.value === data.order_type);
    function submit(e) { e.preventDefault(); post(`/sales/quotations/${quotation.id}/confirm`, { forceFormData: true }); }

    return <AppLayout><Head title="Confirm Deal" /><div className="mx-auto max-w-4xl space-y-5">
        <div><Link href={`/sales/quotations/${quotation.id}`} className="text-sm text-info">← Kembali ke Quotation</Link><h1 className="mt-2 text-2xl font-bold text-text">Confirm Deal</h1><p className="text-sm text-text-muted">QT-{String(quotation.id).padStart(6, '0')} / R{quotation.revision_number} · {quotation.contact.name}</p></div>
        <form onSubmit={submit} className="space-y-5">
            {errors.quotation && <Alert text={errors.quotation} />}
            <section className="rounded-xl border border-border bg-surface p-6 shadow-sm"><h2 className="mb-4 font-semibold text-text">Pilih Order Type</h2><div className="grid gap-3 sm:grid-cols-3">{orderTypes.map((type) => <label key={type.value} className={`cursor-pointer rounded-xl border p-4 ${data.order_type === type.value ? 'border-navy bg-navy/5' : 'border-border'}`}><input type="radio" name="order_type" value={type.value} checked={data.order_type === type.value} onChange={(e) => setData('order_type', e.target.value)} className="mr-2" /><span className="font-semibold text-text">{type.label}</span><div className="mt-2 text-xs text-text-muted">Payment rule: {type.payment_rule === 'full_100' ? 'Full Payment 100%' : 'Down Payment 50%'}</div></label>)}</div>{errors.order_type && <span className="mt-2 block text-sm text-danger">{errors.order_type}</span>}
                {selected && <div className="mt-5 rounded-lg bg-info/10 p-4 text-sm text-info">Payment rule ditentukan otomatis: <strong>{selected.payment_rule === 'full_100' ? 'Full Payment 100%' : 'Down Payment 50%'}</strong>. Sales tidak dapat mengubahnya secara manual.</div>}
            </section>
            <section className="rounded-xl border border-border bg-surface p-6 shadow-sm">
                <h2 className="font-semibold text-text">Bukti Persetujuan Customer</h2>
                <p className="mt-1 text-sm text-text-muted">Konfirmasi deal wajib disertai bukti asli dari customer, bukan sekadar klik tombol.</p>
                <div className="mt-4 space-y-4">
                    <label className="block text-sm font-medium text-text">Dokumen Quotation (ditandatangani &amp; distempel) <span className="text-danger">*</span>
                        <input type="file" accept=".pdf,.jpg,.jpeg,.png" onChange={(e) => setData('signed_quotation', e.target.files[0] ?? null)} className="mt-1 block w-full text-sm" />
                        <span className="mt-1 block text-xs text-text-muted">PDF/gambar, maks 1 MB. Stempel wajib bila customer berupa perusahaan/instansi.</span>
                        {errors.signed_quotation && <span className="mt-1 block text-xs text-danger">{errors.signed_quotation}</span>}
                    </label>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <label className="block text-sm font-medium text-text">Purchase Order dari Customer (opsional)
                            <input type="file" accept=".pdf,.jpg,.jpeg,.png" onChange={(e) => setData('purchase_order', e.target.files[0] ?? null)} className="mt-1 block w-full text-sm" />
                            {errors.purchase_order && <span className="mt-1 block text-xs text-danger">{errors.purchase_order}</span>}
                        </label>
                        <label className="block text-sm font-medium text-text">Nomor PO (opsional)
                            <input value={data.po_number} onChange={(e) => setData('po_number', e.target.value)} className="input" placeholder="mis. PO/2026/00123" />
                            {errors.po_number && <span className="mt-1 block text-xs text-danger">{errors.po_number}</span>}
                        </label>
                    </div>
                </div>
            </section>
            <section className="overflow-hidden rounded-xl border border-border bg-surface shadow-sm"><table className="w-full text-left text-sm"><thead className="bg-bg text-text-muted"><tr><th className="px-4 py-3">Item</th><th className="px-4 py-3">Qty</th><th className="px-4 py-3 text-right">Selling Price</th><th className="px-4 py-3 text-right">Diskon</th><th className="px-4 py-3 text-right">DPP</th></tr></thead><tbody className="divide-y divide-border">{quotation.lines.map((line) => <tr key={line.id}><td className="px-4 py-3 font-medium text-text">{line.item_name}<CategoryBadge category={line.category} />{line.sourcing_note && <div className="mt-0.5 text-[11px] italic font-normal text-text-muted">Opsi: {line.sourcing_note}</div>}</td><td className="px-4 py-3 text-text-muted">{line.qty} {line.unit}</td><td className="px-4 py-3 text-right">{money(line.selling_price)}</td><td className="px-4 py-3 text-right text-text-muted">{Number(line.discount_amount) > 0 ? `${money(line.discount_amount)} (${line.discount_percent ?? 0}%)` : '—'}</td><td className="px-4 py-3 text-right font-medium">{money(line.subtotal)}</td></tr>)}</tbody><Totals totals={totals} span={4} /></table></section>
            <div className="flex justify-end gap-3"><Link href={`/sales/quotations/${quotation.id}`} className="rounded-lg border border-border px-4 py-2 text-sm">Batal</Link><button disabled={processing || !data.order_type || !data.signed_quotation} className="rounded-lg bg-success px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">{processing ? 'Memproses...' : 'Confirm & Create Sales Order'}</button></div>
        </form>
    </div></AppLayout>;
}
function money(value) { return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(value || 0)); }
function Alert({ text }) { return <div className="rounded-lg border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger">{text}</div>; }
