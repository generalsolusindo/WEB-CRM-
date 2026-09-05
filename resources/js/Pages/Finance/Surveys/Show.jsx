import { Head, Link, router, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { pickFile } from '../../../utils/fileValidation';

function money(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(v || 0));
}

export default function Show({ survey, invoice, payments, taxes = [], canHandle, canVoidInvoice = false }) {
    const nowLocal = (() => {
        const d = new Date();
        d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
        return d.toISOString().slice(0, 16);
    })();

    const grandTotal = invoice ? Number(invoice.grand_total) : 0;
    const remaining = invoice ? grandTotal - Number(invoice.total_paid) : 0;

    const issueForm = useForm({ due_date: '', tax_id: '', finance_note: '' });
    const clearForm = useForm({ finance_note: '' });
    const payForm = useForm({ amount_paid: remaining > 0 ? String(remaining) : '', paid_at: nowLocal, notes: '', proof: null });

    const taxPreview = (() => {
        const t = taxes.find((x) => String(x.id) === String(issueForm.data.tax_id));
        if (!t) return null;
        return Math.round(Number(survey.cost) * Number(t.rate)) / 100;
    })();

    function issue(e) { e.preventDefault(); issueForm.post(`/finance/surveys/${survey.id}/invoice`); }
    function clear(e) { e.preventDefault(); clearForm.post(`/finance/surveys/${survey.id}/clear`); }
    function voidInvoice() {
        const paid = Number(invoice.total_paid) > 0;
        const msg = paid
            ? `Ada pembayaran ${money(invoice.total_paid)} tercatat pada invoice ini. Membatalkan invoice TIDAK otomatis me-refund uang — proses refund manual di luar sistem. Lanjut batalkan?`
            : 'Batalkan invoice survey ini? Survey akan kembali ke tahap Finance.';
        if (confirm(msg)) router.post(`/finance/invoices/${invoice.id}/cancel`);
    }
    function pay(e) {
        e.preventDefault();
        payForm.post(`/finance/invoices/${invoice.id}/payments`, { forceFormData: true, preserveScroll: true, onSuccess: () => payForm.reset('notes', 'proof') });
    }

    return (
        <AppLayout>
            <Head title={survey.code} />
            <div className="mx-auto max-w-3xl space-y-5">
                <div>
                    <Link href="/finance/surveys" className="text-sm text-info">← Kembali</Link>
                    <div className="mt-2 flex items-center gap-3">
                        <h1 className="text-2xl font-bold text-text">{survey.code}</h1>
                        <span className="rounded-full bg-warning/10 px-2.5 py-1 text-xs font-semibold text-warning">{survey.status_label}</span>
                    </div>
                    <p className="text-sm text-text-muted">{survey.lead?.contact?.name} · {survey.lead?.contact?.company_name || 'Tanpa perusahaan'}</p>
                </div>

                <section className="grid gap-4 rounded-xl border border-border bg-surface p-6 shadow-sm sm:grid-cols-2">
                    <Info label="Lokasi" value={`${survey.site_region} — ${survey.site_address}`} />
                    <Info label="Pelaksana" value={survey.delivery_mode_label} />
                    <Info label="Tim Surveyor" value={(survey.surveyors ?? []).map((u) => u.name).join(', ') || '—'} />
                    <Info label="Vendor" value={survey.vendor?.name} />
                    <Info label="Biaya (pass-through)" value={money(survey.cost)} />
                    <Info label="Ditagih ke Customer" value={survey.billable ? 'Ya' : 'Tidak'} />
                    {survey.finance_note && <Info label="Catatan Finance" value={survey.finance_note} />}
                </section>

                {survey.billable ? (
                    !invoice ? (
                        canHandle && (
                            <form onSubmit={issue} className="space-y-4 rounded-xl border border-border bg-surface p-6 shadow-sm">
                                <h2 className="font-semibold text-text">Terbitkan Invoice Survey</h2>
                                <p className="text-sm text-text-muted">DPP: <span className="font-semibold text-text">{money(survey.cost)}</span> · nomor seri SRV.</p>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <label className="block text-sm font-medium text-text">PPN (opsional)
                                        <select value={issueForm.data.tax_id} onChange={(e) => issueForm.setData('tax_id', e.target.value)} className="input">
                                            <option value="">Tanpa PPN</option>
                                            {taxes.map((t) => <option key={t.id} value={t.id}>{t.name} ({Number(t.rate)}%)</option>)}
                                        </select>
                                        {issueForm.errors.tax_id && <span className="mt-1 block text-xs text-danger">{issueForm.errors.tax_id}</span>}
                                    </label>
                                    <label className="block text-sm font-medium text-text">Jatuh Tempo (opsional)
                                        <input type="date" value={issueForm.data.due_date} onChange={(e) => issueForm.setData('due_date', e.target.value)} className="input" />
                                    </label>
                                </div>
                                <label className="block text-sm font-medium text-text">Catatan biaya vendor (opsional)
                                    <input value={issueForm.data.finance_note} onChange={(e) => issueForm.setData('finance_note', e.target.value)} placeholder="mis. bayar vendor Rp X via transfer" className="input" />
                                </label>
                                <div className="rounded-lg bg-bg/60 p-3 text-sm text-text-muted">
                                    DPP {money(survey.cost)}{taxPreview !== null ? ` + PPN ${money(taxPreview)}` : ''} = <span className="font-semibold text-text">Total {money(Number(survey.cost) + (taxPreview || 0))}</span>
                                </div>
                                <div className="flex justify-end">
                                    <button disabled={issueForm.processing} className="rounded-lg bg-navy px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">Terbitkan Invoice</button>
                                </div>
                            </form>
                        )
                    ) : (
                        <section className="space-y-4 rounded-xl border border-border bg-surface p-6 shadow-sm">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <h2 className="font-semibold text-text">Invoice {invoice.number}</h2>
                                <div className="flex items-center gap-2">
                                    <a href={`/finance/invoices/${invoice.id}/pdf`} target="_blank" rel="noreferrer" className="rounded-lg border border-border px-3 py-1.5 text-xs font-semibold">Lihat / Cetak PDF</a>
                                    {canVoidInvoice && <button onClick={voidInvoice} className="rounded-lg border border-danger/30 px-3 py-1.5 text-xs font-semibold text-danger">Batalkan Invoice</button>}
                                    <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${invoice.status === 'paid' ? 'bg-success/10 text-success' : 'bg-info/10 text-info'}`}>{invoice.status}</span>
                                </div>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                <Info label={`DPP · ${invoice.tax_label}`} value={money(invoice.amount)} />
                                <Info label="Total Tagihan" value={money(invoice.grand_total)} />
                                <Info label="Terbayar" value={money(invoice.total_paid)} />
                                <Info label="Sisa" value={money(remaining)} />
                            </div>

                            {payments.length > 0 && (
                                <div className="space-y-2">
                                    {payments.map((p) => (
                                        <div key={p.id} className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border p-3 text-sm">
                                            <div>
                                                <div className="font-medium text-text">{money(p.amount_paid)}</div>
                                                <div className="text-xs text-text-muted">{p.paid_at}{p.notes ? ` · ${p.notes}` : ''}</div>
                                            </div>
                                            {p.proofs.length === 0
                                                ? <span className="rounded-full bg-warning/10 px-2.5 py-1 text-xs font-semibold text-warning">Bukti belum ada</span>
                                                : <span className="flex gap-2">{p.proofs.map((pr, i) => <a key={pr.id} href={pr.url} target="_blank" rel="noreferrer" className="rounded-lg border border-info/30 px-3 py-1 text-xs font-semibold text-info">Bukti {p.proofs.length > 1 ? i + 1 : ''}</a>)}</span>}
                                        </div>
                                    ))}
                                </div>
                            )}

                            {canHandle && invoice.status !== 'paid' && (
                                <form onSubmit={pay} className="space-y-4 rounded-lg border border-border bg-bg/50 p-4">
                                    <h3 className="font-medium text-text">Catat Pembayaran</h3>
                                    <div className="grid gap-4 sm:grid-cols-3">
                                        <label className="text-sm font-medium text-text">Jumlah *
                                            <input type="number" min="0" step="0.01" value={payForm.data.amount_paid} onChange={(e) => payForm.setData('amount_paid', e.target.value)} className="input" />
                                            {payForm.errors.amount_paid && <span className="text-xs text-danger">{payForm.errors.amount_paid}</span>}
                                        </label>
                                        <label className="text-sm font-medium text-text">Tanggal Bayar *
                                            <input type="datetime-local" value={payForm.data.paid_at} onChange={(e) => payForm.setData('paid_at', e.target.value)} className="input" />
                                            {payForm.errors.paid_at && <span className="text-xs text-danger">{payForm.errors.paid_at}</span>}
                                        </label>
                                        <label className="text-sm font-medium text-text">Bukti (pdf/jpg/png)
                                            <input type="file" accept=".pdf,.jpg,.jpeg,.png" onChange={(e) => pickFile(payForm, 'proof', e.target.files[0], 5)} className="mt-1 block w-full text-sm" />
                                            <span className="block text-[11px] text-text-muted">maks 5 MB</span>
                                            {payForm.errors.proof && <span className="text-xs text-danger">{payForm.errors.proof}</span>}
                                        </label>
                                    </div>
                                    <label className="block text-sm font-medium text-text">Catatan
                                        <input value={payForm.data.notes} onChange={(e) => payForm.setData('notes', e.target.value)} className="input" />
                                    </label>
                                    <div className="flex justify-end">
                                        <button disabled={payForm.processing} className="rounded-lg bg-navy px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Catat</button>
                                    </div>
                                </form>
                            )}
                            {invoice.status === 'paid' && <p className="rounded-lg bg-success/10 px-4 py-3 text-sm text-success">Invoice lunas. Survey sudah diteruskan ke Operasional.</p>}
                        </section>
                    )
                ) : (
                    canHandle && (
                        <form onSubmit={clear} className="space-y-4 rounded-xl border border-border bg-surface p-6 shadow-sm">
                            <h2 className="font-semibold text-text">Catat Biaya Vendor (tanpa tagihan customer)</h2>
                            <p className="text-sm text-text-muted">Survey ini tidak ditagihkan ke customer. Biaya {money(survey.cost)} dicatat sebagai pengeluaran vendor, lalu survey diteruskan ke Operasional.</p>
                            <label className="block text-sm font-medium text-text">Catatan (opsional)
                                <input value={clearForm.data.finance_note} onChange={(e) => clearForm.setData('finance_note', e.target.value)} className="input" />
                            </label>
                            <div className="flex justify-end">
                                <button disabled={clearForm.processing} className="rounded-lg bg-navy px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">Catat &amp; Teruskan</button>
                            </div>
                        </form>
                    )
                )}
            </div>
        </AppLayout>
    );
}

function Info({ label, value }) {
    return <div><div className="text-xs font-semibold uppercase tracking-wide text-text-muted">{label}</div><div className="mt-1 whitespace-pre-line text-sm text-text">{value || '—'}</div></div>;
}
