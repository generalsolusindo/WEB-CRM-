import { Head, Link, router, useForm } from '@inertiajs/react';
import { FiSend, FiCheck } from 'react-icons/fi';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, Card, CardHeader, Button, Info, InfoGrid, StatusBadge, CurrencyInput } from '../../../Components/ui';

function money(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(v || 0));
}

const CONTROL = 'w-full rounded-lg border border-border-strong bg-surface px-2 py-1.5 text-xs outline-none transition focus:border-primary disabled:bg-bg disabled:text-text-muted';

export default function Show({ project, items, payment, editable, canConfirm, canReceiveAll, history = [], vendors, catalog }) {
    const { data, setData, post, processing, errors, transform } = useForm({
        pricing_mode: payment?.pricing_mode ?? 'itemized',
        lump_sum_vendor_id: payment?.lump_sum_vendor_id ?? '',
        lump_sum_amount: payment?.lump_sum_amount != null ? String(Number(payment.lump_sum_amount)) : '',
        bank_account_note: payment?.bank_account_note ?? '',
        lines: items.map((it) => ({
            id: it.id,
            from_office_stock: it.from_office_stock,
            office_stock_note: it.office_stock_note ?? '',
            vendor_id: it.vendor_id ?? '',
            cost_price: it.cost_price != null ? String(Number(it.cost_price)) : '',
            bank_account_note: it.bank_account_note ?? '',
        })),
    });

    transform((payload) => ({
        ...payload,
        lump_sum_vendor_id: payload.lump_sum_vendor_id === '' ? null : Number(payload.lump_sum_vendor_id),
        lump_sum_amount: payload.lump_sum_amount === '' ? null : Number(payload.lump_sum_amount),
        lines: payload.lines.map((l) => ({
            ...l,
            vendor_id: l.from_office_stock || l.vendor_id === '' ? null : Number(l.vendor_id),
            cost_price: l.from_office_stock || l.cost_price === '' ? null : Number(l.cost_price),
            bank_account_note: l.from_office_stock ? null : l.bank_account_note,
        })),
    }));

    const setLine = (i, patch) => setData('lines', data.lines.map((l, idx) => (idx === i ? { ...l, ...patch } : l)));

    function pickVendor(i, value) {
        const v = vendors.find((x) => String(x.id) === String(value));
        setLine(i, { vendor_id: value, bank_account_note: v?.bank_account_note ?? '' });
    }

    function pickVendorProduct(i, value) {
        const p = catalog.find((c) => String(c.id) === String(value));
        if (!p) return;
        const v = vendors.find((x) => String(x.id) === String(p.vendor_id));
        setLine(i, { vendor_id: String(p.vendor_id), cost_price: String(Number(p.price)), bank_account_note: v?.bank_account_note ?? '' });
    }

    function pickLumpSumVendor(value) {
        const v = vendors.find((x) => String(x.id) === String(value));
        setData({ ...data, lump_sum_vendor_id: value, bank_account_note: v?.bank_account_note ?? '' });
    }

    const isLump = data.pricing_mode === 'lump_sum';
    const itemizedTotal = data.lines.reduce((s, l, i) => (l.from_office_stock ? s : s + Number(l.cost_price || 0) * Number(items[i].qty || 0)), 0);

    function save(e) {
        e?.preventDefault();
        router.put(`/procurement/project-procurements/${project.id}/sourcing`, transformPayload(data), { preserveScroll: true });
    }
    function transformPayload(d) {
        return {
            pricing_mode: d.pricing_mode,
            lump_sum_vendor_id: d.lump_sum_vendor_id === '' ? null : Number(d.lump_sum_vendor_id),
            lump_sum_amount: d.lump_sum_amount === '' ? null : Number(d.lump_sum_amount),
            bank_account_note: d.bank_account_note,
            lines: d.lines.map((l, i) => ({
                id: l.id,
                from_office_stock: l.from_office_stock,
                office_stock_note: l.office_stock_note,
                vendor_id: l.from_office_stock || l.vendor_id === '' ? null : Number(l.vendor_id),
                cost_price: l.from_office_stock || l.cost_price === '' ? null : Number(l.cost_price),
                bank_account_note: l.from_office_stock ? null : l.bank_account_note,
            })),
        };
    }
    function submit(e) {
        e.preventDefault();
        post(`/procurement/project-procurements/${project.id}/submit`, { preserveScroll: true });
    }
    function confirmPayment() {
        if (confirm('Konfirmasi bahwa semua pembayaran ke vendor sudah beres?')) {
            router.post(`/procurement/project-procurements/${project.id}/confirm`, {}, { preserveScroll: true });
        }
    }
    function receiveItem(id) {
        router.post(`/procurement/project-procurements/items/${id}/receive`, {}, { preserveScroll: true });
    }
    function receiveAll() {
        if (confirm('Tandai semua barang yang sudah dibayar sebagai diterima?')) {
            router.post(`/procurement/project-procurements/${project.id}/receive-all`, {}, { preserveScroll: true });
        }
    }

    return (
        <AppLayout>
            <Head title={`Pengadaan ${project.number}`} />
            <div className="mx-auto max-w-5xl space-y-5">
                <PageHeader
                    title={(
                        <span className="flex flex-wrap items-center gap-3">
                            Pengadaan {project.number}
                            {payment && <StatusBadge status={payment.status} label={payment.status_label} />}
                        </span>
                    )}
                    subtitle={`${project.customer}${project.company ? ` · ${project.company}` : ''} · ${project.sales_order}`}
                    back={{ href: '/procurement/project-procurements', label: 'Kembali' }}
                />

                {payment?.status === 'rejected_pm' && payment.pm_notes && (
                    <div className="rounded-xl border border-danger/25 bg-danger-soft px-4 py-3 text-sm font-medium text-danger">
                        Ditolak Project Manager{payment.pm_reviewed_by ? ` (${payment.pm_reviewed_by})` : ''}: {payment.pm_notes}. Perbaiki lalu ajukan ulang.
                    </div>
                )}
                {errors.procurement_payment && (
                    <div className="rounded-xl border border-danger/25 bg-danger-soft px-4 py-3 text-sm font-medium text-danger">{errors.procurement_payment}</div>
                )}
                {errors.lines && (
                    <div className="rounded-xl border border-danger/25 bg-danger-soft px-4 py-3 text-sm font-medium text-danger">{errors.lines}</div>
                )}

                {payment && (
                    <Card>
                        <InfoGrid cols={3}>
                            <Info label="Nomor Pengajuan" value={payment.number} />
                            <Info label="Mode Harga" value={payment.pricing_mode === 'lump_sum' ? 'Borongan (1 vendor)' : 'Per item'} />
                            <Info label="Disetujui PM" value={payment.pm_reviewed_by} />
                            <Info label="Dibayar Finance" value={payment.finance_paid_by} />
                        </InfoGrid>
                    </Card>
                )}

                <Card padded={false}>
                    <CardHeader
                        title="Rincian Kebutuhan Barang"
                        subtitle={editable
                            ? 'Isi vendor & harga tiap item, atau tandai "Stok kantor" bila tidak perlu dibeli.'
                            : 'Sourcing terkunci karena pengajuan sedang diproses.'}
                    />

                    {editable && (
                        <div className="flex flex-wrap items-center gap-4 border-b border-border px-5 py-3 text-sm">
                            <span className="font-medium text-text">Mode harga:</span>
                            <label className="flex items-center gap-1.5">
                                <input type="radio" name="pm" checked={!isLump} onChange={() => setData('pricing_mode', 'itemized')} className="accent-navy" /> Per item
                            </label>
                            <label className="flex items-center gap-1.5">
                                <input type="radio" name="pm" checked={isLump} onChange={() => setData('pricing_mode', 'lump_sum')} className="accent-navy" /> Borongan (1 vendor)
                            </label>
                        </div>
                    )}

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b border-border bg-surface-2 text-[11px] font-bold uppercase tracking-wider text-text-faint">
                                    <th className="px-4 py-3">Item</th>
                                    <th className="px-4 py-3">Qty</th>
                                    <th className="px-4 py-3">Vendor</th>
                                    <th className="px-4 py-3 text-right">Harga Satuan</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {items.map((it, i) => {
                                    const line = data.lines[i];
                                    const rowEditable = editable && !it.row_locked;
                                    return (
                                        <tr key={it.id}>
                                            <td className="px-4 py-3.5">
                                                <div className="font-medium text-text">{it.item_name}{it.is_extra ? ' · ekstra' : ''}</div>
                                                {rowEditable && (
                                                    <label className="mt-1 flex items-center gap-1.5 text-[11px] text-text-muted">
                                                        <input type="checkbox" checked={line.from_office_stock} onChange={(e) => setLine(i, { from_office_stock: e.target.checked })} className="accent-navy" />
                                                        Ada stok kantor
                                                    </label>
                                                )}
                                                {rowEditable && line.from_office_stock && (
                                                    <input value={line.office_stock_note} onChange={(e) => setLine(i, { office_stock_note: e.target.value })} placeholder="catatan (opsional)" className={`mt-1 ${CONTROL}`} />
                                                )}
                                                {!rowEditable && it.from_office_stock && <div className="text-[11px] text-text-muted">Stok kantor{it.office_stock_note ? ` — ${it.office_stock_note}` : ''}</div>}
                                            </td>
                                            <td className="whitespace-nowrap px-4 py-3.5 text-text-muted">{it.qty} {it.unit}</td>
                                            <td className="min-w-48 px-4 py-3.5">
                                                {rowEditable && !line.from_office_stock && !isLump ? (
                                                    <select value={line.vendor_id} onChange={(e) => pickVendor(i, e.target.value)} className={CONTROL}>
                                                        <option value="">— pilih vendor —</option>
                                                        {vendors.map((v) => <option key={v.id} value={v.id}>{v.name}</option>)}
                                                    </select>
                                                ) : (
                                                    <span className="text-text-muted">{line.from_office_stock ? '—' : (it.vendor_name || (isLump ? 'borongan' : '—'))}</span>
                                                )}
                                                {rowEditable && !line.from_office_stock && !isLump && catalog.length > 0 && (
                                                    <select onChange={(e) => pickVendorProduct(i, e.target.value)} value="" className={`mt-1 ${CONTROL}`}>
                                                        <option value="">↳ ambil dari katalog…</option>
                                                        {catalog.map((c) => <option key={c.id} value={c.id}>{c.vendor?.name} · {c.item_name} ({money(c.price)})</option>)}
                                                    </select>
                                                )}
                                                {rowEditable && !line.from_office_stock && !isLump && (
                                                    <input
                                                        value={line.bank_account_note}
                                                        onChange={(e) => setLine(i, { bank_account_note: e.target.value })}
                                                        placeholder="Info rekening (opsional)"
                                                        className={`mt-1 ${CONTROL}`}
                                                    />
                                                )}
                                                {!rowEditable && !it.from_office_stock && it.bank_account_note && (
                                                    <div className="mt-1 text-[11px] text-text-muted">Rek: {it.bank_account_note}</div>
                                                )}
                                            </td>
                                            <td className="min-w-32 px-4 py-3.5 text-right">
                                                {rowEditable && !line.from_office_stock && !isLump ? (
                                                    <CurrencyInput value={line.cost_price} onChange={(e) => setLine(i, { cost_price: e.target.value })} className={`${CONTROL} text-right`} />
                                                ) : (
                                                    <span className="tabular-nums text-text-muted">{line.from_office_stock ? '—' : money(it.cost_price)}</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3.5">
                                                {it.from_office_stock
                                                    ? <span className="badge badge-neutral">Stok kantor</span>
                                                    : it.status === 'received'
                                                        ? <span className="badge badge-success">Diterima</span>
                                                        : it.is_paid
                                                            ? <span className="badge badge-primary">Dibayar</span>
                                                            : <span className="badge badge-warning">Menunggu</span>}
                                            </td>
                                            <td className="px-4 py-3.5 text-right">
                                                {it.is_paid && it.status !== 'received' && (
                                                    <Button size="sm" variant="outline" onClick={() => receiveItem(it.id)}>Tandai Diterima</Button>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                            {editable && !isLump && (
                                <tfoot className="border-t border-border bg-surface-2">
                                    <tr>
                                        <td colSpan="3" className="px-4 py-2.5 text-right font-medium text-text-muted">Estimasi total dibeli</td>
                                        <td className="px-4 py-2.5 text-right font-semibold tabular-nums">{money(itemizedTotal)}</td>
                                        <td colSpan="2"></td>
                                    </tr>
                                </tfoot>
                            )}
                        </table>
                    </div>

                    {editable && isLump && (
                        <div className="grid gap-4 border-t border-border p-5 sm:grid-cols-2">
                            <label className="text-sm font-medium text-text">Vendor borongan
                                <select value={data.lump_sum_vendor_id} onChange={(e) => pickLumpSumVendor(e.target.value)} className="input">
                                    <option value="">— pilih vendor —</option>
                                    {vendors.map((v) => <option key={v.id} value={v.id}>{v.name}</option>)}
                                </select>
                            </label>
                            <label className="text-sm font-medium text-text">Total harga borongan (Rp)
                                <CurrencyInput value={data.lump_sum_amount} onChange={(e) => setData('lump_sum_amount', e.target.value)} className="input" />
                            </label>
                            <label className="text-sm font-medium text-text sm:col-span-2">Info Rekening <span className="font-normal text-text-muted">(opsional)</span>
                                <textarea rows={2} value={data.bank_account_note} onChange={(e) => setData('bank_account_note', e.target.value)} placeholder="Otomatis dari data vendor, bisa diubah" className="input" />
                            </label>
                        </div>
                    )}
                    {!editable && payment?.pricing_mode === 'lump_sum' && payment.bank_account_note && (
                        <div className="border-t border-border p-5 text-sm">
                            <span className="text-[11px] font-bold uppercase tracking-wider text-text-faint">Info Rekening</span>
                            <p className="mt-1 whitespace-pre-line text-text">{payment.bank_account_note}</p>
                        </div>
                    )}

                    {editable && (
                        <div className="flex flex-wrap justify-end gap-2 border-t border-border p-4">
                            <Button variant="outline" type="button" onClick={save}>Simpan Sourcing</Button>
                            <Button type="button" icon={FiSend} loading={processing} onClick={submit}>Ajukan ke Project Manager</Button>
                        </div>
                    )}
                    {(canConfirm || canReceiveAll) && (
                        <div className="flex flex-wrap justify-end gap-2 border-t border-border p-4">
                            {canReceiveAll && <Button variant="outline" onClick={receiveAll}>Terima Semua Barang</Button>}
                            {canConfirm && <Button icon={FiCheck} onClick={confirmPayment}>Konfirmasi Pembayaran</Button>}
                        </div>
                    )}
                </Card>

                {history.length > 1 && (
                    <Card padded={false}>
                        <CardHeader title="Riwayat Pengajuan" subtitle="Semua pengajuan pembayaran pengadaan untuk project ini." />
                        <div className="divide-y divide-border">
                            {history.map((h) => (
                                <Link key={h.id} href={`/procurement/procurement-payments/${h.id}`} className="flex items-center justify-between px-5 py-3 text-sm transition hover:bg-bg">
                                    <span className="font-medium text-text">{h.number}</span>
                                    <span className="flex items-center gap-3">
                                        <StatusBadge status={h.status} label={h.status_label} />
                                        <span className="text-xs text-text-faint">{h.submitted_at ? new Date(h.submitted_at).toLocaleDateString('id-ID') : '—'}</span>
                                    </span>
                                </Link>
                            ))}
                        </div>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
