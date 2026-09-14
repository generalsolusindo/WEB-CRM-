import { Fragment } from "react";
import { Head, Link, useForm } from "@inertiajs/react";
import { PageHeader, Button, CurrencyInput } from "../../../Components/ui";
import AppLayout from '../../../Layouts/AppLayout';

function money(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(v || 0));
}
function suggestedPrice(cost) { return (Number(cost) * 1.3).toFixed(2); }
function r2(n) { return Math.round(n * 100) / 100; }

function initialLine(line, taxes) {
    const dp = line.discount_percent != null ? Number(line.discount_percent) : null;
    const da = line.discount_amount != null ? Number(line.discount_amount) : 0;
    const taxId = line.tax_id ?? line.tax?.id ?? '';
    const rate = line.tax_rate != null ? Number(line.tax_rate) : (line.tax?.rate != null ? Number(line.tax.rate) : null);
    let taxMode = 'none';
    if (taxId && taxes.some((t) => String(t.id) === String(taxId))) taxMode = 'master';
    else if (rate != null && rate > 0) taxMode = 'custom';
    return {
        procurement_request_line_id: line.procurement_request_line_id ?? line.id,
        category: line.category ?? line.vendor_product?.category ?? 'material',
        sourcing_note: line.sourcing_note ?? '',
        selling_price: line.selling_price ?? suggestedPrice(line.cost_price),
        discount_mode: dp && dp > 0 ? 'percent' : (da > 0 ? 'amount' : 'percent'),
        discount_percent: dp && dp > 0 ? String(dp) : '',
        discount_amount: da > 0 ? String(da) : '',
        tax_mode: taxMode,
        tax_id: taxMode === 'master' ? String(taxId) : '',
        tax_rate: taxMode === 'none' ? '' : (rate != null ? String(rate) : ''),
    };
}

