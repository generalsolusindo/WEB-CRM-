import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, Card, CardHeader, Field, Input, Select, Textarea, FormActions, CurrencyInput } from '../../../Components/ui';
import { feedback } from '../../../Components/feedback';
import TableScroll from '../../../Components/ui/TableScroll';

function money(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(v || 0));
}

function r2(n) { return Math.round(n * 100) / 100; }

function Alert({ text }) {
    return <div className="rounded-xl border border-danger/25 bg-danger-soft px-4 py-3 text-sm font-medium text-danger">{text}</div>;
}

export default function Create({
    salesOrder, allowedPhases, defaultPhase, defaultDpPercent = 50,
    agreedDpp = null, currentPpnRate = null, hasServiceLine = false, defaultPph23Rate = 2,
    alreadyInvoiced, approvalDocs = [],
}) {
    const { data, setData, post, processing, errors } = useForm({
        sales_order_id: salesOrder.id,
        phase: defaultPhase,
        due_date: '',
        dp_percent: defaultPhase === 'dp' ? String(defaultDpPercent) : '',
        agreed_dpp: agreedDpp != null ? String(agreedDpp) : '',
        ppn_rate: currentPpnRate != null ? String(currentPpnRate) : '',
        pph23_enabled: false,
        pph23_rate: String(defaultPph23Rate),
        notes: '',
        lines: null,
    });
    const [manualLines, setManualLines] = useState(false);

    function submit(e) {
        e.preventDefault();
        feedback.expect({ success: { title: 'Invoice dibuat', style: 'popup' } });
        post('/finance/invoices');
    }

    const isDp = data.phase === 'dp';
    const pct = isDp ? Math.min(99, Math.max(1, Number(data.dp_percent) || defaultDpPercent)) : 100;
    const ratio = pct / 100;

    const grossAll = r2(salesOrder.lines.reduce((s, l) => s + Number(l.qty) * Number(l.selling_price || 0), 0));
    const agreed = data.agreed_dpp !== '' ? Number(data.agreed_dpp) : null;
    const dppFactor = agreed != null && agreed > 0 && grossAll > 0 ? Math.min(agreed, grossAll) / grossAll : null;
    const ppnOverride = data.ppn_rate !== '' ? Number(data.ppn_rate) : null;

    const computedLines = salesOrder.lines.map((l) => {
        const lineGross = r2(Number(l.qty) * Number(l.selling_price || 0));
        const fullSubtotal = dppFactor != null ? r2(lineGross * dppFactor) : Number(l.subtotal);
        const fullDiscount = dppFactor != null ? r2(lineGross - fullSubtotal) : Number(l.discount_amount || 0);
        const subtotal = r2(fullSubtotal * ratio);
        const discount = r2(fullDiscount * ratio);
        const rate = ppnOverride != null ? ppnOverride : Number(l.tax_rate || 0);
        const tax = r2(subtotal * rate / 100);
        return { ...l, invRate: rate, invSubtotal: subtotal, invTax: tax, invDiscount: discount };
    });

    function enableManualLines() {
        const seeded = computedLines.map((l) => {
            const qty = Number(l.qty) || 1;
            const unitPrice = qty > 0 ? r2((l.invSubtotal + l.invDiscount) / qty) : 0;
            return {
                sales_order_line_id: l.id,
                item_name: l.item_name,
                category: l.category,
                qty,
                unit: l.unit,
                unit_price: unitPrice,
                discount_amount: l.invDiscount,
                tax_rate: l.invRate,
            };
        });
        setData('lines', seeded);
        setManualLines(true);
    }

    function disableManualLines() {
        setData('lines', null);
        setManualLines(false);
    }

    function updateLine(index, field, value) {
        const next = data.lines.map((l, i) => (i === index ? { ...l, [field]: value } : l));
        setData('lines', next);
    }

    function removeLine(index) {
        setData('lines', data.lines.filter((_, i) => i !== index));
    }

    function addLine() {
        setData('lines', [...data.lines, {
            sales_order_line_id: null, item_name: '', category: 'material', qty: 1, unit: 'unit', unit_price: 0, discount_amount: 0, tax_rate: 0,
        }]);
    }

    const manualComputed = manualLines ? (data.lines || []).map((l) => {
        const qty = Number(l.qty) || 0;
        const unitPrice = Number(l.unit_price) || 0;
        const discount = Number(l.discount_amount) || 0;
        const rate = Number(l.tax_rate) || 0;
        const subtotal = r2(qty * unitPrice - discount);
        const tax = r2(subtotal * rate / 100);
        return { ...l, invSubtotal: subtotal, invTax: tax, invDiscount: discount, invRate: rate };
    }) : [];

    const displayLines = manualLines ? manualComputed : computedLines;
    const subtotalTotal = r2(displayLines.reduce((s, l) => s + l.invSubtotal, 0));
    const taxTotal = r2(displayLines.reduce((s, l) => s + l.invTax, 0));
    const discountTotal = r2(displayLines.reduce((s, l) => s + l.invDiscount, 0));
    const serviceDpp = r2(displayLines.filter((l) => l.category === 'service').reduce((s, l) => s + l.invSubtotal, 0));
    const pph23Amount = data.pph23_enabled ? Math.round(serviceDpp * Number(data.pph23_rate || 0) / 100) : 0;
    const totalTagihan = r2(subtotalTotal + taxTotal);

    return (
        <AppLayout>
            <Head title="Buat Invoice" />
            <div className="mx-auto max-w-4xl space-y-5">
                <PageHeader
                    title={`Buat Invoice — ${salesOrder.number}`}
                    subtitle={`${salesOrder.contact.name} · ${salesOrder.contact.company_name || 'Tanpa perusahaan'}`}
                    back={{ href: '/finance/invoices', label: 'Kembali ke Invoice' }}
                />

                {(salesOrder.po_number || approvalDocs.length > 0) && (
                    <Card>
                        <h2 className="text-sm font-semibold text-text">Referensi Persetujuan Customer</h2>
                        <div className="mt-2 flex flex-wrap items-center gap-3 text-sm text-text-muted">
                            {salesOrder.po_number && <span>Nomor PO: <span className="font-medium text-text">{salesOrder.po_number}</span></span>}
                            {approvalDocs.map((doc) => <a key={doc.category} href={doc.url} target="_blank" rel="noreferrer" className="rounded-lg border border-primary/30 px-3 py-1 text-xs font-semibold text-primary">{doc.label}</a>)}
                        </div>
                    </Card>
                )}

                {alreadyInvoiced && <Alert text="Sales Order ini sudah memiliki invoice muka aktif." />}
                {errors.phase && <Alert text={errors.phase} />}
                {errors.sales_order && <Alert text={errors.sales_order} />}

                <form onSubmit={submit} className="space-y-5">
                    <Card>
                        <div className="grid gap-4 sm:grid-cols-3">
                            {allowedPhases.length > 1 ? (
                                <Field label="Jenis Invoice" hint="Pilih Full kalau pekerjaan sudah dieksekusi/selesai duluan (mis. addendum) dan tidak perlu DP lagi." error={errors.phase}>
                                    <Select
                                        value={data.phase}
                                        onChange={(e) => {
                                            const phase = e.target.value;
                                            setData((d) => ({ ...d, phase, dp_percent: phase === 'dp' ? String(defaultDpPercent) : '' }));
                                        }}
                                    >
                                        {allowedPhases.map((p) => <option key={p.value} value={p.value}>{p.label}</option>)}
                                    </Select>
                                </Field>
                            ) : (
                                <div>
                                    <div className="text-[11px] font-bold uppercase tracking-wider text-text-faint">Jenis Invoice</div>
                                    <div className="mt-1 font-medium text-text">{allowedPhases[0].label}</div>
                                    <p className="text-xs text-text-muted">Ditentukan otomatis dari Order Type.</p>
                                </div>
                            )}
                            {isDp && (
                                <Field label="Persentase DP (%)" hint="Default 50%. Sisanya ditagih di invoice pelunasan." error={errors.dp_percent}>
                                    <Input type="number" min="1" max="99" step="0.01" value={data.dp_percent} onChange={(e) => setData('dp_percent', e.target.value)} disabled={manualLines} />
                                </Field>
                            )}
                            <Field label="Jatuh Tempo" error={errors.due_date}>
                                <Input type="date" value={data.due_date} onChange={(e) => setData('due_date', e.target.value)} />
                            </Field>
                        </div>

                        <div className="mt-4 grid gap-4 border-t border-border pt-4 sm:grid-cols-2">
                            <Field
                                label={<>Nilai DPP disepakati <span className="font-normal text-text-muted">(opsional)</span></>}
                                error={errors.agreed_dpp}
                            >
                                <CurrencyInput value={data.agreed_dpp} onChange={(e) => setData('agreed_dpp', e.target.value)} placeholder="kosongkan = pakai diskon Sales Order" className="input" disabled={manualLines} />
                                {dppFactor != null && <span className="mt-1 block text-xs text-info">Diskon global {r2((1 - dppFactor) * 100)}% dibagi rata ke semua baris (menimpa diskon Sales Order).</span>}
                                {agreed != null && agreed > grossAll && <span className="mt-1 block text-xs text-warning">Melebihi subtotal bruto ({money(grossAll)}) — dibatasi ke bruto.</span>}
                            </Field>
                            <Field label="Tarif PPN" error={errors.ppn_rate}>
                                <Select value={data.ppn_rate} onChange={(e) => setData('ppn_rate', e.target.value)} disabled={manualLines}>
                                    <option value="">Ikuti Sales Order</option>
                                    <option value="0">0% (tanpa PPN)</option>
                                    <option value="11">11%</option>
                                    <option value="12">12%</option>
                                </Select>
                            </Field>
                            {manualLines && (
                                <p className="text-xs text-text-muted sm:col-span-2">Nilai DPP disepakati &amp; Tarif PPN dinonaktifkan karena Anda sedang mengedit item invoice secara manual — atur diskon/pajak langsung di tiap baris di bawah.</p>
                            )}
                            <div className="sm:col-span-2">
                                <label className="flex items-center gap-2 text-sm font-medium text-text">
                                    <input type="checkbox" checked={data.pph23_enabled} disabled={!hasServiceLine} onChange={(e) => setData('pph23_enabled', e.target.checked)} className="accent-[var(--color-navy)]" />
                                    Customer memotong PPh 23 atas baris jasa
                                </label>
                                {!hasServiceLine && <span className="mt-1 block text-xs text-text-muted">Sales Order ini tidak punya baris berkategori Jasa.</span>}
                                {data.pph23_enabled && (
                                    <label className="mt-2 block text-sm text-text">Tarif PPh 23 (%)
                                        <Input type="number" min="0" max="10" step="0.01" value={data.pph23_rate} onChange={(e) => setData('pph23_rate', e.target.value)} className="max-w-[8rem]" />
                                        <span className="mt-1 block text-xs text-text-muted">
                                            Basis {money(serviceDpp)} (porsi jasa {pct}%) → potongan {money(pph23Amount)}. Dibagi proporsional dengan invoice pelunasan.
                                        </span>
                                    </label>
                                )}
                                {errors.pph23_rate && <span className="mt-1 block text-xs font-medium text-danger">{errors.pph23_rate}</span>}
                            </div>
                            <div className="sm:col-span-2">
                                <Field label={<>Catatan <span className="font-normal text-text-muted">(opsional)</span></>} error={errors.notes}>
                                    <Textarea rows={2} value={data.notes} onChange={(e) => setData('notes', e.target.value)} placeholder="Alasan penyesuaian, referensi negosiasi, dll." />
                                </Field>
                            </div>
                        </div>
                    </Card>

                    <Card padded={false}>
                        <CardHeader
                            title={manualLines ? 'Rincian Baris (edit manual)' : `Rincian Baris (${pct}% dari Sales Order)`}
                            actions={manualLines
                                ? <button type="button" onClick={disableManualLines} className="text-xs font-semibold text-primary hover:underline">Batalkan edit manual</button>
                                : <button type="button" onClick={enableManualLines} className="text-xs font-semibold text-primary hover:underline">Edit item manual</button>}
                        />
                        <TableScroll>
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b border-border bg-surface-2 text-[11px] font-bold uppercase tracking-wider text-text-faint">
                                        <th className="sticky left-0 z-[1] bg-surface-2 px-4 py-3">Item</th>
                                        {manualLines && <th className="hidden px-4 py-3 sm:table-cell">Kategori</th>}
                                        <th className="px-4 py-3">Qty</th>
                                        {manualLines && <th className="px-4 py-3 text-right">Harga Satuan</th>}
                                        <th className="px-4 py-3 text-right">Diskon</th>
                                        <th className="px-4 py-3">Pajak (%)</th>
                                        <th className="px-4 py-3 text-right">DPP</th>
                                        {manualLines && <th className="hidden px-4 py-3 sm:table-cell"></th>}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {manualLines ? manualComputed.map((l, i) => (
                                        <tr key={i}>
                                            <td className="sticky left-0 z-[1] w-44 min-w-44 bg-surface px-4 py-2 sm:w-auto">
                                                <input value={l.item_name} onChange={(e) => updateLine(i, 'item_name', e.target.value)} className="w-full rounded-lg border border-border px-2 py-1.5 outline-none focus:border-navy" />
                                                <div className="mt-1.5 flex items-center justify-between gap-2 sm:hidden">
                                                    <select value={l.category} onChange={(e) => updateLine(i, 'category', e.target.value)} className="min-w-0 rounded-lg border border-border px-2 py-1.5 text-xs outline-none focus:border-navy">
                                                        <option value="material">Material</option>
                                                        <option value="service">Jasa</option>
                                                    </select>
                                                    <button type="button" onClick={() => removeLine(i)} className="shrink-0 rounded-lg border border-danger/30 px-3 py-1.5 text-xs font-semibold text-danger">Hapus</button>
                                                </div>
                                            </td>
                                            <td className="hidden px-4 py-2 sm:table-cell">
                                                <select value={l.category} onChange={(e) => updateLine(i, 'category', e.target.value)} className="rounded-lg border border-border px-2 py-1.5 outline-none focus:border-navy">
                                                    <option value="material">Material</option>
                                                    <option value="service">Jasa</option>
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
                                            <td className="px-4 py-2 text-right font-medium tabular-nums text-text">{money(l.invSubtotal)}</td>
                                            <td className="hidden px-4 py-2 text-right sm:table-cell">
                                                <button type="button" onClick={() => removeLine(i)} className="rounded-lg border border-danger/30 px-2 py-1 text-xs font-semibold text-danger">Hapus</button>
                                            </td>
                                        </tr>
                                    )) : computedLines.map((l) => (
                                        <tr key={l.id}>
                                            <td className="sticky left-0 z-[1] min-w-40 bg-surface px-4 py-3.5 font-medium text-text">{l.item_name}{ratio < 1 ? ' (DP 50%)' : ''}</td>
                                            <td className="px-4 py-3.5 text-text-muted">{l.qty} {l.unit}</td>
                                            <td className="px-4 py-3.5 text-right tabular-nums text-text-muted">{l.invDiscount > 0 ? money(l.invDiscount) : '—'}</td>
                                            <td className="px-4 py-3.5 text-text-muted">{l.invRate > 0 ? `${l.invRate}%` : '—'}</td>
                                            <td className="px-4 py-3.5 text-right font-medium tabular-nums text-text">{money(l.invSubtotal)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                                {manualLines && (
                                    <tfoot>
                                        <tr>
                                            <td colSpan="8" className="px-4 py-2">
                                                <button type="button" onClick={addLine} className="rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-text-muted hover:text-text">+ Tambah Item</button>
                                            </td>
                                        </tr>
                                    </tfoot>
                                )}
                                <tfoot className="border-t border-border bg-surface-2 text-text">
                                    <tr><td colSpan={manualLines ? 7 : 4} className="px-4 py-2 text-right text-text-muted">Subtotal Bruto</td><td className="px-4 py-2 text-right font-medium tabular-nums">{money(subtotalTotal + discountTotal)}</td></tr>
                                    <tr><td colSpan={manualLines ? 7 : 4} className="px-4 py-2 text-right text-text-muted">Total Diskon</td><td className="px-4 py-2 text-right font-medium tabular-nums text-danger">{discountTotal > 0 ? `− ${money(discountTotal)}` : money(0)}</td></tr>
                                    <tr><td colSpan={manualLines ? 7 : 4} className="px-4 py-2 text-right text-text-muted">DPP</td><td className="px-4 py-2 text-right font-medium tabular-nums">{money(subtotalTotal)}</td></tr>
                                    <tr><td colSpan={manualLines ? 7 : 4} className="px-4 py-2 text-right text-text-muted">PPN{ppnOverride != null && !manualLines ? ` (${ppnOverride}%)` : ''}</td><td className="px-4 py-2 text-right font-medium tabular-nums">{money(taxTotal)}</td></tr>
                                    <tr><td colSpan={manualLines ? 7 : 4} className="px-4 py-2 text-right font-semibold">Total Tagihan</td><td className="px-4 py-2 text-right font-semibold tabular-nums">{money(totalTagihan)}</td></tr>
                                    {pph23Amount > 0 && <tr><td colSpan={manualLines ? 7 : 4} className="px-4 py-2 text-right text-text-muted">PPh 23 ({data.pph23_rate}%)</td><td className="px-4 py-2 text-right font-medium tabular-nums text-danger">− {money(pph23Amount)}</td></tr>}
                                    <tr><td colSpan={manualLines ? 7 : 4} className="px-4 py-4 text-right font-semibold">{pph23Amount > 0 ? 'Dibayar Customer (kas)' : 'Grand Total'}</td><td className="px-4 py-4 text-right text-lg font-bold tabular-nums">{money(totalTagihan - pph23Amount)}</td></tr>
                                    {isDp && ratio < 1 && !manualLines && (() => {
                                        const dpPayable = r2(totalTagihan - pph23Amount);
                                        const fullPayable = r2(dpPayable / ratio);
                                        return (
                                            <>
                                                <tr><td colSpan="4" className="px-4 pt-3 pb-1 text-right text-xs text-text-muted">Nilai kontrak (100%)</td><td className="px-4 pt-3 pb-1 text-right text-xs tabular-nums">{money(fullPayable)}</td></tr>
                                                <tr><td colSpan="4" className="px-4 py-1 text-right text-xs text-text-muted">Sisa — pelunasan setelah BAST</td><td className="px-4 py-1 text-right text-xs font-medium tabular-nums">{money(r2(fullPayable - dpPayable))}</td></tr>
                                            </>
                                        );
                                    })()}
                                </tfoot>
                            </table>
                        </TableScroll>
                    </Card>

                    <FormActions
                        cancelHref="/finance/invoices"
                        submitLabel="Buat Invoice Draft"
                        processing={processing}
                        disabled={alreadyInvoiced || (manualLines && (data.lines || []).length === 0)}
                    />
                </form>
            </div>
        </AppLayout>
    );
}
