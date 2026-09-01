import { Head, Link, useForm } from '@inertiajs/react';
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
        selling_price: line.selling_price ?? suggestedPrice(line.cost_price),
        discount_mode: dp && dp > 0 ? 'percent' : (da > 0 ? 'amount' : 'percent'),
        discount_percent: dp && dp > 0 ? String(dp) : '',
        discount_amount: da > 0 ? String(da) : '',
        tax_mode: taxMode,
        tax_id: taxMode === 'master' ? String(taxId) : '',
        tax_rate: taxMode === 'none' ? '' : (rate != null ? String(rate) : ''),
    };
}

export default function Form({ procurementRequest = null, quotation = null, taxes = [], surveyCredit = 0 }) {
    const editing = Boolean(quotation);
    const sourceLines = editing ? quotation.lines : procurementRequest.lines;
    const customer = editing ? quotation.contact : procurementRequest.lead.contact;

    const { data, setData, post, put, processing, errors, transform } = useForm({
        valid_until: quotation?.valid_until ?? '',
        notes: quotation?.notes ?? '',
        lines: sourceLines.map((l) => initialLine(l, taxes)),
    });

    transform((payload) => ({
        ...payload,
        lines: payload.lines.map((l) => ({
            procurement_request_line_id: l.procurement_request_line_id,
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

    function calc(line, i) {
        const d = data.lines[i];
        const gross = r2(Number(line.qty) * Number(d.selling_price || 0));
        let discount = 0;
        if (d.discount_mode === 'percent' && d.discount_percent !== '') discount = r2(gross * Number(d.discount_percent) / 100);
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
        return acc;
    }, { gross: 0, discount: 0, dpp: 0, tax: 0, cost: 0 });
    const credit = r2(Math.max(Number(surveyCredit) || 0, 0));
    const grand = r2(totals.dpp + totals.tax - credit);
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
                <div>
                    <Link href={editing ? `/sales/quotations/${quotation.id}` : `/sales/leads/${procurementRequest.lead_id}`} className="text-sm text-info">← Kembali</Link>
                    <h1 className="mt-2 text-2xl font-bold text-text">{editing ? `Edit Quotation R${quotation.revision_number}` : 'Buat Quotation'}</h1>
                    <p className="text-sm text-text-muted">{customer.name} · {customer.company_name || 'Tanpa perusahaan'}</p>
                </div>

                <form onSubmit={submit} className="space-y-5">
                    {errors.procurement_request && <Alert text={errors.procurement_request} />}
                    {errors.quotation && <Alert text={errors.quotation} />}
                    {errors.lines && <Alert text={errors.lines} />}

                    <section className="rounded-xl border border-border bg-surface p-5 shadow-sm">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <label className="text-sm font-medium text-text">Valid Until
                                <input type="date" value={data.valid_until} onChange={(e) => setData('valid_until', e.target.value)} className="input" />
                                {errors.valid_until && <span className="text-xs text-danger">{errors.valid_until}</span>}
                            </label>
                            <label className="text-sm font-medium text-text">Catatan
                                <textarea rows="2" value={data.notes} onChange={(e) => setData('notes', e.target.value)} className="input" />
                            </label>
                        </div>
                    </section>

                    <section className="overflow-hidden rounded-xl border border-border bg-surface shadow-sm">
                        <div className="border-b border-border p-5">
                            <h2 className="font-semibold text-text">Line Items</h2>
                            <p className="text-sm text-text-muted">Item, qty & cost dari Procurement. Isi selling price, diskon (% atau Rp), dan pajak per baris.</p>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-bg text-text-muted">
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
                                    {sourceLines.map((line, i) => {
                                        const c = calc(line, i);
                                        return (
                                            <tr key={line.id}>
                                                <td className="px-3 py-3">
                                                    <div className="font-medium text-text">{line.item_name}</div>
                                                    <div className="text-xs text-text-muted">{line.description || '—'}</div>
                                                    <div className="mt-1 text-xs">
                                                        <span className={c.markup != null && c.markup < 0 ? 'text-danger' : 'text-text-muted'}>Markup {c.markup == null ? '—' : `${c.markup.toFixed(1)}%`}</span>
                                                        {' · '}
                                                        <span className={c.margin != null && c.margin < 0 ? 'text-danger' : 'text-success'}>Margin efektif {c.margin == null ? '—' : `${c.margin.toFixed(1)}%`}</span>
                                                    </div>
                                                </td>
                                                <td className="whitespace-nowrap px-3 py-3 text-text-muted">{line.qty} {line.unit}</td>
                                                <td className="px-3 py-3 text-right text-text-muted">{money(line.cost_price)}</td>
                                                <td className="min-w-36 px-3 py-3">
                                                    <input type="number" min="0" step="0.01" value={data.lines[i].selling_price} onChange={(e) => setLine(i, { selling_price: e.target.value })} className="w-full rounded-lg border border-border px-2 py-1.5 text-right outline-none focus:border-navy" />
                                                    {errors[`lines.${i}.selling_price`] && <span className="text-xs text-danger">{errors[`lines.${i}.selling_price`]}</span>}
                                                </td>
                                                <td className="min-w-40 px-3 py-3">
                                                    <div className="flex items-center gap-1">
                                                        <input type="number" min="0" max="100" step="0.01" placeholder="%" value={data.lines[i].discount_percent} onChange={(e) => onDiscountPercent(i, line, e.target.value)} className="w-16 rounded-lg border border-border px-2 py-1.5 text-right text-xs" />
                                                        <span className="text-xs text-text-muted">/</span>
                                                        <input type="number" min="0" step="0.01" placeholder="Rp" value={data.lines[i].discount_amount} onChange={(e) => onDiscountAmount(i, line, e.target.value)} className="w-full rounded-lg border border-border px-2 py-1.5 text-right text-xs" />
                                                    </div>
                                                    {c.discount > 0 && <div className="mt-0.5 text-right text-[10px] text-text-muted">−{money(c.discount)}</div>}
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
                                        );
                                    })}
                                </tbody>
                                <tfoot className="border-t border-border bg-bg text-text">
                                    <tr><td colSpan="6" className="px-3 py-1.5 text-right text-text-muted">Subtotal Bruto</td><td className="px-3 py-1.5 text-right font-medium">{money(totals.gross)}</td></tr>
                                    <tr><td colSpan="6" className="px-3 py-1.5 text-right text-text-muted">Total Diskon{discPct > 0 ? ` (${discPct}%)` : ''}</td><td className="px-3 py-1.5 text-right font-medium text-danger">−{money(totals.discount)}</td></tr>
                                    <tr><td colSpan="6" className="px-3 py-1.5 text-right text-text-muted">DPP</td><td className="px-3 py-1.5 text-right font-medium">{money(totals.dpp)}</td></tr>
                                    <tr><td colSpan="6" className="px-3 py-1.5 text-right text-text-muted">Total PPN</td><td className="px-3 py-1.5 text-right font-medium">{money(totals.tax)}</td></tr>
                                    {credit > 0 && <tr><td colSpan="6" className="px-3 py-1.5 text-right text-text-muted">Kredit Biaya Survey</td><td className="px-3 py-1.5 text-right font-medium text-danger">−{money(credit)}</td></tr>}
                                    <tr><td colSpan="6" className="px-3 py-3 text-right font-semibold">Grand Total</td><td className="px-3 py-3 text-right text-lg font-bold">{money(grand)}</td></tr>
                                    {marginPct != null && <tr><td colSpan="6" className="px-3 py-1.5 text-right text-xs text-text-muted">Estimasi margin keseluruhan</td><td className={`px-3 py-1.5 text-right text-xs font-medium ${marginPct < 0 ? 'text-danger' : 'text-success'}`}>{money(marginRp)} ({marginPct}%)</td></tr>}
                                </tfoot>
                            </table>
                        </div>
                    </section>

                    <div className="flex justify-end gap-3">
                        <Link href={editing ? `/sales/quotations/${quotation.id}` : `/sales/leads/${procurementRequest.lead_id}`} className="rounded-lg border border-border px-4 py-2 text-sm">Batal</Link>
                        <button disabled={processing} className="rounded-lg bg-navy px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">{processing ? 'Menyimpan...' : 'Simpan Draft'}</button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}

function Alert({ text }) { return <div className="rounded-lg border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger">{text}</div>; }
