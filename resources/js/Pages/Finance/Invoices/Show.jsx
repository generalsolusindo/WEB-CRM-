import { Head, router, useForm } from '@inertiajs/react';
import { FiFileText, FiSend, FiXCircle, FiHash } from 'react-icons/fi';
import AppLayout from '../../../Layouts/AppLayout';
import { Totals } from '../../Sales/Quotations/Show';
import { pickFile } from '../../../utils/fileValidation';
import { PageHeader, Card, CardHeader, Button, Field, Input, Info, InfoGrid, StatusBadge, CurrencyInput } from '../../../Components/ui';

function money(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(v || 0));
}

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

    function editNumber() {
        const value = window.prompt('Nomor invoice baru:', invoice.number ?? '');
        if (value && value.trim() !== '' && value.trim() !== invoice.number) {
            router.patch(`/finance/invoices/${invoice.id}/number`, { number: value.trim() }, { preserveScroll: true });
        }
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
                <PageHeader
                    title={(
                        <span className="flex flex-wrap items-center gap-3">
                            {invoice.number}
                            <StatusBadge status={invoice.status} />
                            <span className="badge badge-neutral uppercase">{invoice.invoice_phase}</span>
                        </span>
                    )}
                    subtitle={`${invoice.sales_order?.number ?? ''} · ${invoice.sales_order?.contact?.name ?? '—'}`}
                    back={{ href: '/finance/invoices', label: 'Kembali ke Invoice' }}
                    actions={(
                        <>
                            <Button href={`/finance/invoices/${invoice.id}/pdf`} external variant="outline" icon={FiFileText}>Lihat PDF</Button>
                            {permissions.updateNumber && <Button onClick={editNumber} variant="outline" icon={FiHash}>Ubah Nomor</Button>}
                            {permissions.sendWhatsapp && (
                                <Button
                                    onClick={sendWhatsapp}
                                    disabled={!customerHasWhatsapp}
                                    title={customerHasWhatsapp ? '' : 'Nomor WhatsApp customer belum ada di data Contact'}
                                    className="bg-success text-white hover:bg-success"
                                >
                                    {invoice.whatsapp_sent_at ? 'Kirim Ulang via WhatsApp' : 'Kirim via WhatsApp'}
                                </Button>
                            )}
                            {permissions.send && <Button onClick={() => act(`/finance/invoices/${invoice.id}/send`, 'Tandai invoice sudah dikirim (tanpa WA)?')} variant="outline" icon={FiSend}>Tandai Terkirim</Button>}
                            {permissions.cancel && <Button onClick={() => act(`/finance/invoices/${invoice.id}/cancel`, 'Batalkan invoice ini?')} variant="ghost" icon={FiXCircle} className="text-danger hover:bg-danger-soft hover:text-danger">Batalkan</Button>}
                        </>
                    )}
                />

                {invoice.whatsapp_sent_at && (
                    <p className="text-xs text-text-muted">Invoice terakhir dikirim ke customer via WhatsApp: {new Date(invoice.whatsapp_sent_at).toLocaleString('id-ID')}</p>
                )}

                {overdue && <div className="rounded-xl border border-danger/25 bg-danger-soft px-4 py-3 text-sm font-medium text-danger">Invoice sudah melewati jatuh tempo ({invoice.due_date}).</div>}

                <Card>
                    <InfoGrid cols={3}>
                        <Info label="Customer" value={invoice.sales_order?.contact?.name} />
                        <Info label="Perusahaan" value={invoice.sales_order?.contact?.company_name} />
                        <Info label="Jatuh Tempo" value={invoice.due_date} />
                        <Info label="Dibuat oleh" value={invoice.creator?.name} />
                        <Info label="Total Dibayar (kas)" value={money(totalPaid)} />
                        <Info label="Sisa" value={money(payable - totalPaid)} />
                    </InfoGrid>
                    {invoice.notes && (
                        <div className="mt-3 rounded-xl border border-border bg-surface-2 px-4 py-3 text-sm">
                            <span className="font-semibold text-text">Catatan Finance:</span> <span className="whitespace-pre-line text-text-muted">{invoice.notes}</span>
                        </div>
                    )}
                </Card>

                {settlement && (
                    <Card>
                        <CardHeader title="Rincian DP & Pelunasan" className="-mx-5 -mt-5 mb-4 px-5" />
                        <InfoGrid cols={3}>
                            <Info label="Nilai Kontrak (100%)" value={money(settlement.contract_payable)} />
                            <Info label={`Ditagih di invoice ini (DP ${settlement.dp_percent}%)`} value={money(settlement.dp_payable)} />
                            <Info label="Sisa — pelunasan setelah BAST" value={money(settlement.remaining)} />
                        </InfoGrid>
                        <p className="mt-2 text-xs text-text-muted">Invoice pelunasan dibuat setelah project Completed / BAST.</p>
                    </Card>
                )}

                {pph23 && !pph23.applies && pph23.can_toggle && (
                    <Card className="flex items-center justify-between gap-3">
                        <p className="text-sm text-text-muted">Invoice ini punya baris jasa. Kalau customer memotong PPh 23, aktifkan agar potongan 2% dihitung.</p>
                        <Button onClick={() => togglePph23(true)} variant="outline" className="shrink-0">Aktifkan PPh 23</Button>
                    </Card>
                )}

                {pph23 && pph23.applies && (
                    <Card>
                        <div className="mb-3 flex items-center justify-between">
                            <h2 className="font-semibold text-text">PPh 23 (Jasa)</h2>
                            {pph23.can_toggle && <button onClick={() => togglePph23(false)} className="text-xs font-medium text-danger">Nonaktifkan</button>}
                        </div>
                        <InfoGrid cols={4}>
                            <Info label="Rate" value={`${pph23.rate || 0}%`} />
                            <Info label="Dipotong" value={money(pph23.amount)} />
                            <Info label="Nilai Faktur" value={money(grandTotal)} />
                            <Info label="Dibayar Customer" value={money(pph23.payable)} />
                        </InfoGrid>
                        {pph23.bukti_potong_no && (
                            <p className="mt-2 text-sm text-text-muted">
                                Bukti potong: <span className="font-medium text-text">{pph23.bukti_potong_no}</span>
                                {pph23.recorded_by ? ` · dicatat ${pph23.recorded_by}` : ''}
                                {pph23.slip_url && <> · <a href={pph23.slip_url} target="_blank" rel="noreferrer" className="text-info">lihat file</a></>}
                            </p>
                        )}
                        {permissions.managePph23 && (
                            <form onSubmit={savePph23} className="mt-4 grid gap-3 rounded-xl border border-border bg-surface-2 p-4 sm:grid-cols-3">
                                {pph23.rate_editable && (
                                    <Field label="Rate PPh 23 (%)" error={pphForm.errors.rate}>
                                        <Input type="number" min="0" max="10" step="0.01" value={pphForm.data.rate} onChange={(e) => pphForm.setData('rate', e.target.value)} />
                                    </Field>
                                )}
                                <Field label="Nomor Bukti Potong" error={pphForm.errors.bukti_potong_no}>
                                    <Input value={pphForm.data.bukti_potong_no} onChange={(e) => pphForm.setData('bukti_potong_no', e.target.value)} placeholder="dari customer" />
                                </Field>
                                <Field label="File Bukti Potong" hint="maks 1 MB" error={pphForm.errors.slip}>
                                    <input type="file" accept=".pdf,.jpg,.jpeg,.png" onChange={(e) => pickFile(pphForm, 'slip', e.target.files[0], 1)} className="mt-1 block w-full text-sm text-text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-bg file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-text" />
                                </Field>
                                <div className="flex justify-end sm:col-span-3">
                                    <Button type="submit" loading={pphForm.processing}>Simpan PPh 23</Button>
                                </div>
                            </form>
                        )}
                    </Card>
                )}

                <Card padded={false}>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b border-border bg-surface-2 text-[11px] font-bold uppercase tracking-wider text-text-faint">
                                    <th className="px-4 py-3">Item</th>
                                    <th className="px-4 py-3">Qty</th>
                                    <th className="px-4 py-3 text-right">Harga</th>
                                    <th className="px-4 py-3 text-right">Diskon</th>
                                    <th className="px-4 py-3">Pajak</th>
                                    <th className="px-4 py-3 text-right">DPP</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {invoice.lines.map((l) => (
                                    <tr key={l.id}>
                                        <td className="px-4 py-3.5 font-medium text-text">{l.item_name}</td>
                                        <td className="px-4 py-3.5 text-text-muted">{l.qty}</td>
                                        <td className="px-4 py-3.5 text-right tabular-nums text-text-muted">{money(l.unit_price)}</td>
                                        <td className="px-4 py-3.5 text-right tabular-nums text-text-muted">{Number(l.discount_amount) > 0 ? money(l.discount_amount) : '—'}</td>
                                        <td className="px-4 py-3.5 text-text-muted">{l.tax ? l.tax.name : (Number(l.tax_rate) > 0 ? `${l.tax_rate}%` : '—')}</td>
                                        <td className="px-4 py-3.5 text-right font-medium tabular-nums text-text">{money(l.subtotal)}</td>
                                    </tr>
                                ))}
                            </tbody>
                            <Totals totals={totals} span={5} />
                        </table>
                    </div>
                </Card>

                <Card>
                    <h2 className="mb-3 font-semibold text-text">Riwayat Pembayaran</h2>
                    {payments.length === 0 ? (
                        <p className="text-sm text-text-muted">Belum ada pembayaran tercatat.</p>
                    ) : (
                        <div className="space-y-2">
                            {payments.map((p) => (
                                <div key={p.id} className="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-border p-3 text-sm">
                                    <div>
                                        <div className="font-medium text-text">{money(p.amount_paid)}</div>
                                        <div className="text-xs text-text-muted">{p.paid_at}{p.notes ? ` · ${p.notes}` : ''}</div>
                                    </div>
                                    {p.proofs.length === 0
                                        ? <span className="badge badge-warning">Bukti belum ada</span>
                                        : <span className="flex gap-2">{p.proofs.map((proof, i) => <a key={proof.id} href={proof.url} target="_blank" rel="noreferrer" className="rounded-lg border border-primary/30 px-3 py-1 text-xs font-semibold text-primary">Bukti {p.proofs.length > 1 ? i + 1 : ''}</a>)}</span>}
                                </div>
                            ))}
                        </div>
                    )}

                    {permissions.recordPayment && (
                        <form onSubmit={submitPayment} className="mt-5 space-y-4 rounded-xl border border-border bg-surface-2 p-4">
                            <h3 className="font-medium text-text">Catat Pembayaran</h3>
                            <div className="grid gap-4 sm:grid-cols-3">
                                <Field label="Jumlah" required error={payForm.errors.amount_paid}>
                                    <CurrencyInput value={payForm.data.amount_paid} onChange={(e) => payForm.setData('amount_paid', e.target.value)} className="input" />
                                </Field>
                                <Field label="Tanggal Bayar" required error={payForm.errors.paid_at}>
                                    <Input type="datetime-local" value={payForm.data.paid_at} onChange={(e) => payForm.setData('paid_at', e.target.value)} />
                                </Field>
                                <Field label="Bukti (pdf/jpg/png)" hint="maks 5 MB" error={payForm.errors.proof}>
                                    <input type="file" accept=".pdf,.jpg,.jpeg,.png" onChange={(e) => pickFile(payForm, 'proof', e.target.files[0], 5)} className="mt-1 block w-full text-sm text-text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-bg file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-text" />
                                </Field>
                            </div>
                            <Field label="Catatan">
                                <Input value={payForm.data.notes} onChange={(e) => payForm.setData('notes', e.target.value)} />
                            </Field>
                            <div className="flex justify-end">
                                <Button type="submit" loading={payForm.processing}>Catat</Button>
                            </div>
                        </form>
                    )}
                </Card>
            </div>
        </AppLayout>
    );
}
