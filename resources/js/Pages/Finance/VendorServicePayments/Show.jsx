import { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, Card, Button, Field, Input, Textarea, Modal, Info, InfoGrid, StatusBadge } from '../../../Components/ui';
import { feedback } from '../../../Components/feedback';

function money(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(v || 0));
}

const KIND_LABEL = { dp: 'DP', final: 'Pelunasan' };

function PayForm({ payment, kind, amount }) {
    const form = useForm({ kind, paid_at: '', notes: '', proof: null });

    async function submit(e) {
        e.preventDefault();
        const ok = await feedback.confirm({
            tone: 'question',
            title: `Catat pembayaran ${KIND_LABEL[kind]}?`,
            text: `${money(amount)} ke ${payment.bank_name} ${payment.account_number} a.n. ${payment.account_holder} akan dicatat sebagai sudah ditransfer.`,
            confirmLabel: 'Ya, sudah ditransfer',
        });
        if (!ok) return;
        feedback.expect({
            success: { title: kind === 'dp' ? 'DP tercatat' : 'Pelunasan tercatat', style: 'popup' },
            error: { title: 'Pembayaran belum tercatat' },
        });
        form.post(`/finance/vendor-service-payments/${payment.id}/pay`, { forceFormData: true, preserveScroll: true });
    }

    return (
        <form onSubmit={submit} className="space-y-4 rounded-xl border border-border bg-surface-2 p-4">
            <h3 className="font-medium text-text">Catat Pembayaran {KIND_LABEL[kind]}</h3>
            {form.errors.kind && <p className="text-sm font-medium text-danger">{form.errors.kind}</p>}
            <div className="grid gap-4 sm:grid-cols-3">
                <div>
                    <div className="text-sm font-medium text-text">Jumlah (sesuai deal Procurement)</div>
                    <div className="mt-1 rounded-lg border border-border bg-bg px-3 py-2 text-lg font-bold text-text">{money(amount)}</div>
                </div>
                <Field label="Tanggal Bayar" required error={form.errors.paid_at}>
                    <Input type="datetime-local" value={form.data.paid_at} onChange={(e) => form.setData('paid_at', e.target.value)} />
                </Field>
                <Field label="Bukti Transfer (pdf/jpg/png)" required hint="maks 5 MB" error={form.errors.proof}>
                    <input type="file" accept=".pdf,.jpg,.jpeg,.png" onChange={(e) => form.setData('proof', e.target.files[0] ?? null)} className="mt-1 block w-full text-sm text-text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-primary-soft file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-primary-strong" />
                </Field>
            </div>
            <Field label="Catatan" error={form.errors.notes}>
                <Textarea rows={2} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
            </Field>
            <div className="flex justify-end">
                <Button type="submit" loading={form.processing}>Catat {KIND_LABEL[kind]}</Button>
            </div>
        </form>
    );
}