export default function Form({ procurementRequest = null, quotation = null, taxes = [], defaultTerms = '' }) {
    const editing = Boolean(quotation);
    const sourceLines = editing ? quotation.lines : procurementRequest.lines;
    const customer = editing ? quotation.contact : procurementRequest.lead.contact;

    const { data, setData, post, put, processing, errors, transform } = useForm({
        notes: quotation?.notes ?? '',
        terms: quotation?.terms ?? defaultTerms,
        agreed_dpp: quotation?.agreed_dpp != null ? String(Number(quotation.agreed_dpp)) : '',
        lines: sourceLines.map((l) => initialLine(l, taxes)),
    });

    const groupRank = (i) => (data.lines[i].category === 'material' ? 0 : 1);
    const orderedIdx = sourceLines
        .map((_, i) => i)
        .sort((a, b) => groupRank(a) - groupRank(b) || a - b);

    transform((payload) => ({
        ...payload,
        agreed_dpp: payload.agreed_dpp === '' ? null : Number(payload.agreed_dpp),
        lines: payload.lines.map((l) => ({
            procurement_request_line_id: l.procurement_request_line_id,
            category: l.category,
            sourcing_note: l.sourcing_note,
            selling_price: l.selling_price,
            discount_percent: l.discount_mode === 'percent' && l.discount_percent !== '' ? Number(l.discount_percent) : null,
            discount_amount: l.discount_mode === 'amount' && l.discount_amount !== '' ? Number(l.discount_amount) : null,
            tax_id: l.tax_id === '' || l.tax_id === null ? null : Number(l.tax_id),
            tax_rate: l.tax_rate === '' ? null : Number(l.tax_rate),
        })),
    }));

    function setLine(i, patch) {
        setData('lines', data.lines.map((l, idx) => (idx === i ? { ...l, ...patch } : l)));
    }

    const grossAll = r2(sourceLines.reduce((s, line, i) => s + Number(line.qty) * Number(data.lines[i].selling_price || 0), 0));
    const agreedDpp = data.agreed_dpp !== '' ? Number(data.agreed_dpp) : null;
    const agreedFactor = agreedDpp != null && agreedDpp > 0 && grossAll > 0
        ? Math.min(agreedDpp, grossAll) / grossAll
        : null;

    function calc(line, i) {
        const d = data.lines[i];
        const gross = r2(Number(line.qty) * Number(d.selling_price || 0));
        let discount = 0;
        if (agreedFactor != null) {
            discount = r2(gross - r2(gross * agreedFactor));
        } else if (d.discount_mode === 'percent' && d.discount_percent !== '') discount = r2(gross * Number(d.discount_percent) / 100);
        else if (d.discount_mode === 'amount' && d.discount_amount !== '') discount = Math.min(Number(d.discount_amount), gross);
        const dpp = r2(gross - discount);
        const tax = r2(dpp * Number(d.tax_rate || 0) / 100);
        const cost = Number(line.cost_price);
        const totalCost = r2(Number(line.qty) * cost);
        return {
            gross, discount, dpp, tax,
            markup: cost > 0 ? ((Number(d.selling_price || 0) - cost) / cost) * 100 : null,
            margin: totalCost > 0 ? ((dpp - totalCost) / totalCost) * 100 : null,
        };
    }

    function onDiscountPercent(i, line, value) {
        const gross = r2(Number(line.qty) * Number(data.lines[i].selling_price || 0));
        setLine(i, {
            discount_mode: 'percent',
            discount_percent: value,
            discount_amount: value === '' ? '' : String(r2(gross * Number(value) / 100)),
        });
    }
    function onDiscountAmount(i, line, value) {
        const gross = r2(Number(line.qty) * Number(data.lines[i].selling_price || 0));
        setLine(i, {
            discount_mode: 'amount',
            discount_amount: value,
            discount_percent: value === '' || gross <= 0 ? '' : String(r2(Number(value) / gross * 100)),
        });
    }
    function onTaxPick(i, value) {
        if (value === '') { setLine(i, { tax_mode: 'none', tax_id: '', tax_rate: '' }); return; }
        if (value === 'custom') { setLine(i, { tax_mode: 'custom', tax_id: '' }); return; }
        const t = taxes.find((x) => String(x.id) === String(value));
        setLine(i, { tax_mode: 'master', tax_id: value, tax_rate: t ? String(Number(t.rate)) : '' });
    }

    const totals = sourceLines.reduce((acc, line, i) => {
        const c = calc(line, i);
        acc.gross += c.gross; acc.discount += c.discount; acc.dpp += c.dpp; acc.tax += c.tax;
        acc.cost += r2(Number(line.qty) * Number(line.cost_price));
        if (data.lines[i].category === 'service') acc.serviceDpp += c.dpp;
        return acc;
    }, { gross: 0, discount: 0, dpp: 0, tax: 0, cost: 0, serviceDpp: 0 });
    const grand = r2(totals.dpp + totals.tax);
    const pph23Estimate = r2(totals.serviceDpp * 0.02);
    const discPct = totals.gross > 0 ? r2(totals.discount / totals.gross * 100) : 0;
    const marginRp = r2(totals.dpp - totals.cost);
    const marginPct = totals.cost > 0 ? r2((totals.dpp - totals.cost) / totals.cost * 100) : null;

    function submit(e) {
        e.preventDefault();
        editing ? put(`/sales/quotations/${quotation.id}`) : post(`/sales/procurement-requests/${procurementRequest.id}/quotations`);
    }

    return (
        <AppLayout>
            <Head title={editing ? `Edit Quotation R${quotation.revision_number}` : 'Buat Quotation'} />
            <div className="mx-auto max-w-6xl space-y-5">
                <PageHeader
                    title={editing ? `Edit Quotation R${quotation.revision_number}` : 'Buat Quotation'}
                    subtitle={`${customer.name} · ${customer.company_name || 'Tanpa perusahaan'}`}
                    back={{ href: editing ? `/sales/quotations/${quotation.id}` : `/sales/leads/${procurementRequest.lead_id}` }}
                />

                <form onSubmit={submit} className="space-y-5">
                    {errors.procurement_request && <Alert text={errors.procurement_request} />}
                    {errors.quotation && <Alert text={errors.quotation} />}
                    {errors.lines && <Alert text={errors.lines} />}

                    <section className="card p-5">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="rounded-xl border border-border bg-surface-2 px-4 py-3 text-sm text-text-muted sm:col-span-2">
                                Masa berlaku quotation otomatis <strong className="text-text">10 hari</strong> sejak dibuat — tidak perlu diisi.
                            </div>
                            <label className="text-sm font-medium text-text sm:col-span-2">Catatan <span className="font-normal text-text-muted">(opsional — tampil sebagai catatan tambahan di dokumen)</span>
                                <textarea rows="2" value={data.notes} onChange={(e) => setData('notes', e.target.value)} className="input" />
                            </label>
                            <label className="text-sm font-medium text-text sm:col-span-2">Syarat &amp; Ketentuan <span className="font-normal text-text-muted">(tampil di cetakan quotation, satu baris per poin — sudah terisi default, bisa diedit bebas)</span>
                                <textarea rows="6" value={data.terms} onChange={(e) => setData('terms', e.target.value)} className="input" />
                                {errors.terms && <span className="text-xs text-danger">{errors.terms}</span>}
                            </label>
                            <label className="text-sm font-medium text-text sm:col-span-2">Nilai DPP disepakati <span className="font-normal text-text-muted">(opsional — harga nett hasil negosiasi)</span>
                                <CurrencyInput value={data.agreed_dpp} onChange={(e) => setData('agreed_dpp', e.target.value)} placeholder="mis. 10.000.000 — kosongkan untuk pakai diskon per-baris" className="input" />
                                {errors.agreed_dpp && <span className="text-xs text-danger">{errors.agreed_dpp}</span>}
                                {agreedFactor != null && (
                                    <span className="mt-1 block text-xs text-info">
                                        Diskon global {r2((1 - agreedFactor) * 100)}% (−{money(r2(grossAll - Math.min(agreedDpp, grossAll)))}) dibagi rata ke semua baris. Diskon per-baris dinonaktifkan.
                                    </span>
                                )}
                                {agreedDpp != null && agreedDpp > grossAll && (
                                    <span className="mt-1 block text-xs text-warning">Nilai DPP melebihi subtotal bruto ({money(grossAll)}) — dibatasi ke subtotal bruto (tanpa diskon).</span>
                                )}
                            </label>
                        </div>
                    </section>

                    <section className="card overflow-hidden p-0">
                        <div className="border-b border-border p-5">
                            <h2 className="font-semibold text-text">Line Items</h2>
                            <p className="text-sm text-text-muted">Item, qty & cost dari Procurement. Isi selling price, diskon (% atau Rp), dan pajak per baris.</p>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-surface-2 text-[11px] font-bold uppercase tracking-wider text-text-faint">
                                    <tr>
                                        <th className="px-3 py-3">Item</th>
                                        <th className="px-3 py-3">Qty</th>
                                        <th className="px-3 py-3 text-right">Cost</th>
                                        <th className="px-3 py-3">Selling</th>
                                        <th className="px-3 py-3">Diskon</th>
                                        <th className="px-3 py-3">Pajak %</th>
                                        <th className="px-3 py-3 text-right">DPP</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {orderedIdx.map((i, pos) => {
                                        const line = sourceLines[i];
                                        const c = calc(line, i);
                                        const group = data.lines[i].category === 'material' ? 'material' : 'service';
                                        const prevGroup = pos === 0 ? null : (data.lines[orderedIdx[pos - 1]].category === 'material' ? 'material' : 'service');
                                        return (
                                          <Fragment key={line.id}>
                                            {group !== prevGroup && (
                                                <tr className="bg-surface-2">
                                                    <td colSpan="7" className="px-3 py-2 text-[11px] font-bold uppercase tracking-wider text-text-faint">
                                                        {group === 'material' ? 'Material' : 'Jasa'}
                                                    </td>
                                                </tr>
                                            )}
                                            <tr>
                                                <td className="px-3 py-3">
                                                    <div className="font-medium text-text">{line.item_name}</div>
                                                    <div className="whitespace-pre-line text-xs text-text-muted">{line.description || '—'}</div>
                                                    <select value={data.lines[i].category} onChange={(e) => setLine(i, { category: e.target.value })} className="mt-1 rounded border border-border px-1 py-0.5 text-[11px]">
                                                        <option value="material">Material</option>
                                                        <option value="service">Jasa</option>
                                                        <option value="reimburse">Biaya Reimburse</option>
                                                    </select>
                                                    <textarea rows="2" value={data.lines[i].sourcing_note} onChange={(e) => setLine(i, { sourcing_note: e.target.value })} placeholder="Catatan opsi merk (dari Procurement). Kosongkan bila tak perlu ditampilkan ke customer." className="mt-1 w-full rounded border border-border px-1.5 py-1 text-[11px]" />
                                                    <div className="mt-1 text-xs">
                                                        <span className={c.markup != null && c.markup < 0 ? 'text-danger' : 'text-text-muted'}>Markup {c.markup == null ? '—' : `${c.markup.toFixed(1)}%`}</span>
                                                        {' · '}
                                                        <span className={c.margin != null && c.margin < 0 ? 'text-danger' : 'text-success'}>Margin efektif {c.margin == null ? '—' : `${c.margin.toFixed(1)}%`}</span>
                                                    </div>
                                                </td>
                                                <td className="whitespace-nowrap px-3 py-3 text-text-muted">{line.qty} {line.unit}</td>
                                                <td className="px-3 py-3 text-right text-text-muted">{money(line.cost_price)}</td>
                                                <td className="min-w-36 px-3 py-3">
                                                    <CurrencyInput value={data.lines[i].selling_price} onChange={(e) => setLine(i, { selling_price: e.target.value })} className="w-full rounded-lg border border-border px-2 py-1.5 text-right outline-none focus:border-navy" />
                                                    {errors[`lines.${i}.selling_price`] && <span className="text-xs text-danger">{errors[`lines.${i}.selling_price`]}</span>}
                                                </td>
                                                <td className="min-w-40 px-3 py-3">
                                                    <div className="flex items-center gap-1">
                                                        <input type="number" min="0" max="100" step="0.01" placeholder="%" disabled={agreedFactor != null} value={data.lines[i].discount_percent} onChange={(e) => onDiscountPercent(i, line, e.target.value)} className="w-16 rounded-lg border border-border px-2 py-1.5 text-right text-xs disabled:bg-bg" />
                                                        <span className="text-xs text-text-muted">/</span>
                                                        <CurrencyInput placeholder="Rp" disabled={agreedFactor != null} value={data.lines[i].discount_amount} onChange={(e) => onDiscountAmount(i, line, e.target.value)} className="w-full rounded-lg border border-border px-2 py-1.5 text-right text-xs disabled:bg-bg" />
                                                    </div>
                                                    {c.discount > 0 && <div className="mt-0.5 text-right text-[10px] text-text-muted">−{money(c.discount)}{agreedFactor != null ? ' (dari Nilai DPP)' : ''}</div>}
                                                </td>
                                                <td className="min-w-36 px-3 py-3">
                                                    <select value={data.lines[i].tax_mode === 'custom' ? 'custom' : data.lines[i].tax_id} onChange={(e) => onTaxPick(i, e.target.value)} className="w-full rounded-lg border border-border px-1 py-1.5 text-[11px]">
                                                        <option value="">Tanpa pajak</option>
                                                        {taxes.map((t) => <option key={t.id} value={t.id}>{t.name} ({Number(t.rate)}%)</option>)}
                                                        <option value="custom">Pajak manual…</option>
                                                    </select>
                                                    <input
                                                        type="number" min="0" max="100" step="0.01"
                                                        value={data.lines[i].tax_mode === 'none' ? '' : data.lines[i].tax_rate}
                                                        onChange={(e) => setLine(i, { tax_rate: e.target.value })}
                                                        disabled={data.lines[i].tax_mode !== 'custom'}
                                                        placeholder={data.lines[i].tax_mode === 'none' ? '0%' : '%'}
                                                        className="mt-1 w-full rounded-lg border border-border px-2 py-1.5 text-right text-xs disabled:bg-bg disabled:text-text-muted"
                                                    />
                                                    {errors[`lines.${i}.tax_rate`] && <span className="text-xs text-danger">{errors[`lines.${i}.tax_rate`]}</span>}
                                                </td>
                                                <td className="px-3 py-3 text-right font-medium text-text">{money(c.dpp)}</td>
                                            </tr>
                                          </Fragment>
                                        );
                                    })}
                                </tbody>
                                <tfoot className="border-t border-border bg-surface-2 text-text">
                                    <tr><td colSpan="6" className="px-3 py-1.5 text-right text-text-muted">Subtotal Bruto</td><td className="px-3 py-1.5 text-right font-medium">{money(totals.gross)}</td></tr>
                                    <tr><td colSpan="6" className="px-3 py-1.5 text-right text-text-muted">Total Diskon{discPct > 0 ? ` (${discPct}%)` : ''}</td><td className="px-3 py-1.5 text-right font-medium text-danger">−{money(totals.discount)}</td></tr>
                                    <tr><td colSpan="6" className="px-3 py-1.5 text-right text-text-muted">DPP</td><td className="px-3 py-1.5 text-right font-medium">{money(totals.dpp)}</td></tr>
                                    <tr><td colSpan="6" className="px-3 py-1.5 text-right text-text-muted">Total PPN</td><td className="px-3 py-1.5 text-right font-medium">{money(totals.tax)}</td></tr>
                                    <tr><td colSpan="6" className="px-3 py-3 text-right font-semibold">Grand Total</td><td className="px-3 py-3 text-right text-lg font-bold">{money(grand)}</td></tr>
                                    {pph23Estimate > 0 && <>
                                        <tr><td colSpan="6" className="px-3 py-1.5 text-right text-xs text-text-muted">Estimasi PPh 23 (2%) — jika customer memotong</td><td className="px-3 py-1.5 text-right text-xs font-medium text-warning">−{money(pph23Estimate)}</td></tr>
                                        <tr><td colSpan="6" className="px-3 py-1.5 text-right text-xs text-text-muted">Estimasi diterima tunai</td><td className="px-3 py-1.5 text-right text-xs font-medium">{money(r2(grand - pph23Estimate))}</td></tr>
                                    </>}
                                    {marginPct != null && <tr><td colSpan="6" className="px-3 py-1.5 text-right text-xs text-text-muted">Estimasi margin keseluruhan</td><td className={`px-3 py-1.5 text-right text-xs font-medium ${marginPct < 0 ? 'text-danger' : 'text-success'}`}>{money(marginRp)} ({marginPct}%)</td></tr>}
                                </tfoot>
                            </table>
                        </div>
                    </section>

                    <div className="flex justify-end gap-3">
                        <Link href={editing ? `/sales/quotations/${quotation.id}` : `/sales/leads/${procurementRequest.lead_id}`} className="btn btn-outline">Batal</Link>
                        <button disabled={processing} className="btn btn-primary">{processing ? 'Menyimpan...' : 'Simpan Draft'}</button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}

function Alert({ text }) { return <div className="rounded-xl border border-danger/25 bg-danger-soft px-4 py-3 text-sm font-medium text-danger">{text}</div>; }
