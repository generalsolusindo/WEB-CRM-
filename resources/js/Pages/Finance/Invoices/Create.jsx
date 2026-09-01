import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';

function money(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(v || 0));
}

export default function Create({ salesOrder, allowedPhase, ratio, alreadyInvoiced }) {
    const { data, setData, post, processing, errors } = useForm({
        sales_order_id: salesOrder.id,
        phase: allowedPhase.value,
        due_date: '',
    });

    function submit(e) {
        e.preventDefault();
        post('/finance/invoices');
    }

    const lines = salesOrder.lines.map((l) => {
        const subtotal = Math.round(Number(l.subtotal) * ratio * 100) / 100;
        const discount = Math.round(Number(l.discount_amount || 0) * ratio * 100) / 100;
        const tax = Math.round(subtotal * Number(l.tax_rate || 0)) / 100;
        return { ...l, invSubtotal: subtotal, invTax: tax, invDiscount: discount };
    });
    const subtotalTotal = lines.reduce((s, l) => s + l.invSubtotal, 0);
    const taxTotal = lines.reduce((s, l) => s + l.invTax, 0);
    const discountTotal = lines.reduce((s, l) => s + l.invDiscount, 0);

    return (
        <AppLayout>
            <Head title="Buat Invoice" />
            <div className="mx-auto max-w-4xl space-y-5">
                <div>
                    <Link href="/finance/invoices" className="text-sm text-info">← Kembali</Link>
                    <h1 className="mt-2 text-2xl font-bold text-text">Buat Invoice — {salesOrder.number}</h1>
                    <p className="text-sm text-text-muted">{salesOrder.contact.name} · {salesOrder.contact.company_name || 'Tanpa perusahaan'}</p>
                </div>

                {alreadyInvoiced && (
                    <div className="rounded-lg border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger">
                        Sales Order ini sudah memiliki invoice muka aktif.
                    </div>
                )}
                {errors.phase && <div className="rounded-lg border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger">{errors.phase}</div>}
                {errors.sales_order && <div className="rounded-lg border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger">{errors.sales_order}</div>}

                <form onSubmit={submit} className="space-y-5">
                    <section className="rounded-xl border border-border bg-surface p-5 shadow-sm">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <div className="text-xs font-semibold uppercase tracking-wide text-text-muted">Jenis Invoice</div>
                                <div className="mt-1 font-medium text-text">{allowedPhase.label}</div>
                                <p className="text-xs text-text-muted">Ditentukan otomatis dari Order Type.</p>
                            </div>
                            <label className="text-sm font-medium text-text">Jatuh Tempo
                                <input type="date" value={data.due_date} onChange={(e) => setData('due_date', e.target.value)} className="input" />
                                {errors.due_date && <span className="text-xs text-danger">{errors.due_date}</span>}
                            </label>
                        </div>
                    </section>

                    <section className="overflow-hidden rounded-xl border border-border bg-surface shadow-sm">
                        <div className="border-b border-border p-5"><h2 className="font-semibold text-text">Rincian Baris ({Math.round(ratio * 100)}% dari Sales Order)</h2></div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-bg text-text-muted"><tr><th className="px-4 py-3">Item</th><th className="px-4 py-3">Qty</th><th className="px-4 py-3 text-right">Diskon</th><th className="px-4 py-3">Pajak</th><th className="px-4 py-3 text-right">DPP</th></tr></thead>
                                <tbody className="divide-y divide-border">
                                    {lines.map((l) => (
                                        <tr key={l.id}>
                                            <td className="px-4 py-3 text-text">{l.item_name}{ratio < 1 ? ' (DP 50%)' : ''}</td>
                                            <td className="px-4 py-3 text-text-muted">{l.qty} {l.unit}</td>
                                            <td className="px-4 py-3 text-right text-text-muted">{l.invDiscount > 0 ? money(l.invDiscount) : '—'}</td>
                                            <td className="px-4 py-3 text-text-muted">{l.tax ? l.tax.name : (Number(l.tax_rate) > 0 ? `${l.tax_rate}%` : '—')}</td>
                                            <td className="px-4 py-3 text-right text-text">{money(l.invSubtotal)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                                <tfoot className="border-t border-border bg-bg text-text">
                                    <tr><td colSpan="4" className="px-4 py-2 text-right text-text-muted">Subtotal Bruto</td><td className="px-4 py-2 text-right font-medium">{money(subtotalTotal + discountTotal)}</td></tr>
                                    <tr><td colSpan="4" className="px-4 py-2 text-right text-text-muted">Total Diskon</td><td className="px-4 py-2 text-right font-medium text-danger">{discountTotal > 0 ? `− ${money(discountTotal)}` : money(0)}</td></tr>
                                    <tr><td colSpan="4" className="px-4 py-2 text-right text-text-muted">DPP</td><td className="px-4 py-2 text-right font-medium">{money(subtotalTotal)}</td></tr>
                                    <tr><td colSpan="4" className="px-4 py-2 text-right text-text-muted">Total PPN</td><td className="px-4 py-2 text-right font-medium">{money(taxTotal)}</td></tr>
                                    <tr><td colSpan="4" className="px-4 py-4 text-right font-semibold">Grand Total</td><td className="px-4 py-4 text-right text-lg font-bold">{money(subtotalTotal + taxTotal)}</td></tr>
                                </tfoot>
                            </table>
                        </div>
                    </section>

                    <div className="flex justify-end gap-3">
                        <Link href="/finance/invoices" className="rounded-lg border border-border px-4 py-2 text-sm">Batal</Link>
                        <button disabled={processing || alreadyInvoiced} className="rounded-lg bg-navy px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">
                            {processing ? 'Menyimpan...' : 'Buat Invoice Draft'}
                        </button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
