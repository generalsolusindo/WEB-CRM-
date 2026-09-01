import { Head, Link, router, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { Totals } from '../../Sales/Quotations/Show';

function money(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(v || 0));
}

const statusBadge = {
    draft: 'bg-warning/10 text-warning',
    sent: 'bg-info/10 text-info',
    partially_paid: 'bg-info/10 text-info',
    paid: 'bg-success/10 text-success',
    overdue: 'bg-danger/10 text-danger',
    cancelled: 'bg-text-muted/10 text-text-muted',
};

export default function Show({ invoice, payments, totals, totalPaid, permissions }) {
    const grandTotal = totals.grand_total;
    const overdue = invoice.due_date && !['paid', 'cancelled'].includes(invoice.status)
        && new Date(invoice.due_date) < new Date(new Date().toDateString());

    const nowLocal = (() => {
        const d = new Date();
        d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
        return d.toISOString().slice(0, 16);
    })();
    const payForm = useForm({ amount_paid: '', paid_at: nowLocal, notes: '', proof: null });

    function act(url, msg) {
        if (confirm(msg)) router.post(url);
    }

    function submitPayment(e) {
        e.preventDefault();
        payForm.post(`/finance/invoices/${invoice.id}/payments`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => payForm.reset(),
        });
    }

    return (
        <AppLayout>
            <Head title={invoice.number} />
            <div className="mx-auto max-w-4xl space-y-5">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <Link href="/finance/invoices" className="text-sm text-info">← Kembali</Link>
                        <div className="mt-2 flex items-center gap-3">
                            <h1 className="text-2xl font-bold text-text">{invoice.number}</h1>
                            <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${statusBadge[invoice.status]}`}>{invoice.status}</span>
                            <span className="rounded-full bg-bg px-2.5 py-1 text-xs font-semibold uppercase text-text-muted">{invoice.invoice_phase}</span>
                        </div>
                        <p className="text-sm text-text-muted">
                            {invoice.sales_order?.number} · {invoice.sales_order?.contact?.name ?? '—'}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <a href={`/finance/invoices/${invoice.id}/print`} target="_blank" rel="noreferrer" className="rounded-lg border border-border px-4 py-2 text-sm">Cetak / PDF</a>
                        {permissions.send && <button onClick={() => act(`/finance/invoices/${invoice.id}/send`, 'Tandai invoice sudah dikirim ke customer?')} className="rounded-lg bg-info px-4 py-2 text-sm font-semibold text-white">Kirim</button>}
                        {permissions.cancel && <button onClick={() => act(`/finance/invoices/${invoice.id}/cancel`, 'Batalkan invoice ini?')} className="rounded-lg border border-danger/30 px-4 py-2 text-sm text-danger">Batalkan</button>}
                    </div>
                </div>

                {overdue && <div className="rounded-lg border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger">Invoice sudah melewati jatuh tempo ({invoice.due_date}).</div>}

                <section className="grid gap-4 rounded-xl border border-border bg-surface p-6 shadow-sm sm:grid-cols-3">
                    <Info label="Customer" value={invoice.sales_order?.contact?.name} />
                    <Info label="Perusahaan" value={invoice.sales_order?.contact?.company_name} />
                    <Info label="Jatuh Tempo" value={invoice.due_date || '—'} />
                    <Info label="Dibuat oleh" value={invoice.creator?.name} />
                    <Info label="Total Dibayar" value={money(totalPaid)} />
                    <Info label="Sisa" value={money(grandTotal - totalPaid)} />
                </section>

                <section className="overflow-hidden rounded-xl border border-border bg-surface shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-bg text-text-muted"><tr><th className="px-4 py-3">Item</th><th className="px-4 py-3">Qty</th><th className="px-4 py-3 text-right">Harga</th><th className="px-4 py-3 text-right">Diskon</th><th className="px-4 py-3">Pajak</th><th className="px-4 py-3 text-right">DPP</th></tr></thead>
                            <tbody className="divide-y divide-border">
                                {invoice.lines.map((l) => (
                                    <tr key={l.id}>
                                        <td className="px-4 py-3 text-text">{l.item_name}</td>
                                        <td className="px-4 py-3 text-text-muted">{l.qty}</td>
                                        <td className="px-4 py-3 text-right text-text-muted">{money(l.unit_price)}</td>
                                        <td className="px-4 py-3 text-right text-text-muted">{Number(l.discount_amount) > 0 ? money(l.discount_amount) : '—'}</td>
                                        <td className="px-4 py-3 text-text-muted">{l.tax ? l.tax.name : (Number(l.tax_rate) > 0 ? `${l.tax_rate}%` : '—')}</td>
                                        <td className="px-4 py-3 text-right text-text">{money(l.subtotal)}</td>
                                    </tr>
                                ))}
                            </tbody>
                            <Totals totals={totals} span={5} />
                        </table>
                    </div>
                </section>

                <section className="rounded-xl border border-border bg-surface p-6 shadow-sm">
                    <h2 className="mb-3 font-semibold text-text">Riwayat Pembayaran</h2>
                    {payments.length === 0 ? (
                        <p className="text-sm text-text-muted">Belum ada pembayaran tercatat.</p>
                    ) : (
                        <div className="space-y-2">
                            {payments.map((p) => (
                                <div key={p.id} className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border p-3 text-sm">
                                    <div>
                                        <div className="font-medium text-text">{money(p.amount_paid)}</div>
                                        <div className="text-xs text-text-muted">{p.paid_at}{p.notes ? ` · ${p.notes}` : ''}</div>
                                    </div>
                                    {p.proofs.length === 0
                                        ? <span className="rounded-full bg-warning/10 px-2.5 py-1 text-xs font-semibold text-warning">Bukti belum ada</span>
                                        : <span className="flex gap-2">{p.proofs.map((proof, i) => <a key={proof.id} href={proof.url} target="_blank" rel="noreferrer" className="rounded-lg border border-info/30 px-3 py-1 text-xs font-semibold text-info">Bukti {p.proofs.length > 1 ? i + 1 : ''}</a>)}</span>}
                                </div>
                            ))}
                        </div>
                    )}

                    {permissions.recordPayment && (
                        <form onSubmit={submitPayment} className="mt-5 space-y-4 rounded-lg border border-border bg-bg/50 p-4">
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
                                    <input type="file" accept=".pdf,.jpg,.jpeg,.png" onChange={(e) => payForm.setData('proof', e.target.files[0] ?? null)} className="mt-1 block w-full text-sm" />
                                    {payForm.errors.proof && <span className="text-xs text-danger">{payForm.errors.proof}</span>}
                                </label>
                            </div>
                            <label className="block text-sm font-medium text-text">Catatan
                                <input value={payForm.data.notes} onChange={(e) => payForm.setData('notes', e.target.value)} className="input" />
                            </label>
                            <div className="flex justify-end">
                                <button disabled={payForm.processing} className="rounded-lg bg-navy px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">
                                    {payForm.processing ? 'Menyimpan...' : 'Catat'}
                                </button>
                            </div>
                        </form>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}

function Info({ label, value }) {
    return <div><div className="text-xs font-semibold uppercase tracking-wide text-text-muted">{label}</div><div className="mt-1 text-sm text-text">{value || '—'}</div></div>;
}
