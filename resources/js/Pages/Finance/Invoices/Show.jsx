import { Head, Link, router, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { Totals } from '../../Sales/Quotations/Show';
import { pickFile } from '../../../utils/fileValidation';

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

export default function Show({ invoice, payments, totals, totalPaid, customerHasWhatsapp = false, pph23 = null, settlement = null, permissions }) {
    const grandTotal = totals.grand_total;
    const payable = pph23 && pph23.amount > 0 ? pph23.payable : grandTotal;

    const pphForm = useForm({ rate: pph23 ? String(pph23.rate || 2) : '2', bukti_potong_no: pph23?.bukti_potong_no ?? '', slip: null });
    function togglePph23(enabled) {
        router.post(`/finance/invoices/${invoice.id}/pph23`, { enabled, rate: pphForm.data.rate }, { preserveScroll: true });
    }
    function savePph23(e) {
        e.preventDefault();
        pphForm.post(`/finance/invoices/${invoice.id}/pph23`, { forceFormData: true, preserveScroll: true, onSuccess: () => pphForm.setData('slip', null) });
    }
    const overdue = invoice.due_date && !['paid', 'cancelled'].includes(invoice.status)
        && new Date(invoice.due_date) < new Date(new Date().toDateString());

    function sendWhatsapp() {
        router.post(`/finance/invoices/${invoice.id}/send-whatsapp`, {}, {
            preserveScroll: true,
            onSuccess: (page) => {
                const url = page.props.flash?.whatsappUrl;
                if (url) window.open(url, '_blank', 'noopener');
            },
        });
    }

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
                    <div className="flex flex-wrap gap-2">
                        <a href={`/finance/invoices/${invoice.id}/pdf`} target="_blank" rel="noreferrer" className="rounded-lg border border-border px-4 py-2 text-sm">Lihat PDF</a>
                        {permissions.sendWhatsapp && (
                            <button
                                onClick={sendWhatsapp}
                                disabled={!customerHasWhatsapp}
                                title={customerHasWhatsapp ? '' : 'Nomor WhatsApp customer belum ada di data Contact'}
                                className="rounded-lg bg-success px-4 py-2 text-sm font-semibold text-white disabled:opacity-40"
                            >
                                {invoice.whatsapp_sent_at ? 'Kirim Ulang via WhatsApp' : 'Kirim via WhatsApp'}
                            </button>
                        )}
                        {permissions.send && <button onClick={() => act(`/finance/invoices/${invoice.id}/send`, 'Tandai invoice sudah dikirim (tanpa WA)?')} className="rounded-lg border border-border px-4 py-2 text-sm">Tandai Terkirim</button>}
                        {permissions.cancel && <button onClick={() => act(`/finance/invoices/${invoice.id}/cancel`, 'Batalkan invoice ini?')} className="rounded-lg border border-danger/30 px-4 py-2 text-sm text-danger">Batalkan</button>}
                    </div>
                </div>

                {invoice.whatsapp_sent_at && (
                    <p className="text-xs text-text-muted">Invoice terakhir dikirim ke customer via WhatsApp: {new Date(invoice.whatsapp_sent_at).toLocaleString('id-ID')}</p>
                )}

                {overdue && <div className="rounded-lg border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger">Invoice sudah melewati jatuh tempo ({invoice.due_date}).</div>}

                <section className="grid gap-4 rounded-xl border border-border bg-surface p-6 shadow-sm sm:grid-cols-3">
                    <Info label="Customer" value={invoice.sales_order?.contact?.name} />
                    <Info label="Perusahaan" value={invoice.sales_order?.contact?.company_name} />
                    <Info label="Jatuh Tempo" value={invoice.due_date || '—'} />
                    <Info label="Dibuat oleh" value={invoice.creator?.name} />
                    <Info label="Total Dibayar (kas)" value={money(totalPaid)} />
                    <Info label="Sisa" value={money(payable - totalPaid)} />
                </section>

                {settlement && (
                    <section className="rounded-xl border border-border bg-surface p-6 shadow-sm">
                        <h2 className="font-semibold text-text">Rincian DP &amp; Pelunasan</h2>
                        <div className="mt-3 grid gap-4 sm:grid-cols-3">
                            <Info label="Nilai Kontrak (100%)" value={money(settlement.contract_payable)} />
                            <Info label={`Ditagih di invoice ini (DP ${settlement.dp_percent}%)`} value={money(settlement.dp_payable)} />
                            <Info label="Sisa — pelunasan setelah BAST" value={money(settlement.remaining)} />
                        </div>
                        <p className="mt-2 text-xs text-text-muted">Invoice pelunasan dibuat setelah project Completed / BAST.</p>
                    </section>
                )}

                {pph23 && !pph23.applies && pph23.can_toggle && (
                    <section className="rounded-xl border border-border bg-surface p-4 shadow-sm">
                        <div className="flex items-center justify-between gap-3">
                            <p className="text-sm text-text-muted">Invoice ini punya baris jasa. Kalau customer memotong PPh 23, aktifkan agar potongan 2% dihitung.</p>
                            <button onClick={() => togglePph23(true)} className="shrink-0 rounded-lg border border-navy px-3 py-1.5 text-sm font-semibold text-navy">Aktifkan PPh 23</button>
                        </div>
                    </section>
                )}

                {pph23 && pph23.applies && (
                    <section className="rounded-xl border border-border bg-surface p-6 shadow-sm">
                        <div className="flex items-center justify-between">
                            <h2 className="font-semibold text-text">PPh 23 (Jasa)</h2>
                            {pph23.can_toggle && <button onClick={() => togglePph23(false)} className="text-xs text-danger">Nonaktifkan</button>}
                        </div>
                        <div className="mt-3 grid gap-4 sm:grid-cols-4">
                            <Info label="Rate" value={`${pph23.rate || 0}%`} />
                            <Info label="Dipotong" value={money(pph23.amount)} />
                            <Info label="Nilai Faktur" value={money(grandTotal)} />
                            <Info label="Dibayar Customer" value={money(pph23.payable)} />
                        </div>
                        {pph23.bukti_potong_no && (
                            <p className="mt-2 text-sm text-text-muted">
                                Bukti potong: <span className="font-medium text-text">{pph23.bukti_potong_no}</span>
                                {pph23.recorded_by ? ` · dicatat ${pph23.recorded_by}` : ''}
                                {pph23.slip_url && <> · <a href={pph23.slip_url} target="_blank" rel="noreferrer" className="text-info">lihat file</a></>}
                            </p>
                        )}
                        {permissions.managePph23 && (
                            <form onSubmit={savePph23} className="mt-4 grid gap-3 rounded-lg border border-border bg-bg/50 p-4 sm:grid-cols-3">
                                {pph23.rate_editable && (
                                    <label className="text-sm font-medium text-text">Rate PPh 23 (%)
                                        <input type="number" min="0" max="10" step="0.01" value={pphForm.data.rate} onChange={(e) => pphForm.setData('rate', e.target.value)} className="input" />
                                        {pphForm.errors.rate && <span className="text-xs text-danger">{pphForm.errors.rate}</span>}
                                    </label>
                                )}
                                <label className="text-sm font-medium text-text">Nomor Bukti Potong
                                    <input value={pphForm.data.bukti_potong_no} onChange={(e) => pphForm.setData('bukti_potong_no', e.target.value)} className="input" placeholder="dari customer" />
                                    {pphForm.errors.bukti_potong_no && <span className="text-xs text-danger">{pphForm.errors.bukti_potong_no}</span>}
                                </label>
                                <label className="text-sm font-medium text-text">File Bukti Potong
                                    <input type="file" accept=".pdf,.jpg,.jpeg,.png" onChange={(e) => pickFile(pphForm, 'slip', e.target.files[0], 1)} className="mt-1 block w-full text-sm" />
                                    <span className="block text-[11px] text-text-muted">maks 1 MB</span>
                                    {pphForm.errors.slip && <span className="text-xs text-danger">{pphForm.errors.slip}</span>}
                                </label>
                                <div className="sm:col-span-3 flex justify-end">
                                    <button disabled={pphForm.processing} className="rounded-lg bg-navy px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Simpan PPh 23</button>
                                </div>
                            </form>
                        )}
                    </section>
                )}

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
                                    <input type="file" accept=".pdf,.jpg,.jpeg,.png" onChange={(e) => pickFile(payForm, 'proof', e.target.files[0], 5)} className="mt-1 block w-full text-sm" />
                                    <span className="block text-[11px] text-text-muted">maks 5 MB</span>
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
