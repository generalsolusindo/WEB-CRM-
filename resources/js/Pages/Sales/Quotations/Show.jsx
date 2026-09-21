import { Fragment, useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FiPrinter, FiEdit2, FiSend, FiCheck, FiX, FiCopy, FiTrash2, FiHash, FiRefreshCw } from 'react-icons/fi';
import AppLayout from '../../../Layouts/AppLayout';
import CategoryBadge from '../../../Components/CategoryBadge';
import { PageHeader, Card, CardHeader, Button, ConfirmDialog, Field, Info, InfoGrid, Modal, PromptDialog, StatusBadge, Textarea } from '../../../Components/ui';
import { feedback } from '../../../Components/feedback';
import TableScroll from '../../../Components/ui/TableScroll';
import TotalsSummary from '../../../Components/ui/TotalsSummary';

export default function Show({ quotation, history, totals, permissions, customerHasWhatsapp = false }) {
    const number = quotation.number ?? `QT-${String(quotation.id).padStart(6, '0')} / R${quotation.revision_number}`;
    const [numberOpen, setNumberOpen] = useState(false);
    const [numberProcessing, setNumberProcessing] = useState(false);
    const [numberError, setNumberError] = useState('');
    const [confirmation, setConfirmation] = useState(null);
    const [actionProcessing, setActionProcessing] = useState(false);
    const [cancelOpen, setCancelOpen] = useState(false);
    const cancelForm = useForm({ reason: '' });
    const needsCorrection = quotation.pm_review_status === 'rejected' || quotation.manager_review_status === 'rejected';
    const pricingPending = quotation.procurement_request.status === 'ready' && quotation.quoted_at === null;

    function editNumber(value) {
        if (value === quotation.number) {
            setNumberOpen(false);
            return;
        }
        setNumberError('');
        setNumberProcessing(true);
        router.patch(`/sales/quotations/${quotation.id}/number`, { number: value }, {
            preserveScroll: true,
            onSuccess: () => setNumberOpen(false),
            onError: (errors) => setNumberError(errors.number ?? 'Nomor quotation gagal diubah.'),
            onFinish: () => setNumberProcessing(false),
        });
    }
    const validUntil = quotation.valid_until
        ? new Date(quotation.valid_until).toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' })
        : null;
    const orderedLines = [...quotation.lines].sort(
        (a, b) => (a.category === 'material' ? 0 : 1) - (b.category === 'material' ? 0 : 1),
    );
    function askAction(config) {
        setConfirmation(config);
    }

    function runAction() {
        if (!confirmation) return;
        setActionProcessing(true);
        const method = confirmation.method ?? 'post';
        feedback.expect({ success: { style: 'popup', ...confirmation.success } });
        router[method](confirmation.path, confirmation.data ?? {}, {
            preserveScroll: true,
            onSuccess: () => setConfirmation(null),
            onFinish: () => setActionProcessing(false),
        });
    }

    function askDestroy() {
        askAction({
            title: 'Hapus draft quotation permanen?',
            description: `Draft ${number}, seluruh baris, hasil review internal, dan notifikasi terkait akan dihapus permanen. Procurement Request tetap tersimpan agar hasil sourcing dapat digunakan kembali.`,
            confirmLabel: 'Hapus Permanen',
            tone: 'danger',
            method: 'delete',
            path: `/sales/quotations/${quotation.id}`,
            success: { title: 'Draft quotation dihapus' },
        });
    }

    function sendWhatsapp() {
        router.post(`/sales/quotations/${quotation.id}/send-whatsapp`, {}, {
            preserveScroll: true,
            onSuccess: (page) => {
                const url = page.props.flash?.whatsappUrl;
                if (url) window.open(url, '_blank', 'noopener');
            },
        });
    }

    function closeCancellation() {
        if (cancelForm.processing) return;
        setCancelOpen(false);
        cancelForm.reset();
        cancelForm.clearErrors();
    }

    function cancelTransaction(event) {
        event.preventDefault();
        feedback.expect({ success: { title: 'Transaksi dibatalkan', style: 'popup' } });
        cancelForm.post(`/sales/quotations/${quotation.id}/cancel`, {
            preserveScroll: true,
            onSuccess: closeCancellation,
        });
    }

    return (
        <AppLayout>
            <Head title={number} />
            <div className="mx-auto max-w-6xl space-y-5">
                <PageHeader
                    title={(
                        <span className="flex flex-wrap items-center gap-3">
                            {number} <StatusBadge status={quotation.status} />
                            {quotation.is_addendum && <span className="rounded-full bg-info-soft px-2.5 py-1 text-xs font-semibold text-info">Tambahan (Addendum)</span>}
                        </span>
                    )}
                    subtitle={`${quotation.contact.name} · ${quotation.contact.company_name || 'Tanpa perusahaan'}`}
                    back={{ href: '/sales/quotations', label: 'Kembali ke Quotations' }}
                    actions={
                        <>
                            <Button href={`/sales/quotations/${quotation.id}/print`} external variant="outline" icon={FiPrinter}>Cetak / PDF</Button>
                            {permissions.updateNumber && <Button onClick={() => { setNumberError(''); setNumberOpen(true); }} variant="outline" icon={FiHash}>Ubah Nomor</Button>}
                            {permissions.update && <Button href={`/sales/quotations/${quotation.id}/edit`} variant={needsCorrection || pricingPending ? 'primary' : 'outline'} icon={FiEdit2}>{pricingPending ? 'Lengkapi Harga Jual' : needsCorrection ? 'Perbaiki Quotation' : 'Edit'}</Button>}
                            {permissions.reviseScope && <Button href={`/sales/quotations/${quotation.id}/scope-revision`} variant="outline" icon={FiRefreshCw}>Revisi Kebutuhan</Button>}
                            {permissions.sendWhatsapp && (
                                <Button
                                    onClick={sendWhatsapp}
                                    disabled={!customerHasWhatsapp}
                                    title={customerHasWhatsapp ? '' : 'Nomor WhatsApp customer belum ada di data Contact'}
                                    className="bg-success text-white hover:bg-success"
                                >
                                    {quotation.whatsapp_sent_at ? 'Kirim Ulang via WhatsApp' : 'Kirim via WhatsApp'}
                                </Button>
                            )}
                            {permissions.send && <Button onClick={() => askAction({ title: 'Tandai quotation sebagai terkirim?', description: 'Status quotation akan diperbarui menjadi sudah dikirim ke customer.', confirmLabel: 'Tandai Terkirim', tone: 'info', path: `/sales/quotations/${quotation.id}/send`, success: { title: 'Quotation ditandai terkirim' } })} icon={FiSend}>Tandai Terkirim</Button>}
                            {permissions.confirm && <Button href={`/sales/quotations/${quotation.id}/confirm`} icon={FiCheck}>Confirm Deal</Button>}
                            {permissions.reject && <Button onClick={() => askAction({ title: 'Tandai quotation sebagai ditolak?', description: 'Status quotation akan berubah menjadi ditolak oleh customer.', confirmLabel: 'Tandai Ditolak', tone: 'danger', path: `/sales/quotations/${quotation.id}/reject`, success: { title: 'Quotation ditandai ditolak' } })} variant="ghost" icon={FiX} className="text-danger hover:bg-danger-soft hover:text-danger">Tandai Ditolak</Button>}
                            {permissions.revise && <Button onClick={() => askAction({ title: 'Buat revisi quotation?', description: 'Sistem akan membuat revisi baru berdasarkan data quotation ini.', confirmLabel: 'Buat Revisi', tone: 'info', path: `/sales/quotations/${quotation.id}/revisions`, success: { title: 'Revisi quotation dibuat' } })} variant="outline" icon={FiCopy}>Buat Revisi</Button>}
                            {permissions.cancel && <Button onClick={() => setCancelOpen(true)} variant="ghost" icon={FiX} className="text-danger hover:bg-danger-soft hover:text-danger">Batalkan Transaksi</Button>}
                            {permissions.delete && <Button onClick={askDestroy} variant="ghost" icon={FiTrash2} className="text-danger hover:bg-danger-soft hover:text-danger">Hapus</Button>}
                        </>
                    }
                />

                <PromptDialog
                    open={numberOpen}
                    onClose={() => setNumberOpen(false)}
                    onConfirm={editNumber}
                    title="Ubah nomor quotation"
                    description="Masukkan nomor quotation yang baru. Nomor harus unik."
                    label="Nomor quotation"
                    initialValue={quotation.number ?? ''}
                    required
                    maxLength={100}
                    error={numberError}
                    processing={numberProcessing}
                    confirmLabel="Simpan Nomor"
                />

                <ConfirmDialog
                    open={Boolean(confirmation)}
                    onClose={() => setConfirmation(null)}
                    onConfirm={runAction}
                    title={confirmation?.title}
                    description={confirmation?.description}
                    tone={confirmation?.tone}
                    confirmLabel={confirmation?.confirmLabel}
                    processing={actionProcessing}
                />

                <Modal
                    open={cancelOpen}
                    onClose={closeCancellation}
                    busy={cancelForm.processing}
                    title="Batalkan transaksi?"
                    description="Quotation beserta revisi, Sales Order, dan invoice yang belum dibayar akan ditandai dibatalkan. Riwayat transaksi tetap tersimpan."
                    footer={(
                        <>
                            <Button type="button" variant="outline" onClick={closeCancellation} disabled={cancelForm.processing}>Kembali</Button>
                            <Button type="submit" form="cancel-transaction-form" variant="danger" disabled={cancelForm.processing}>
                                {cancelForm.processing ? 'Membatalkan...' : 'Batalkan Transaksi'}
                            </Button>
                        </>
                    )}
                >
                    <form id="cancel-transaction-form" onSubmit={cancelTransaction}>
                        <Field label="Alasan pembatalan" error={cancelForm.errors.reason} required>
                            <Textarea
                                value={cancelForm.data.reason}
                                onChange={(event) => cancelForm.setData('reason', event.target.value)}
                                required
                                rows={4}
                                minLength={5}
                                maxLength={2000}
                                placeholder="Jelaskan alasan transaksi dibatalkan..."
                            />
                        </Field>
                    </form>
                </Modal>

                {quotation.status === 'cancelled' && (
                    <div className="rounded-xl border border-danger/25 bg-danger-soft p-4 text-sm text-danger">
                        <p className="font-semibold">Transaksi dibatalkan</p>
                        <p className="mt-1 whitespace-pre-line">{quotation.cancellation_reason}</p>
                        <p className="mt-2 text-xs opacity-80">
                            Oleh {quotation.cancelled_by_name ?? 'pengguna'}
                            {quotation.cancelled_at ? ` · ${new Date(quotation.cancelled_at).toLocaleString('id-ID')}` : ''}
                        </p>
                    </div>
                )}

                <Card>
                    <InfoGrid cols={3}>
                        <Info label="Customer" value={quotation.contact.name} />
                        <Info label="Perusahaan" value={quotation.contact.company_name} />
                        <Info label="Berlaku Sampai" value={validUntil} />
                        <Info label="Disiapkan Oleh" value={quotation.sales?.name} />
                        <Info label="Procurement Request" value={`#${quotation.procurement_request_id} · ${quotation.procurement_request.status}`} />
                        <Info label="Lead" value={`#${quotation.lead_id}`} />
                        <Info label="Catatan" value={quotation.notes} />
                    </InfoGrid>
                    {quotation.terms && (
                        <div className="mt-3 rounded-xl border border-border bg-surface-2 px-4 py-3 text-sm">
                            <span className="font-semibold text-text">Syarat &amp; Ketentuan (di cetakan):</span>
                            <p className="mt-1 whitespace-pre-line text-text-muted">{quotation.terms}</p>
                        </div>
                    )}
                </Card>

                {quotation.procurement_request.status !== 'ready' && (
                    <div className="rounded-xl border border-info/25 bg-info-soft p-4 text-sm font-medium text-info">
                        Revisi kebutuhan sedang diproses Procurement. Setelah costing berstatus Ready, Sales dapat melengkapi harga jual dan mengirim quotation ini kembali ke Project Manager.
                    </div>
                )}
                {pricingPending && (
                    <div className="rounded-xl border border-warning/25 bg-warning-soft p-4 text-sm font-medium text-warning">
                        Costing ulang sudah selesai. Lengkapi dan simpan harga jual terlebih dahulu sebelum quotation dapat ditinjau kembali oleh Project Manager.
                    </div>
                )}

                {quotation.status === 'draft' && <ReviewGate quotation={quotation} />}

                <Card padded={false}>
                    <TableScroll>
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b border-border bg-surface-2 text-[11px] font-bold uppercase tracking-wider text-text-faint">
                                    <th className="sticky left-0 z-[1] bg-surface-2 px-4 py-3">Item</th>
                                    <th className="px-4 py-3">Qty</th>
                                    <th className="px-4 py-3 text-right">Cost</th>
                                    <th className="px-4 py-3 text-right">Selling</th>
                                    <th className="px-4 py-3 text-right">Diskon</th>
                                    <th className="px-4 py-3 text-right">Markup / Margin</th>
                                    <th className="px-4 py-3">Pajak</th>
                                    <th className="px-4 py-3 text-right">DPP</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {orderedLines.map((line, pos) => (
                                  <Fragment key={line.id}>
                                    {(pos === 0 || (orderedLines[pos - 1].category === 'material') !== (line.category === 'material')) && (
                                        <tr className="bg-surface-2">
                                            <td colSpan="8" className="px-4 py-2 text-[11px] font-bold uppercase tracking-wider text-text-faint">
                                                <span className="sticky left-0 inline-block">{line.category === 'material' ? 'Material' : 'Jasa'}</span>
                                            </td>
                                        </tr>
                                    )}
                                    <tr>
                                        <td className="sticky left-0 z-[1] bg-surface w-44 min-w-44 px-4 py-3.5 sm:w-auto">
                                            <div className="font-medium text-text">{line.item_name}</div>
                                            <div className="whitespace-pre-line text-xs text-text-muted">{line.description || '—'}<CategoryBadge category={line.category} /></div>
                                            {line.sourcing_note && <div className="mt-0.5 text-[11px] italic text-text-muted">Opsi: {line.sourcing_note}</div>}
                                        </td>
                                        <td className="px-4 py-3.5 text-text-muted">{line.qty} {line.unit}</td>
                                        <td className="px-4 py-3.5 text-right tabular-nums text-text-muted">{money(line.cost_price)}</td>
                                        <td className="px-4 py-3.5 text-right tabular-nums text-text">{money(line.selling_price)}</td>
                                        <td className="px-4 py-3.5 text-right tabular-nums text-text-muted">{Number(line.discount_amount) > 0 ? <>{money(line.discount_amount)}<div className="text-xs">{line.discount_percent ?? 0}%</div></> : '—'}</td>
                                        <td className={`px-4 py-3.5 text-right tabular-nums ${Number(line.markup_percent) < 0 ? 'text-danger' : 'text-success'}`}>
                                            {line.markup_percent === null ? '—' : `${line.markup_percent}%`}
                                            <div className="text-xs text-text-muted">{line.effective_margin_percent == null ? '—' : `${line.effective_margin_percent}% efektif`}</div>
                                        </td>
                                        <td className="px-4 py-3.5 text-text-muted">{line.tax ? line.tax.name : (Number(line.tax_rate) > 0 ? `${line.tax_rate}%` : '—')}</td>
                                        <td className="px-4 py-3.5 text-right font-medium tabular-nums text-text">{money(line.subtotal)}</td>
                                    </tr>
                                  </Fragment>
                                ))}
                            </tbody>
                        </table>
                    </TableScroll>
                    <TotalsSummary totals={totals} />
                </Card>

                <Card>
                    <CardHeader title="Riwayat Revisi" className="-mx-5 -mt-5 mb-4 px-5" />
                    <div className="flex flex-wrap gap-2">
                        {history.map((item) => (
                            <Link
                                key={item.id}
                                href={`/sales/quotations/${item.id}`}
                                className={`rounded-xl border px-4 py-2.5 text-sm transition ${item.id === quotation.id ? 'border-navy bg-navy text-white' : 'border-border hover:bg-bg'}`}
                            >
                                <span className="font-semibold">Revisi {item.revision_number}</span>
                                <span className="ml-2 capitalize opacity-75">{item.status}</span>
                            </Link>
                        ))}
                    </div>
                </Card>
            </div>
        </AppLayout>
    );
}

