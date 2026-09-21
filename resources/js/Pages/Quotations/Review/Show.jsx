import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { FiFileText } from 'react-icons/fi';
import AppLayout from '../../../Layouts/AppLayout';
import CategoryBadge from '../../../Components/CategoryBadge';
import { PageHeader, Button } from '../../../Components/ui';
import { feedback } from '../../../Components/feedback';
import TableScroll from '../../../Components/ui/TableScroll';
import TotalsSummary from '../../../Components/ui/TotalsSummary';

export default function Show({ quotation, canReview, role, backHref }) {
    const isMgmt = role === 'management';
    const base = isMgmt ? '/management/quotations' : '/project-manager/quotations';
    const form = useForm({ approved: true, notes: '' });
    const [action, setAction] = useState(null);

    async function submit(approved) {
        const ok = await feedback.confirm(approved
            ? { tone: 'question', title: 'Setujui quotation ini?', text: `Quotation ${quotation.number} akan ditandai disetujui${isMgmt ? ' dan siap dikirim Sales ke customer' : ' lalu diteruskan ke Manager'}.`, confirmLabel: 'Ya, setujui' }
            : { tone: 'danger', title: 'Tolak quotation ini?', text: `Quotation ${quotation.number} akan dikembalikan ke Sales untuk diperbaiki. Pastikan catatan penolakan sudah diisi.`, confirmLabel: 'Ya, tolak' });
        if (!ok) return;

        feedback.expect({ success: { title: approved ? 'Quotation disetujui' : 'Quotation ditolak', style: 'popup' }, error: { title: 'Gagal memproses quotation' } });
        setAction(approved ? 'approve' : 'reject');
        form.transform((data) => ({ ...data, approved }));
        form.post(`${base}/${quotation.id}/review`, { preserveScroll: true });
    }

    return (
        <AppLayout>
            <Head title={quotation.number} />
            <div className="mx-auto max-w-5xl space-y-5">
                <PageHeader
                    title={(
                        <span className="flex flex-wrap items-center gap-3">
                            {quotation.number}
                            {quotation.is_addendum && <span className="rounded-full bg-info-soft px-2.5 py-1 text-xs font-semibold text-info">Tambahan (Addendum)</span>}
                        </span>
                    )}
                    subtitle={`${quotation.company || quotation.customer} · Sales: ${quotation.sales}`}
                    back={{ href: backHref || base, label: 'Kembali' }}
                    actions={<Button href={`/sales/quotations/${quotation.id}/print`} external variant="outline" icon={FiFileText}>Lihat PDF</Button>}
                />

                <section className="grid gap-x-6 gap-y-4 card p-6 sm:grid-cols-2">
                    <ReviewInfo label="Verifikasi Project Manager" status={quotation.pm_review_status} by={quotation.pm_reviewed_by} at={quotation.pm_reviewed_at} notes={quotation.pm_review_notes} />
                    <ReviewInfo label="Verifikasi Manager" status={quotation.manager_review_status} by={quotation.manager_reviewed_by} at={quotation.manager_reviewed_at} notes={quotation.manager_review_notes} />
                </section>

                <section className="card overflow-hidden p-0">
                    <TableScroll>
                        <table className="w-full text-left text-sm">
                            <thead className="bg-surface-2 text-[11px] font-bold uppercase tracking-wider text-text-faint">
                                <tr>
                                    <th className="sticky left-0 z-[1] bg-surface-2 px-4 py-3">Item</th>
                                    <th className="px-4 py-3">Qty</th>
                                    {isMgmt && <th className="px-4 py-3 text-right">Harga Beli</th>}
                                    <th className="px-4 py-3 text-right">Harga Jual</th>
                                    {isMgmt && <th className="px-4 py-3 text-right">Selisih</th>}
                                    <th className="px-4 py-3 text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {quotation.lines.map((line) => {
                                    const lineMargin = Number(line.subtotal) - Number(line.qty) * Number(line.cost_price || 0);
                                    const marginPercent = Number(line.cost_price) > 0
                                        ? (lineMargin / (Number(line.qty) * Number(line.cost_price)) * 100)
                                        : null;
                                    return (
                                        <tr key={line.id}>
                                            <td className="sticky left-0 z-[1] bg-surface w-44 min-w-44 px-4 py-3 sm:w-auto"><div className="font-medium text-text">{line.item_name}</div><CategoryBadge category={line.category} /></td>
                                            <td className="px-4 py-3 text-text-muted">{line.qty} {line.unit}</td>
                                            {isMgmt && <td className="px-4 py-3 text-right text-text-muted">{money(line.cost_price)}</td>}
                                            <td className="px-4 py-3 text-right text-text">{money(line.selling_price)}</td>
                                            {isMgmt && (
                                                <td className={`px-4 py-3 text-right font-medium ${lineMargin < 0 ? 'text-danger' : 'text-success'}`}>
                                                    {money(lineMargin)}
                                                    {marginPercent !== null && <span className="ml-1 text-xs font-normal text-text-faint">({marginPercent.toFixed(1)}%)</span>}
                                                </td>
                                            )}
                                            <td className="px-4 py-3 text-right font-medium text-text">{money(line.subtotal)}</td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </TableScroll>
                    <TotalsSummary totals={quotation.totals} />
                </section>

                {canReview && (
                    <div className="space-y-3 card p-6">
                        <h2 className="font-semibold text-text">Verifikasi Quotation</h2>
                        <textarea
                            value={form.data.notes}
                            onChange={(e) => form.setData('notes', e.target.value)}
                            placeholder="Catatan (wajib diisi jika menolak)"
                            className="input min-h-[80px] w-full"
                        />
                        {form.errors.notes && <span className="block text-xs text-danger">{form.errors.notes}</span>}
                        <div className="flex justify-end gap-2">
                            <button
                                disabled={form.processing}
                                onClick={() => submit(false)}
                                className="btn btn-outline border-danger/40 text-danger"
                            >
                                {form.processing && action === 'reject' ? 'Memproses…' : 'Tolak'}
                            </button>
                            <button
                                disabled={form.processing}
                                onClick={() => submit(true)}
                                className="btn btn-primary bg-success"
                            >
                                {form.processing && action === 'approve' ? 'Memproses…' : 'Setujui'}
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

function ReviewInfo({ label, status, by, at, notes }) {
    const styles = { approved: 'badge-success', rejected: 'badge-danger' };
    const labelText = status === 'approved' ? 'Disetujui' : status === 'rejected' ? 'Ditolak' : 'Menunggu';
    return (
        <div>
            <div className="text-[11px] font-bold uppercase tracking-wider text-text-faint">{label}</div>
            <div className="mt-1"><span className={`badge ${styles[status] || 'badge-warning'}`}>{labelText}</span></div>
            {by && <div className="mt-1 text-xs text-text-muted">oleh {by} · {new Date(at).toLocaleString('id-ID')}</div>}
            {notes && <div className="mt-1 whitespace-pre-line text-sm text-text">{notes}</div>}
        </div>
    );
}

function money(value) { return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(value || 0)); }
