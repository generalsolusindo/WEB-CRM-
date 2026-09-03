import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';

function money(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(v || 0));
}

function r2(n) { return Math.round(n * 100) / 100; }

export default function Create({
    salesOrder, allowedPhase, isDp = false, defaultDpPercent = 50,
    agreedDpp = null, currentPpnRate = null, hasServiceLine = false, defaultPph23Rate = 2,
    alreadyInvoiced, approvalDocs = [],
}) {
    const { data, setData, post, processing, errors } = useForm({
        sales_order_id: salesOrder.id,
        phase: allowedPhase.value,
        due_date: '',
        dp_percent: isDp ? String(defaultDpPercent) : '',
        agreed_dpp: agreedDpp != null ? String(agreedDpp) : '',
        ppn_rate: currentPpnRate != null ? String(currentPpnRate) : '',
        pph23_enabled: false,
        pph23_rate: String(defaultPph23Rate),
    });

    function submit(e) {
        e.preventDefault();
        post('/finance/invoices');
    }

    const pct = isDp ? Math.min(99, Math.max(1, Number(data.dp_percent) || defaultDpPercent)) : 100;
    const ratio = pct / 100;

    const grossAll = r2(salesOrder.lines.reduce((s, l) => s + Number(l.qty) * Number(l.selling_price || 0), 0));
    const agreed = data.agreed_dpp !== '' ? Number(data.agreed_dpp) : null;
    const dppFactor = agreed != null && agreed > 0 && grossAll > 0 ? Math.min(agreed, grossAll) / grossAll : null;
    const ppnOverride = data.ppn_rate !== '' ? Number(data.ppn_rate) : null;

    const lines = salesOrder.lines.map((l) => {
        const lineGross = r2(Number(l.qty) * Number(l.selling_price || 0));
        const fullSubtotal = dppFactor != null ? r2(lineGross * dppFactor) : Number(l.subtotal);
        const fullDiscount = dppFactor != null ? r2(lineGross - fullSubtotal) : Number(l.discount_amount || 0);
        const subtotal = r2(fullSubtotal * ratio);
        const discount = r2(fullDiscount * ratio);
        const rate = ppnOverride != null ? ppnOverride : Number(l.tax_rate || 0);
        const tax = r2(subtotal * rate / 100);
        return { ...l, invRate: rate, invSubtotal: subtotal, invTax: tax, invDiscount: discount };
    });
    const subtotalTotal = r2(lines.reduce((s, l) => s + l.invSubtotal, 0));
    const taxTotal = r2(lines.reduce((s, l) => s + l.invTax, 0));
    const discountTotal = r2(lines.reduce((s, l) => s + l.invDiscount, 0));
    const serviceDpp = r2(lines.filter((l) => l.category === 'service').reduce((s, l) => s + l.invSubtotal, 0));
    const pph23Amount = data.pph23_enabled ? Math.round(serviceDpp * Number(data.pph23_rate || 0) / 100) : 0;
    const totalTagihan = r2(subtotalTotal + taxTotal);

    return (
        <AppLayout>
            <Head title="Buat Invoice" />
            <div className="mx-auto max-w-4xl space-y-5">
                <div>
                    <Link href="/finance/invoices" className="text-sm text-info">← Kembali</Link>
                    <h1 className="mt-2 text-2xl font-bold text-text">Buat Invoice — {salesOrder.number}</h1>
                    <p className="text-sm text-text-muted">{salesOrder.contact.name} · {salesOrder.contact.company_name || 'Tanpa perusahaan'}</p>
                </div>

                {(salesOrder.po_number || approvalDocs.length > 0) && (
                    <section className="rounded-xl border border-border bg-surface p-5 shadow-sm">
                        <h2 className="text-sm font-semibold text-text">Referensi Persetujuan Customer</h2>
                        <div className="mt-2 flex flex-wrap items-center gap-3 text-sm text-text-muted">
                            {salesOrder.po_number && <span>Nomor PO: <span className="font-medium text-text">{salesOrder.po_number}</span></span>}
                            {approvalDocs.map((doc) => <a key={doc.category} href={doc.url} target="_blank" rel="noreferrer" className="rounded-lg border border-info/30 px-3 py-1 text-xs font-semibold text-info">{doc.label}</a>)}
                        </div>
                    </section>
                )}

                {alreadyInvoiced && (
                    <div className="rounded-lg border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger">
                        Sales Order ini sudah memiliki invoice muka aktif.
                    </div>
                )}
                {errors.phase && <div className="rounded-lg border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger">{errors.phase}</div>}
                {errors.sales_order && <div className="rounded-lg border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger">{errors.sales_order}</div>}

                <form onSubmit={submit} className="space-y-5">
                    <section className="rounded-xl border border-border bg-surface p-5 shadow-sm">
                        <div className="grid gap-4 sm:grid-cols-3">
                            <div>
                                <div className="text-xs font-semibold uppercase tracking-wide text-text-muted">Jenis Invoice</div>
                                <div className="mt-1 font-medium text-text">{allowedPhase.label}</div>
                                <p className="text-xs text-text-muted">Ditentukan otomatis dari Order Type.</p>
                            </div>
                            {isDp && (
                                <label className="text-sm font-medium text-text">Persentase DP (%)
                                    <input type="number" min="1" max="99" step="0.01" value={data.dp_percent} onChange={(e) => setData('dp_percent', e.target.value)} className="input" />
                                    <span className="text-xs text-text-muted">Default 50%. Sisanya ditagih di invoice pelunasan.</span>
                                    {errors.dp_percent && <span className="block text-xs text-danger">{errors.dp_percent}</span>}
                                </label>
                            )}
                            <label className="text-sm font-medium text-text">Jatuh Tempo
                                <input type="date" value={data.due_date} onChange={(e) => setData('due_date', e.target.value)} className="input" />
                                {errors.due_date && <span className="text-xs text-danger">{errors.due_date}</span>}
                            </label>
                        </div>

                        <div className="mt-4 grid gap-4 border-t border-border pt-4 sm:grid-cols-2">
                            <label className="text-sm font-medium text-text">Nilai DPP disepakati <span className="font-normal text-text-muted">(opsional)</span>
                                <input type="number" min="0" step="0.01" value={data.agreed_dpp} onChange={(e) => setData('agreed_dpp', e.target.value)} placeholder="kosongkan = pakai diskon Sales Order" className="input" />
                                {dppFactor != null && <span className="mt-1 block text-xs text-info">Diskon global {r2((1 - dppFactor) * 100)}% dibagi rata ke semua baris (menimpa diskon Sales Order).</span>}
                                {agreed != null && agreed > grossAll && <span className="mt-1 block text-xs text-warning">Melebihi subtotal bruto ({money(grossAll)}) — dibatasi ke bruto.</span>}
                                {errors.agreed_dpp && <span className="block text-xs text-danger">{errors.agreed_dpp}</span>}
                            </label>
                            <label className="text-sm font-medium text-text">Tarif PPN
                                <select value={data.ppn_rate} onChange={(e) => setData('ppn_rate', e.target.value)} className="input">
                                    <option value="">Ikuti Sales Order</option>
                                    <option value="0">0% (tanpa PPN)</option>
                                    <option value="11">11%</option>
                                    <option value="12">12%</option>
                                </select>
                                {errors.ppn_rate && <span className="block text-xs text-danger">{errors.ppn_rate}</span>}
                            </label>
                            <div className="sm:col-span-2">
                                <label className="flex items-center gap-2 text-sm font-medium text-text">
                                    <input type="checkbox" checked={data.pph23_enabled} disabled={!hasServiceLine} onChange={(e) => setData('pph23_enabled', e.target.checked)} />
                                    Customer memotong PPh 23 atas baris jasa
                                </label>
                                {!hasServiceLine && <span className="mt-1 block text-xs text-text-muted">Sales Order ini tidak punya baris berkategori Jasa.</span>}
                                {data.pph23_enabled && (
                                    <label className="mt-2 block text-sm text-text">Tarif PPh 23 (%)
                                        <input type="number" min="0" max="10" step="0.01" value={data.pph23_rate} onChange={(e) => setData('pph23_rate', e.target.value)} className="input max-w-[8rem]" />
                                        <span className="mt-1 block text-xs text-text-muted">
                                            Basis {money(serviceDpp)} (porsi jasa {pct}%) → potongan {money(pph23Amount)}. Dibagi proporsional dengan invoice pelunasan.
                                        </span>
                                    </label>
                                )}
                                {errors.pph23_rate && <span className="block text-xs text-danger">{errors.pph23_rate}</span>}
                            </div>
                        </div>
                    </section>

                    <section className="overflow-hidden rounded-xl border border-border bg-surface shadow-sm">
                        <div className="border-b border-border p-5"><h2 className="font-semibold text-text">Rincian Baris ({pct}% dari Sales Order)</h2></div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-bg text-text-muted"><tr><th className="px-4 py-3">Item</th><th className="px-4 py-3">Qty</th><th className="px-4 py-3 text-right">Diskon</th><th className="px-4 py-3">Pajak</th><th className="px-4 py-3 text-right">DPP</th></tr></thead>
                                <tbody className="divide-y divide-border">
                                    {lines.map((l) => (
                                        <tr key={l.id}>
                                            <td className="px-4 py-3 text-text">{l.item_name}{ratio < 1 ? ' (DP 50%)' : ''}</td>
                                            <td className="px-4 py-3 text-text-muted">{l.qty} {l.unit}</td>
                                            <td className="px-4 py-3 text-right text-text-muted">{l.invDiscount > 0 ? money(l.invDiscount) : '—'}</td>
                                            <td className="px-4 py-3 text-text-muted">{l.invRate > 0 ? `${l.invRate}%` : '—'}</td>
                                            <td className="px-4 py-3 text-right text-text">{money(l.invSubtotal)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                                <tfoot className="border-t border-border bg-bg text-text">
                                    <tr><td colSpan="4" className="px-4 py-2 text-right text-text-muted">Subtotal Bruto</td><td className="px-4 py-2 text-right font-medium">{money(subtotalTotal + discountTotal)}</td></tr>
                                    <tr><td colSpan="4" className="px-4 py-2 text-right text-text-muted">Total Diskon</td><td className="px-4 py-2 text-right font-medium text-danger">{discountTotal > 0 ? `− ${money(discountTotal)}` : money(0)}</td></tr>
                                    <tr><td colSpan="4" className="px-4 py-2 text-right text-text-muted">DPP</td><td className="px-4 py-2 text-right font-medium">{money(subtotalTotal)}</td></tr>
                                    <tr><td colSpan="4" className="px-4 py-2 text-right text-text-muted">PPN{ppnOverride != null ? ` (${ppnOverride}%)` : ''}</td><td className="px-4 py-2 text-right font-medium">{money(taxTotal)}</td></tr>
                                    <tr><td colSpan="4" className="px-4 py-2 text-right font-semibold">Total Tagihan</td><td className="px-4 py-2 text-right font-semibold">{money(totalTagihan)}</td></tr>
                                    {pph23Amount > 0 && <tr><td colSpan="4" className="px-4 py-2 text-right text-text-muted">PPh 23 ({data.pph23_rate}%)</td><td className="px-4 py-2 text-right font-medium text-danger">− {money(pph23Amount)}</td></tr>}
                                    <tr><td colSpan="4" className="px-4 py-4 text-right font-semibold">{pph23Amount > 0 ? 'Dibayar Customer (kas)' : 'Grand Total'}</td><td className="px-4 py-4 text-right text-lg font-bold">{money(totalTagihan - pph23Amount)}</td></tr>
                                    {isDp && ratio < 1 && (() => {
                                        const dpPayable = r2(totalTagihan - pph23Amount);
                                        const fullPayable = r2(dpPayable / ratio);
                                        return (
                                            <>
                                                <tr><td colSpan="4" className="px-4 pt-3 pb-1 text-right text-xs text-text-muted">Nilai kontrak (100%)</td><td className="px-4 pt-3 pb-1 text-right text-xs">{money(fullPayable)}</td></tr>
                                                <tr><td colSpan="4" className="px-4 py-1 text-right text-xs text-text-muted">Sisa — pelunasan setelah BAST</td><td className="px-4 py-1 text-right text-xs font-medium">{money(r2(fullPayable - dpPayable))}</td></tr>
                                            </>
                                        );
                                    })()}
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
