import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { FiPlay, FiCheck, FiXCircle } from 'react-icons/fi';
import AppLayout from '../../../Layouts/AppLayout';
import CategoryBadge from '../../../Components/CategoryBadge';
import { PageHeader, Card, Button, ConfirmDialog, Modal, StatusBadge, CurrencyInput } from '../../../Components/ui';
import { feedback } from '../../../Components/feedback';
import TableScroll from '../../../Components/ui/TableScroll';

function money(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(v || 0));
}

function Alert({ text }) {
    return <div className="rounded-xl border border-danger/25 bg-danger-soft px-4 py-3 text-sm font-medium text-danger">{text}</div>;
}

export default function Show({ procurementRequest: pr, editable, canStart, canFinalize, isRecost = false, hasNpwp = false, npwpDocumentUrl = null, availabilityOptions, taxes, catalog }) {
    const number = `PR-${String(pr.id).padStart(6, '0')}`;
    const [rejectOpen, setRejectOpen] = useState(false);
    const [confirmation, setConfirmation] = useState(null);
    const [actionProcessing, setActionProcessing] = useState(false);
    const rejectForm = useForm({ rejection_reason: '' });

    function runAction() {
        if (!confirmation) return;
        setActionProcessing(true);
        const endpoint = confirmation === 'ready' ? 'ready' : 'start';
        feedback.expect({ success: { title: endpoint === 'ready' ? 'Harga siap dikirim ke Sales' : 'Sourcing dimulai', style: 'popup' } });
        router.post(`/procurement/procurement-requests/${pr.id}/${endpoint}`, {}, {
            preserveScroll: true,
            onSuccess: () => setConfirmation(null),
            onFinish: () => setActionProcessing(false),
        });
    }
    function submitReject(e) {
        e.preventDefault();
        feedback.expect({ success: { title: 'Request ditolak', style: 'popup' } });
        rejectForm.post(`/procurement/procurement-requests/${pr.id}/reject`);
    }

    const { data, setData, put, processing, errors, transform } = useForm({
        lines: pr.lines.map((line) => ({
            id: line.id,
            category: line.category ?? 'material',
            sourcing_note: line.sourcing_note ?? '',
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
            ...(product ? { cost_price: Number(product.price).toFixed(2), category: product.category } : {}),
        });
    }

    function submit(e) {
        e.preventDefault();
        put(`/procurement/procurement-requests/${pr.id}/lines`, { preserveScroll: true });
    }

    const control = 'w-full rounded-lg border border-border-strong bg-surface px-2 py-2 text-xs outline-none transition focus:border-primary disabled:bg-bg disabled:text-text-muted';

    return (
        <AppLayout>
            <Head title={number} />
            <div className="mx-auto max-w-6xl space-y-5">
                <PageHeader
                    title={<span className="flex items-center gap-3">{number} <StatusBadge status={pr.status} /></span>}
                    subtitle={`${pr.lead.contact.name} · ${pr.lead.contact.company_name || 'Tanpa perusahaan'}`}
                    back={{ href: '/procurement/procurement-requests', label: 'Kembali' }}
                    actions={(
                        <>
                            {canStart && <Button onClick={() => setConfirmation('start')} icon={FiPlay}>Mulai Kerjakan</Button>}
                            {canFinalize && <Button onClick={() => setConfirmation('ready')} icon={FiCheck} className="bg-success text-white hover:bg-success">Tandai Ready</Button>}
                            {canFinalize && <Button onClick={() => setRejectOpen(true)} variant="ghost" icon={FiXCircle} className="text-danger hover:bg-danger-soft hover:text-danger">Tolak PR</Button>}
                        </>
                    )}
                />

                {isRecost && pr.status !== 'ready' && (
                    <div className="rounded-xl border border-warning/25 bg-warning-soft px-4 py-3 text-sm font-medium text-warning">
                        Costing ulang untuk revisi kebutuhan quotation. Periksa kembali vendor atau stok, harga beli, pajak, dan ketersediaan seluruh item sebelum menandai Ready.
                    </div>
                )}

                {hasNpwp && (
                    <div className="flex flex-wrap items-center gap-2 rounded-xl border border-primary/25 bg-primary-soft px-4 py-3 text-sm font-medium text-primary-strong">
                        <span>💡 Customer ini punya NPWP — kemungkinan kebutuhan ini perlu dikenakan PPN. Pilih pajak PPN di tiap baris sesuai kebutuhan.</span>
                        {npwpDocumentUrl && (
                            <a href={npwpDocumentUrl} target="_blank" rel="noreferrer" className="font-semibold underline">
                                Lihat dokumen NPWP
                            </a>
                        )}
                    </div>
                )}

                {pr.status === 'rejected' && pr.rejection_reason && <Alert text={`Ditolak: ${pr.rejection_reason}`} />}
                {errors.procurement_request && <Alert text={errors.procurement_request} />}
                {errors.lines && <Alert text={errors.lines} />}

                <Card padded={false}>
                    <form onSubmit={submit}>
                        <div className="border-b border-border p-5">
                            <h2 className="font-semibold text-text">Sourcing per Item</h2>
                            <p className="text-sm text-text-muted">Kebutuhan berasal dari Sales. Isi vendor, cost price, pajak, dan ketersediaan.</p>
                        </div>
                        <TableScroll>
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b border-border bg-surface-2 text-[11px] font-bold uppercase tracking-wider text-text-faint">
                                        <th className="sticky left-0 z-[1] bg-surface-2 px-3 py-3">Kebutuhan</th>
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
                                            <td className="sticky left-0 z-[1] bg-surface w-48 min-w-48 px-3 py-3 sm:w-auto">
                                                <div className="flex flex-wrap items-center gap-1.5 font-medium text-text">
                                                    {line.item_name}
                                                    <CategoryBadge category={data.lines[i].category} />
                                                    {line.revision_state && <RevisionBadge state={line.revision_state} />}
                                                </div>
                                                <div className="whitespace-pre-line text-xs text-text-muted">{line.description || '—'}</div>
                                                {line.requirement?.notes && <div className="mt-1 text-xs text-warning">Catatan: {line.requirement.notes}</div>}
                                                <label className="mt-2 block text-[11px] font-medium text-text-muted">Kategori
                                                    <select disabled={!editable} value={data.lines[i].category} onChange={(e) => setLine(i, { category: e.target.value })} className={`mt-1 ${control}`}>
                                                        <option value="material">Material</option>
                                                        <option value="service">Jasa</option>
                                                        <option value="reimburse">Biaya Reimburse</option>
                                                    </select>
                                                </label>
                                                <label className="mt-2 block text-[11px] font-medium text-text-muted">Catatan Sourcing / Opsi Merk
                                                    <textarea rows="2" disabled={!editable} value={data.lines[i].sourcing_note} onChange={(e) => setLine(i, { sourcing_note: e.target.value })} placeholder="mis. Rekomendasi Hikvision DS-2CD; alternatif Dahua (−10%). Customer belum tentukan merk." className={`mt-1 ${control}`} />
                                                </label>
                                                {errors[`lines.${i}.sourcing_note`] && <span className="text-xs text-danger">{errors[`lines.${i}.sourcing_note`]}</span>}
                                            </td>
                                            <td className="whitespace-nowrap px-3 py-3 text-text-muted">{line.qty} {line.unit}</td>
                                            <td className="min-w-56 px-3 py-3">
                                                <select disabled={!editable} value={data.lines[i].vendor_product_id} onChange={(e) => pickProduct(i, e.target.value)} className={control}>
                                                    <option value="">— pilih —</option>
                                                    {catalog.map((c) => <option key={c.id} value={c.id}>{c.vendor?.name} · {c.item_name} ({money(c.price)}/{c.unit})</option>)}
                                                </select>
                                                {errors[`lines.${i}.vendor_product_id`] && <span className="text-xs text-danger">{errors[`lines.${i}.vendor_product_id`]}</span>}
                                            </td>
                                            <td className="min-w-36 px-3 py-3">
                                                <CurrencyInput disabled={!editable} value={data.lines[i].cost_price} onChange={(e) => setLine(i, { cost_price: e.target.value })} className={`${control} text-right`} />
                                                {errors[`lines.${i}.cost_price`] && <span className="text-xs text-danger">{errors[`lines.${i}.cost_price`]}</span>}
                                            </td>
                                            <td className="min-w-36 px-3 py-3">
                                                <select disabled={!editable} value={data.lines[i].tax_id} onChange={(e) => setLine(i, { tax_id: e.target.value })} className={control}>
                                                    <option value="">Tanpa pajak</option>
                                                    {taxes.map((t) => <option key={t.id} value={t.id}>{t.name} ({Number(t.rate)}%)</option>)}
                                                </select>
                                            </td>
                                            <td className="min-w-40 px-3 py-3">
                                                <select disabled={!editable} value={data.lines[i].availability_status} onChange={(e) => setLine(i, { availability_status: e.target.value })} className={control}>
                                                    {availabilityOptions.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                                                </select>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </TableScroll>
                        {editable && (
                            <div className="flex justify-end border-t border-border p-4">
                                <Button type="submit" loading={processing}>Simpan</Button>
                            </div>
                        )}
                    </form>
                </Card>
            </div>

            <ConfirmDialog
                open={confirmation !== null}
                onClose={() => setConfirmation(null)}
                onConfirm={runAction}
                title={confirmation === 'ready' ? 'Tandai Procurement Request sebagai Ready?' : 'Mulai kerjakan Procurement Request?'}
                description={confirmation === 'ready'
                    ? 'Sales akan menerima notifikasi dan dapat melanjutkan pembuatan quotation.'
                    : 'Status Procurement Request akan berubah menjadi sedang dikerjakan.'}
                tone={confirmation === 'ready' ? 'success' : 'info'}
                confirmLabel={confirmation === 'ready' ? 'Tandai Ready' : 'Mulai Kerjakan'}
                processing={actionProcessing}
            />

            <Modal
                open={rejectOpen}
                onClose={() => setRejectOpen(false)}
                title="Tolak Procurement Request"
                busy={rejectForm.processing}
                footer={(
                    <>
                        <Button type="button" variant="outline" onClick={() => setRejectOpen(false)} disabled={rejectForm.processing}>Batal</Button>
                        <Button type="submit" form="reject-pr-form" variant="danger" loading={rejectForm.processing}>Tolak &amp; Kembalikan</Button>
                    </>
                )}
            >
                <form id="reject-pr-form" onSubmit={submitReject}>
                    <p className="text-sm text-text-muted">PR dikembalikan ke Sales untuk revisi requirement. Wajib isi alasan.</p>
                    <textarea
                        rows="4"
                        autoFocus
                        value={rejectForm.data.rejection_reason}
                        onChange={(e) => rejectForm.setData('rejection_reason', e.target.value)}
                        className="input mt-3"
                        placeholder="Contoh: spesifikasi item tidak jelas, qty tidak masuk akal, dst."
                    />
                    {rejectForm.errors.rejection_reason && <span className="text-xs text-danger">{rejectForm.errors.rejection_reason}</span>}
                </form>
            </Modal>
        </AppLayout>
    );
}

function RevisionBadge({ state }) {
    const config = {
        unchanged: ['Tidak Berubah', 'bg-success-soft text-success'],
        changed: ['Diubah', 'bg-warning-soft text-warning'],
        new: ['Baru', 'bg-info-soft text-info'],
    }[state];

    if (!config) return null;

    return <span className={`rounded-full px-2 py-0.5 text-[10px] font-semibold ${config[1]}`}>{config[0]}</span>;
}