export default function Show({ payment, entries, cancelledEntries = [], canPayDp, canPayFinal, canCancel }) {
    const [target, setTarget] = useState(null);
    const cancelForm = useForm({ reason: '' });

    function cancel(e) {
        e.preventDefault();
        feedback.expect({ success: { title: 'Pembayaran vendor dibatalkan', style: 'popup' }, error: { title: 'Pembayaran gagal dibatalkan' } });
        cancelForm.post(`/finance/vendor-service-payments/${payment.id}/entries/${target.id}/cancel`, {
            preserveScroll: true,
            onSuccess: () => { setTarget(null); cancelForm.reset(); },
        });
    }

    return (
        <AppLayout>
            <Head title={`Vendor ${payment.number}`} />
            <div className="mx-auto max-w-4xl space-y-5">
                <PageHeader
                    title={<span className="flex flex-wrap items-center gap-3">Vendor {payment.number} <StatusBadge status={payment.status} label={payment.status_label} /></span>}
                    subtitle={`${payment.project_number} · ${payment.customer} · ${payment.sales_order}`}
                    back={{ href: '/finance/vendor-service-payments', label: 'Kembali' }}
                />

                <Card>
                    <InfoGrid cols={3}>
                        <Info label="Vendor" value={payment.vendor.name} />
                        <Info label="Kontak Vendor" value={[payment.vendor.contact_person, payment.vendor.phone].filter(Boolean).join(' · ')} />
                        <Info label="Total Fee" value={money(payment.total_fee)} />
                        <Info label="Termin" value={payment.terms === 'dp_final'
                            ? `DP ${money(payment.dp_amount)} (${payment.dp_percent}%) + pelunasan ${money(payment.final_amount)} setelah BAST`
                            : 'Lunas setelah BAST'} />
                        <Info label="Diinput Procurement" value={payment.submitted_by} />
                        <Info label="BAST" value={payment.bast_verified ? 'Sudah diverifikasi' : 'Belum diverifikasi'} />
                    </InfoGrid>
                    <div className="mt-4 rounded-xl border border-border bg-bg px-4 py-3 text-sm">
                        <div className="text-xs font-semibold uppercase tracking-wide text-text-muted">Rekening Tujuan</div>
                        <div className="mt-1 font-medium text-text">{payment.bank_name} · {payment.account_number}</div>
                        <div className="text-text-muted">a.n. {payment.account_holder}</div>
                    </div>
                    {payment.notes && <p className="mt-3 text-sm text-text-muted">Catatan Procurement: {payment.notes}</p>}
                </Card>

                <Card>
                    <h2 className="mb-3 font-semibold text-text">Riwayat Pembayaran</h2>
                    {entries.length === 0 ? (
                        <p className="text-sm text-text-muted">Belum ada pembayaran tercatat.</p>
                    ) : (
                        <div className="space-y-2">
                            {entries.map((e) => (
                                <div key={e.id} className="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-border p-3 text-sm">
                                    <div>
                                        <div className="font-medium text-text">{KIND_LABEL[e.kind]} · {money(e.amount)}</div>
                                        <div className="text-xs text-text-muted">{e.paid_at}{e.paid_by ? ` · ${e.paid_by}` : ''}{e.notes ? ` · ${e.notes}` : ''}</div>
                                    </div>
                                    <span className="flex gap-2">
                                        {e.proofs.map((proof, i) => (
                                            <a key={proof.id} href={proof.url} target="_blank" rel="noreferrer" className="rounded-lg border border-primary/30 px-3 py-1 text-xs font-semibold text-primary">Bukti {e.proofs.length > 1 ? i + 1 : ''}</a>
                                        ))}
                                    </span>
                                    {canCancel && (
                                        <button type="button" onClick={() => { cancelForm.clearErrors(); setTarget(e); }} className="text-xs font-semibold text-danger hover:underline">Batalkan pembayaran</button>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}

                    {cancelledEntries.length > 0 && (
                        <details className="mt-4 rounded-xl border border-border p-3 text-sm text-text-muted">
                            <summary className="cursor-pointer font-medium">Riwayat pembatalan ({cancelledEntries.length}) — tidak dihitung</summary>
                            {cancelledEntries.map((e) => (
                                <div key={e.id} className="mt-3 border-t border-border pt-3">
                                    <p className="font-medium">{KIND_LABEL[e.kind]} · {money(e.amount)} · Dibatalkan</p>
                                    <p>{e.cancelled_at} · {e.cancelled_by ?? 'Finance'}</p>
                                    <p className="break-words">Alasan: {e.reason}</p>
                                </div>
                            ))}
                        </details>
                    )}
                </Card>

                {canPayDp && <PayForm payment={payment} kind="dp" amount={payment.dp_amount} />}
                {canPayFinal && <PayForm payment={payment} kind="final" amount={payment.final_amount} />}
                {!canPayFinal && payment.status === 'in_progress' && !payment.bast_verified && (
                    <div className="rounded-xl border border-warning/25 bg-warning-soft px-4 py-3 text-sm font-medium text-warning">
                        Pelunasan ({money(payment.final_amount)}) belum bisa dibayar — menunggu BAST diverifikasi Operasional.
                    </div>
                )}
            </div>

            <Modal open={target !== null} onClose={() => setTarget(null)} title="Batalkan pembayaran vendor" size="sm">
                <form onSubmit={cancel} className="space-y-4">
                    <p className="text-sm">Batalkan {target ? KIND_LABEL[target.kind] : ''} <strong>{money(target?.amount)}</strong> untuk {payment.number}? Nominal dan bukti tetap tersimpan sebagai audit. Status deal dihitung ulang.</p>
                    <Field label="Alasan pembatalan" required error={cancelForm.errors.reason}>
                        <Textarea rows={3} value={cancelForm.data.reason} onChange={(e) => cancelForm.setData('reason', e.target.value)} />
                    </Field>
                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="outline" onClick={() => setTarget(null)}>Batal</Button>
                        <Button type="submit" variant="danger" loading={cancelForm.processing}>Batalkan pembayaran ini</Button>
                    </div>
                </form>
            </Modal>
        </AppLayout>
    );
}
