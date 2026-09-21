import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { FiFileText } from 'react-icons/fi';
import AppLayout from '../../../Layouts/AppLayout';
import { pickFile } from '../../../utils/fileValidation';
import { PageHeader, Card, Button, ConfirmDialog, Field, Input, Select, Info, InfoGrid, StatusBadge, CurrencyInput } from '../../../Components/ui';
import { feedback } from '../../../Components/feedback';

function money(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(v || 0));
}

export default function Show({ survey, invoice, payments, taxes = [], canHandle, canVoidInvoice = false }) {
    const [voidOpen, setVoidOpen] = useState(false);
    const [voiding, setVoiding] = useState(false);
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

    function issue(e) {
        e.preventDefault();
        feedback.expect({ success: { title: 'Invoice survey diterbitkan', style: 'popup' } });
        issueForm.post(`/finance/surveys/${survey.id}/invoice`);
    }
    function clear(e) {
        e.preventDefault();
        feedback.expect({ success: { title: 'Biaya survey dicatat', style: 'popup' } });
        clearForm.post(`/finance/surveys/${survey.id}/clear`);
    }
    function voidInvoice() {
        setVoiding(true);
        feedback.expect({ success: { title: 'Invoice survey dibatalkan', style: 'popup' } });
        router.post(`/finance/invoices/${invoice.id}/cancel`, {}, {
            preserveScroll: true,
            onSuccess: () => setVoidOpen(false),
            onFinish: () => setVoiding(false),
        });
    }
    function pay(e) {
        e.preventDefault();
        feedback.expect({ success: { title: 'Pembayaran tercatat', style: 'popup' } });
        payForm.post(`/finance/invoices/${invoice.id}/payments`, { forceFormData: true, preserveScroll: true, onSuccess: () => payForm.reset('notes', 'proof') });
    }

    return (
        <AppLayout>
            <Head title={survey.code} />
            <div className="mx-auto max-w-3xl space-y-5">
                <PageHeader
                    title={<span className="flex items-center gap-3">{survey.code} <StatusBadge status={survey.status} label={survey.status_label} tone="warning" /></span>}
                    subtitle={`${survey.lead?.contact?.name ?? ''} · ${survey.lead?.contact?.company_name || 'Tanpa perusahaan'}`}
                    back={{ href: '/finance/surveys', label: 'Kembali ke Survey' }}
                />

                <Card>
                    <InfoGrid cols={2}>
                        <Info label="Lokasi" value={`${survey.site_region} — ${survey.site_address}`} />
                        <Info label="Pelaksana" value={survey.delivery_mode_label} />
                        <Info label="Tim Surveyor" value={(survey.surveyors ?? []).map((u) => u.name).join(', ')} />
                        <Info label="Vendor" value={survey.vendor?.name} />
                        <Info label="Biaya (pass-through)" value={money(survey.cost)} />
                        <Info label="Ditagih ke Customer" value={survey.billable ? 'Ya' : 'Tidak'} />
                        {survey.finance_note && <Info label="Catatan Finance" value={survey.finance_note} />}
                    </InfoGrid>
                </Card>

                {survey.billable ? (
                    !invoice ? (
                        canHandle && (
                            <Card>
                                <form onSubmit={issue} className="space-y-4">
                                    <h2 className="font-semibold text-text">Terbitkan Invoice Survey</h2>
                                    <p className="text-sm text-text-muted">DPP: <span className="font-semibold text-text">{money(survey.cost)}</span> · nomor seri SRV.</p>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Field label="PPN (opsional)" error={issueForm.errors.tax_id}>
                                            <Select value={issueForm.data.tax_id} onChange={(e) => issueForm.setData('tax_id', e.target.value)}>
                                                <option value="">Tanpa PPN</option>
                                                {taxes.map((t) => <option key={t.id} value={t.id}>{t.name} ({Number(t.rate)}%)</option>)}
                                            </Select>
                                        </Field>
                                        <Field label="Jatuh Tempo (opsional)">
                                            <Input type="date" value={issueForm.data.due_date} onChange={(e) => issueForm.setData('due_date', e.target.value)} />
                                        </Field>
                                    </div>
                                    <Field label="Catatan biaya vendor (opsional)">
                                        <Input value={issueForm.data.finance_note} onChange={(e) => issueForm.setData('finance_note', e.target.value)} placeholder="mis. bayar vendor Rp X via transfer" />
                                    </Field>
                                    <div className="rounded-xl bg-surface-2 p-3 text-sm text-text-muted">
                                        DPP {money(survey.cost)}{taxPreview !== null ? ` + PPN ${money(taxPreview)}` : ''} = <span className="font-semibold text-text">Total {money(Number(survey.cost) + (taxPreview || 0))}</span>
                                    </div>
                                    <div className="flex justify-end">
                                        <Button type="submit" loading={issueForm.processing}>Terbitkan Invoice</Button>
                                    </div>
                                </form>
                            </Card>
                        )
                    ) : (
                        <Card>
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <h2 className="font-semibold text-text">Invoice {invoice.number}</h2>
                                <div className="flex items-center gap-2">
                                    <Button href={`/finance/invoices/${invoice.id}/pdf`} external variant="outline" size="sm" icon={FiFileText}>Lihat / Cetak PDF</Button>
                                    {canVoidInvoice && <Button onClick={() => setVoidOpen(true)} variant="ghost" size="sm" className="text-danger hover:bg-danger-soft hover:text-danger">Batalkan Invoice</Button>}
                                    <StatusBadge status={invoice.status} />
                                </div>
                            </div>
                            <div className="mt-4">
                                <InfoGrid cols={4}>
                                    <Info label={`DPP · ${invoice.tax_label}`} value={money(invoice.amount)} />
                                    <Info label="Total Tagihan" value={money(invoice.grand_total)} />
                                    <Info label="Terbayar" value={money(invoice.total_paid)} />
                                    <Info label="Sisa" value={money(remaining)} />
                                </InfoGrid>
                            </div>

                            {payments.length > 0 && (
                                <div className="mt-4 space-y-2">
                                    {payments.map((p) => (
                                        <div key={p.id} className="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-border p-3 text-sm">
                                            <div>
                                                <div className="font-medium text-text">{money(p.amount_paid)}</div>
                                                <div className="text-xs text-text-muted">{p.paid_at}{p.notes ? ` · ${p.notes}` : ''}</div>
                                            </div>
                                            {p.proofs.length === 0
                                                ? <span className="badge badge-warning">Bukti belum ada</span>
                                                : <span className="flex gap-2">{p.proofs.map((pr, i) => <a key={pr.id} href={pr.url} target="_blank" rel="noreferrer" className="rounded-lg border border-primary/30 px-3 py-1 text-xs font-semibold text-primary">Bukti {p.proofs.length > 1 ? i + 1 : ''}</a>)}</span>}
                                        </div>
                                    ))}
                                </div>
                            )}

                            {canHandle && invoice.status !== 'paid' && (
                                <form onSubmit={pay} className="mt-4 space-y-4 rounded-xl border border-border bg-surface-2 p-4">
                                    <h3 className="font-medium text-text">Catat Pembayaran</h3>
                                    <div className="grid gap-4 sm:grid-cols-3">
                                        <Field label="Jumlah" required error={payForm.errors.amount_paid}>
                                            <CurrencyInput value={payForm.data.amount_paid} onChange={(e) => payForm.setData('amount_paid', e.target.value)} className="input" />
                                        </Field>
                                        <Field label="Tanggal Bayar" required error={payForm.errors.paid_at}>
                                            <Input type="datetime-local" value={payForm.data.paid_at} onChange={(e) => payForm.setData('paid_at', e.target.value)} />
                                        </Field>
                                        <Field label="Bukti (pdf/jpg/png)" hint="maks 5 MB" error={payForm.errors.proof}>
                                            <input type="file" accept=".pdf,.jpg,.jpeg,.png" onChange={(e) => pickFile(payForm, 'proof', e.target.files[0], 5)} className="mt-1 block w-full text-sm text-text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-primary-soft file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-primary-strong" />
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
                            {invoice.status === 'paid' && <p className="mt-4 rounded-xl bg-success-soft px-4 py-3 text-sm font-medium text-success">Invoice lunas. Survey sudah diteruskan ke Operasional.</p>}
                        </Card>
                    )
                ) : (
                    canHandle && (
                        <Card>
                            <form onSubmit={clear} className="space-y-4">
                                <h2 className="font-semibold text-text">Catat Biaya Vendor (tanpa tagihan customer)</h2>
                                <p className="text-sm text-text-muted">Survey ini tidak ditagihkan ke customer. Biaya {money(survey.cost)} dicatat sebagai pengeluaran vendor, lalu survey diteruskan ke Operasional.</p>
                                <Field label="Catatan (opsional)">
                                    <Input value={clearForm.data.finance_note} onChange={(e) => clearForm.setData('finance_note', e.target.value)} />
                                </Field>
                                <div className="flex justify-end">
                                    <Button type="submit" loading={clearForm.processing}>Catat &amp; Teruskan</Button>
                                </div>
                            </form>
                        </Card>
                    )
                )}
            </div>

            <ConfirmDialog
                open={voidOpen}
                onClose={() => setVoidOpen(false)}
                onConfirm={voidInvoice}
                title="Batalkan invoice survey?"
                description={Number(invoice?.total_paid) > 0
                    ? `Pembayaran ${money(invoice.total_paid)} sudah tercatat. Pembatalan invoice tidak mengembalikan uang secara otomatis; refund harus diproses di luar sistem.`
                    : 'Invoice akan dibatalkan dan survey dikembalikan ke tahap Finance.'}
                tone="danger"
                confirmLabel="Batalkan Invoice"
                processing={voiding}
            />
        </AppLayout>
    );
}
