import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../../Layouts/AppLayout';

function money(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(v || 0));
}

const statusBadge = {
    submitted: 'bg-warning/10 text-warning',
    searching: 'bg-info/10 text-info',
    ready: 'bg-success/10 text-success',
};

export default function Show({ procurementRequest: pr, editable, canStart, canFinalize, availabilityOptions, taxes, catalog }) {
    const number = `PR-${String(pr.id).padStart(6, '0')}`;
    const [rejectOpen, setRejectOpen] = useState(false);
    const rejectForm = useForm({ rejection_reason: '' });

    function markReady() {
        if (confirm('Tandai PR ini Ready? Sales akan dinotifikasi untuk membuat Quotation.')) {
            router.post(`/procurement/procurement-requests/${pr.id}/ready`);
        }
    }
    function submitReject(e) {
        e.preventDefault();
        rejectForm.post(`/procurement/procurement-requests/${pr.id}/reject`);
    }

    const { data, setData, put, processing, errors, transform } = useForm({
        lines: pr.lines.map((line) => ({
            id: line.id,
            vendor_product_id: line.vendor_product_id ?? '',
            cost_price: line.cost_price ?? '',
            tax_id: line.tax_id ?? '',
            availability_status: line.availability_status ?? 'searching',
        })),
    });

    transform((payload) => ({
        lines: payload.lines.map((l) => ({
            ...l,
            vendor_product_id: l.vendor_product_id === '' ? null : Number(l.vendor_product_id),
            tax_id: l.tax_id === '' ? null : Number(l.tax_id),
        })),
    }));

    function setLine(i, patch) {
        setData('lines', data.lines.map((l, idx) => (idx === i ? { ...l, ...patch } : l)));
    }

    function pickProduct(i, value) {
        const product = catalog.find((c) => String(c.id) === String(value));
        setLine(i, {
            vendor_product_id: value,
            // auto-isi harga dari katalog; tetap bisa diedit manual sesudahnya
            ...(product ? { cost_price: Number(product.price).toFixed(2) } : {}),
        });
    }

    function start() {
        if (confirm('Tandai PR ini sedang dikerjakan?')) router.post(`/procurement/procurement-requests/${pr.id}/start`);
    }

    function submit(e) {
        e.preventDefault();
        put(`/procurement/procurement-requests/${pr.id}/lines`, { preserveScroll: true });
    }

    return (
        <AppLayout>
            <Head title={number} />
            <div className="mx-auto max-w-6xl space-y-5">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <Link href="/procurement/procurement-requests" className="text-sm text-info">← Kembali</Link>
                        <div className="mt-2 flex items-center gap-3">
                            <h1 className="text-2xl font-bold text-text">{number}</h1>
                            <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${statusBadge[pr.status] ?? 'bg-bg text-text-muted'}`}>{pr.status}</span>
                        </div>
                        <p className="text-sm text-text-muted">{pr.lead.contact.name} · {pr.lead.contact.company_name || 'Tanpa perusahaan'}</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {canStart && <button onClick={start} className="rounded-lg bg-info px-4 py-2 text-sm font-semibold text-white">Mulai Kerjakan</button>}
                        {canFinalize && <button onClick={markReady} className="rounded-lg bg-success px-4 py-2 text-sm font-semibold text-white">Tandai Ready</button>}
                        {canFinalize && <button onClick={() => setRejectOpen(true)} className="rounded-lg border border-danger/30 px-4 py-2 text-sm font-semibold text-danger">Tolak PR</button>}
                    </div>
                </div>

                {pr.status === 'rejected' && pr.rejection_reason && (
                    <div className="rounded-lg border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger">Ditolak: {pr.rejection_reason}</div>
                )}
                {errors.procurement_request && <div className="rounded-lg border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger">{errors.procurement_request}</div>}
                {errors.lines && <div className="rounded-lg border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger">{errors.lines}</div>}

                <form onSubmit={submit} className="overflow-hidden rounded-xl border border-border bg-surface shadow-sm">
                    <div className="border-b border-border p-5">
                        <h2 className="font-semibold text-text">Sourcing per Item</h2>
                        <p className="text-sm text-text-muted">Kebutuhan berasal dari Sales. Isi vendor, cost price, pajak, dan ketersediaan.</p>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-bg text-text-muted">
                                <tr>
                                    <th className="px-3 py-3">Kebutuhan</th>
                                    <th className="px-3 py-3">Qty</th>
                                    <th className="px-3 py-3">Vendor / Produk</th>
                                    <th className="px-3 py-3">Cost Price</th>
                                    <th className="px-3 py-3">Pajak</th>
                                    <th className="px-3 py-3">Ketersediaan</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {pr.lines.map((line, i) => (
                                    <tr key={line.id}>
                                        <td className="px-3 py-3">
                                            <div className="font-medium text-text">{line.item_name}</div>
                                            <div className="text-xs text-text-muted">{line.description || '—'}</div>
                                            {line.requirement?.notes && <div className="mt-1 text-xs text-warning">Catatan: {line.requirement.notes}</div>}
                                        </td>
                                        <td className="whitespace-nowrap px-3 py-3 text-text-muted">{line.qty} {line.unit}</td>
                                        <td className="min-w-56 px-3 py-3">
                                            <select disabled={!editable} value={data.lines[i].vendor_product_id} onChange={(e) => pickProduct(i, e.target.value)} className="w-full rounded-lg border border-border px-2 py-2 outline-none focus:border-navy disabled:bg-bg">
                                                <option value="">— pilih —</option>
                                                {catalog.map((c) => <option key={c.id} value={c.id}>{c.vendor?.name} · {c.item_name} ({money(c.price)}/{c.unit})</option>)}
                                            </select>
                                            {errors[`lines.${i}.vendor_product_id`] && <span className="text-xs text-danger">{errors[`lines.${i}.vendor_product_id`]}</span>}
                                        </td>
                                        <td className="min-w-36 px-3 py-3">
                                            <input type="number" min="0" step="0.01" disabled={!editable} value={data.lines[i].cost_price} onChange={(e) => setLine(i, { cost_price: e.target.value })} className="w-full rounded-lg border border-border px-2 py-2 text-right outline-none focus:border-navy disabled:bg-bg" />
                                            {errors[`lines.${i}.cost_price`] && <span className="text-xs text-danger">{errors[`lines.${i}.cost_price`]}</span>}
                                        </td>
                                        <td className="min-w-36 px-3 py-3">
                                            <select disabled={!editable} value={data.lines[i].tax_id} onChange={(e) => setLine(i, { tax_id: e.target.value })} className="w-full rounded-lg border border-border px-2 py-2 outline-none focus:border-navy disabled:bg-bg">
                                                <option value="">Tanpa pajak</option>
                                                {taxes.map((t) => <option key={t.id} value={t.id}>{t.name} ({Number(t.rate)}%)</option>)}
                                            </select>
                                        </td>
                                        <td className="min-w-40 px-3 py-3">
                                            <select disabled={!editable} value={data.lines[i].availability_status} onChange={(e) => setLine(i, { availability_status: e.target.value })} className="w-full rounded-lg border border-border px-2 py-2 outline-none focus:border-navy disabled:bg-bg">
                                                {availabilityOptions.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                                            </select>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {editable && (
                        <div className="flex justify-end border-t border-border p-4">
                            <button disabled={processing} className="rounded-lg bg-navy px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">
                                {processing ? 'Menyimpan...' : 'Simpan'}
                            </button>
                        </div>
                    )}
                </form>
            </div>

            {rejectOpen && (
                <div className="fixed inset-0 z-20 flex items-center justify-center bg-navy/40 p-4">
                    <form onSubmit={submitReject} className="w-full max-w-md rounded-xl border border-border bg-surface p-6 shadow-lg">
                        <h3 className="text-lg font-semibold text-text">Tolak Procurement Request</h3>
                        <p className="mt-1 text-sm text-text-muted">PR dikembalikan ke Sales untuk revisi requirement. Wajib isi alasan.</p>
                        <textarea
                            rows="4"
                            autoFocus
                            value={rejectForm.data.rejection_reason}
                            onChange={(e) => rejectForm.setData('rejection_reason', e.target.value)}
                            className="input mt-3"
                            placeholder="Contoh: spesifikasi item tidak jelas, qty tidak masuk akal, dst."
                        />
                        {rejectForm.errors.rejection_reason && <span className="text-xs text-danger">{rejectForm.errors.rejection_reason}</span>}
                        <div className="mt-4 flex justify-end gap-2">
                            <button type="button" onClick={() => setRejectOpen(false)} className="rounded-lg border border-border px-4 py-2 text-sm">Batal</button>
                            <button disabled={rejectForm.processing} className="rounded-lg bg-danger px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">
                                {rejectForm.processing ? 'Memproses...' : 'Tolak & Kembalikan'}
                            </button>
                        </div>
                    </form>
                </div>
            )}
        </AppLayout>
    );
}
