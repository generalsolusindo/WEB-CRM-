import { Fragment } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { FiPrinter, FiEdit2, FiSend, FiCheck, FiX, FiCopy, FiTrash2, FiHash } from 'react-icons/fi';
import AppLayout from '../../../Layouts/AppLayout';
import CategoryBadge from '../../../Components/CategoryBadge';
import { PageHeader, Card, CardHeader, Button, Info, InfoGrid, StatusBadge } from '../../../Components/ui';

export default function Show({ quotation, history, totals, permissions, customerHasWhatsapp = false }) {
    const number = quotation.number ?? `QT-${String(quotation.id).padStart(6, '0')} / R${quotation.revision_number}`;

    function editNumber() {
        const value = window.prompt('Nomor quotation baru:', quotation.number ?? '');
        if (value && value.trim() !== '' && value.trim() !== quotation.number) {
            router.patch(`/sales/quotations/${quotation.id}/number`, { number: value.trim() }, { preserveScroll: true });
        }
    }
    const validUntil = quotation.valid_until
        ? new Date(quotation.valid_until).toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' })
        : null;
    const orderedLines = [...quotation.lines].sort(
        (a, b) => (a.category === 'material' ? 0 : 1) - (b.category === 'material' ? 0 : 1),
    );
    function action(path, message) { if (confirm(message)) router.post(path); }
    function destroy() {
        if (confirm(`Hapus quotation ${number} ini? Tindakan ini tidak bisa dibatalkan.`)) {
            router.delete(`/sales/quotations/${quotation.id}`);
        }
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
                            {permissions.updateNumber && <Button onClick={editNumber} variant="outline" icon={FiHash}>Ubah Nomor</Button>}
                            {permissions.update && <Button href={`/sales/quotations/${quotation.id}/edit`} variant="outline" icon={FiEdit2}>Edit</Button>}
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
                            {permissions.send && <Button onClick={() => action(`/sales/quotations/${quotation.id}/send`, 'Tandai quotation sudah dikirim ke customer?')} icon={FiSend}>Tandai Terkirim</Button>}
                            {permissions.confirm && <Button href={`/sales/quotations/${quotation.id}/confirm`} icon={FiCheck}>Confirm Deal</Button>}
                            {permissions.reject && <Button onClick={() => action(`/sales/quotations/${quotation.id}/reject`, 'Tandai quotation ditolak customer?')} variant="ghost" icon={FiX} className="text-danger hover:bg-danger-soft hover:text-danger">Tandai Ditolak</Button>}
                            {permissions.revise && <Button onClick={() => action(`/sales/quotations/${quotation.id}/revisions`, 'Buat revision baru dari quotation ini?')} variant="outline" icon={FiCopy}>Buat Revisi</Button>}
                            {permissions.delete && <Button onClick={destroy} variant="ghost" icon={FiTrash2} className="text-danger hover:bg-danger-soft hover:text-danger">Hapus</Button>}
                        </>
                    }
                />

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

                {quotation.status === 'draft' && <ReviewGate quotation={quotation} />}

                <Card padded={false}>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b border-border bg-surface-2 text-[11px] font-bold uppercase tracking-wider text-text-faint">
                                    <th className="px-4 py-3">Item</th>
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
                                                {line.category === 'material' ? 'Material' : 'Jasa'}
                                            </td>
                                        </tr>
                                    )}
                                    <tr>
                                        <td className="px-4 py-3.5">
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
                            <Totals totals={totals} span={7} />
                        </table>
                    </div>
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

export function Totals({ totals, span }) {
    return (
        <tfoot className="border-t border-border bg-surface-2 text-text">
            <tr><td colSpan={span} className="px-4 py-2 text-right text-text-muted">Subtotal Bruto</td><td className="px-4 py-2 text-right font-medium tabular-nums">{money(totals.gross)}</td></tr>
            <tr><td colSpan={span} className="px-4 py-2 text-right text-text-muted">Total Diskon{Number(totals.discount) > 0 ? ` (${totals.discount_percent}%)` : ''}</td><td className="px-4 py-2 text-right font-medium tabular-nums text-danger">{Number(totals.discount) > 0 ? `− ${money(totals.discount)}` : money(0)}</td></tr>
            <tr><td colSpan={span} className="px-4 py-2 text-right text-text-muted">DPP</td><td className="px-4 py-2 text-right font-medium tabular-nums">{money(totals.subtotal)}</td></tr>
            <tr><td colSpan={span} className="px-4 py-2 text-right text-text-muted">Total PPN</td><td className="px-4 py-2 text-right font-medium tabular-nums">{money(totals.tax)}</td></tr>
            <tr><td colSpan={span} className="px-4 py-4 text-right font-semibold">Grand Total</td><td className="px-4 py-4 text-right text-lg font-bold tabular-nums">{money(totals.grand_total)}</td></tr>
            {Number(totals.pph23_estimate) > 0 && (
                <>
                    <tr><td colSpan={span} className="px-4 py-1.5 text-right text-xs text-text-muted">Estimasi PPh 23 (2%) — jika customer memotong</td><td className="px-4 py-1.5 text-right text-xs font-medium tabular-nums text-warning">− {money(totals.pph23_estimate)}</td></tr>
                    <tr><td colSpan={span} className="px-4 py-1.5 text-right text-xs text-text-muted">Estimasi diterima tunai</td><td className="px-4 py-1.5 text-right text-xs font-medium tabular-nums">{money(Number(totals.grand_total) - Number(totals.pph23_estimate))}</td></tr>
                </>
            )}
            {totals.margin_percent != null && (
                <tr><td colSpan={span} className="px-4 py-2 text-right text-text-muted">Estimasi margin keseluruhan</td><td className={`px-4 py-2 text-right font-medium tabular-nums ${Number(totals.margin_percent) < 0 ? 'text-danger' : 'text-success'}`}>{money(totals.margin_amount)} ({totals.margin_percent}%)</td></tr>
            )}
        </tfoot>
    );
}

export function money(value) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(value || 0));
}