function ReviewGate({ quotation }) {
    const pm = quotation.lead?.delegatedTo;
    const pmStatus = quotation.pm_review_status;
    const mgrStatus = quotation.manager_review_status;

    // Status review (pm_review_status/manager_review_status) adalah catatan permanen —
    // begitu disetujui, statusnya tetap tersimpan meski delegasi opportunity ke PM
    // ditarik kembali belakangan oleh Manager. Jadi cek hasil review DULU sebelum
    // menyimpulkan "belum didelegasikan", supaya quotation yang sudah benar-benar
    // disetujui tidak salah ditampilkan seolah belum diproses sama sekali.
    let message;
    if (mgrStatus === 'approved') message = 'Sudah disetujui Project Manager & Manager — siap dikirim ke customer.';
    else if (mgrStatus === 'rejected') message = `Ditolak oleh Manager (${quotation.managerReviewedBy?.name || '-'})${quotation.manager_review_notes ? `: ${quotation.manager_review_notes}` : ''}. Silakan revisi quotation ini.`;
    else if (pmStatus === 'approved') message = 'Sudah disetujui Project Manager, menunggu verifikasi Manager.';
    else if (pmStatus === 'rejected') message = `Ditolak oleh Project Manager (${quotation.pmReviewedBy?.name || '-'})${quotation.pm_review_notes ? `: ${quotation.pm_review_notes}` : ''}. Silakan revisi quotation ini.`;
    else if (!pm) message = 'Opportunity ini belum didelegasikan ke Project Manager oleh Manager — quotation belum bisa diverifikasi.';
    else message = `Menunggu verifikasi Project Manager (${pm.name}).`;

    const tone = mgrStatus === 'approved'
        ? 'border-success/25 bg-success-soft text-success'
        : (pmStatus === 'rejected' || mgrStatus === 'rejected')
            ? 'border-danger/25 bg-danger-soft text-danger'
            : 'border-warning/25 bg-warning-soft text-warning';

    return <div className={`rounded-xl border p-4 text-sm font-medium ${tone}`}>{message}</div>;
}

export function money(value) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(value || 0));
}
