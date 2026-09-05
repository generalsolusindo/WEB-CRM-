import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../../Layouts/AppLayout';
import CategoryBadge from '../../../Components/CategoryBadge';
import { Totals } from '../../Sales/Quotations/Show';

export default function Show({ quotation, canReview, role }) {
    const base = role === 'management' ? '/management/quotations' : '/project-manager/quotations';
    const form = useForm({ approved: true, notes: '' });
    const [action, setAction] = useState(null);

    function submit(approved) {
        setAction(approved ? 'approve' : 'reject');
        form.transform((data) => ({ ...data, approved }));
        form.post(`${base}/${quotation.id}/review`, { preserveScroll: true });
    }

    return (
        <AppLayout>
            <Head title={quotation.number} />
            <div className="mx-auto max-w-5xl space-y-5">
                <div>
                    <Link href={base} className="text-sm text-info">← Kembali</Link>
                    <h1 className="mt-2 text-2xl font-bold text-text">{quotation.number}</h1>
                    <p className="text-sm text-text-muted">{quotation.company || quotation.customer} · Sales: {quotation.sales}</p>
                </div>

                <section className="grid gap-4 rounded-xl border border-border bg-surface p-6 shadow-sm sm:grid-cols-2">
                    <ReviewInfo label="Verifikasi Project Manager" status={quotation.pm_review_status} by={quotation.pm_reviewed_by} at={quotation.pm_reviewed_at} notes={quotation.pm_review_notes} />
                    <ReviewInfo label="Verifikasi Manager" status={quotation.manager_review_status} by={quotation.manager_reviewed_by} at={quotation.manager_reviewed_at} notes={quotation.manager_review_notes} />
                </section>

                <section className="overflow-hidden rounded-xl border border-border bg-surface shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-bg text-text-muted">
                                <tr><th className="px-4 py-3">Item</th><th className="px-4 py-3">Qty</th><th className="px-4 py-3 text-right">Harga Jual</th><th className="px-4 py-3 text-right">Subtotal</th></tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {quotation.lines.map((line) => (
                                    <tr key={line.id}>
                                        <td className="px-4 py-3"><div className="font-medium text-text">{line.item_name}</div><CategoryBadge category={line.category} /></td>
                                        <td className="px-4 py-3 text-text-muted">{line.qty} {line.unit}</td>
                                        <td className="px-4 py-3 text-right text-text">{money(line.selling_price)}</td>
                                        <td className="px-4 py-3 text-right font-medium text-text">{money(line.subtotal)}</td>
                                    </tr>
                                ))}
                            </tbody>
                            <Totals totals={quotation.totals} span={3} />
                        </table>
                    </div>
                </section>

                {canReview && (
                    <div className="space-y-3 rounded-xl border border-border bg-surface p-6 shadow-sm">
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
                                className="rounded-lg border border-danger/30 px-5 py-2 text-sm font-semibold text-danger disabled:opacity-50"
                            >
                                {form.processing && action === 'reject' ? 'Memproses…' : 'Tolak'}
                            </button>
                            <button
                                disabled={form.processing}
                                onClick={() => submit(true)}
                                className="rounded-lg bg-success px-5 py-2 text-sm font-semibold text-white disabled:opacity-50"
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
    const styles = { approved: 'bg-success/10 text-success', rejected: 'bg-danger/10 text-danger' };
    const labelText = status === 'approved' ? 'Disetujui' : status === 'rejected' ? 'Ditolak' : 'Menunggu';
    return (
        <div>
            <div className="text-xs font-semibold uppercase tracking-wide text-text-muted">{label}</div>
            <div className="mt-1"><span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${styles[status] || 'bg-warning/10 text-warning'}`}>{labelText}</span></div>
            {by && <div className="mt-1 text-xs text-text-muted">oleh {by} · {new Date(at).toLocaleString('id-ID')}</div>}
            {notes && <div className="mt-1 whitespace-pre-line text-sm text-text">{notes}</div>}
        </div>
    );
}

function money(value) { return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(value || 0)); }
