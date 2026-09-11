import { Card, CardHeader, Info, InfoGrid } from './ui';

function money(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(v || 0));
}

/**
 * Ringkasan + rincian item satu pengajuan pembayaran pengadaan.
 * Dipakai halaman persetujuan PM dan pembayaran Finance.
 * `itemActions(item)` opsional → node aksi per baris (mis. checkbox bayar).
 */
export default function ProcurementPaymentDetail({ payment, itemActions }) {
    const overBudget = payment.totals.actual - payment.totals.estimated;

    return (
        <>
            <Card>
                <InfoGrid cols={3}>
                    <Info label="Nomor Pengajuan" value={payment.number} />
                    <Info label="Project" value={`${payment.project.number} · ${payment.project.customer}`} />
                    <Info label="Mode Harga" value={payment.pricing_mode === 'lump_sum' ? `Borongan — ${payment.lump_sum_vendor ?? '—'}` : 'Per item'} />
                    <Info label="Diajukan" value={payment.submitted_by} />
                    <Info label="Disetujui PM" value={payment.pm_reviewed_by} />
                    <Info label="Dibayar Finance" value={payment.finance_paid_by} />
                </InfoGrid>
                {payment.pricing_mode === 'lump_sum' && payment.bank_account_note && (
                    <div className="mt-3 rounded-xl border border-border bg-surface-2 px-4 py-3 text-sm">
                        <span className="font-semibold text-text">Info Rekening ({payment.lump_sum_vendor}):</span> <span className="whitespace-pre-line text-text-muted">{payment.bank_account_note}</span>
                    </div>
                )}
                {payment.pm_notes && (
                    <div className="mt-3 rounded-xl border border-border bg-surface-2 px-4 py-3 text-sm">
                        <span className="font-semibold text-text">Catatan PM:</span> <span className="text-text-muted">{payment.pm_notes}</span>
                    </div>
                )}
            </Card>

            <Card padded={false}>
                <CardHeader title="Rincian Barang" />
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b border-border bg-surface-2 text-[11px] font-bold uppercase tracking-wider text-text-faint">
                                <th className="px-4 py-3">Item</th>
                                <th className="px-4 py-3">Qty</th>
                                <th className="px-4 py-3">Vendor</th>
                                <th className="px-4 py-3 text-right">Estimasi</th>
                                <th className="px-4 py-3 text-right">Harga Aktual</th>
                                <th className="px-4 py-3">Status</th>
                                {itemActions && <th className="px-4 py-3 text-right">Bayar</th>}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {payment.items.map((it) => {
                                const diff = it.line_total - it.estimated_total;
                                return (
                                    <tr key={it.id}>
                                        <td className="px-4 py-3.5">
                                            <div className="font-medium text-text">{it.item_name}{it.is_extra ? ' · ekstra' : ''}</div>
                                            {it.from_office_stock && <div className="text-[11px] text-text-muted">Stok kantor{it.office_stock_note ? ` — ${it.office_stock_note}` : ''}</div>}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3.5 text-text-muted">{it.qty} {it.unit}</td>
                                        <td className="px-4 py-3.5 text-text-muted">
                                            {it.from_office_stock ? '—' : (it.vendor || (payment.pricing_mode === 'lump_sum' ? 'borongan' : '—'))}
                                            {!it.from_office_stock && payment.pricing_mode !== 'lump_sum' && it.bank_account_note && (
                                                <div className="text-[11px] text-text-faint">Rek: {it.bank_account_note}</div>
                                            )}
                                        </td>
                                        <td className="px-4 py-3.5 text-right tabular-nums text-text-muted">{it.from_office_stock ? '—' : money(it.estimated_total)}</td>
                                        <td className="px-4 py-3.5 text-right tabular-nums">
                                            {it.from_office_stock ? '—' : (
                                                <>
                                                    <span className="font-medium text-text">{money(it.line_total)}</span>
                                                    {Math.abs(diff) > 0.5 && (
                                                        <div className={`text-[11px] ${diff > 0 ? 'text-danger' : 'text-success'}`}>
                                                            {diff > 0 ? '+' : '−'}{money(Math.abs(diff))}
                                                        </div>
                                                    )}
                                                </>
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
                                            {it.proofs.length > 0 && (
                                                <span className="ml-2">{it.proofs.map((p, i) => <a key={p.id} href={p.url} target="_blank" rel="noreferrer" className="text-xs font-semibold text-primary hover:underline">bukti{it.proofs.length > 1 ? ` ${i + 1}` : ''}</a>)}</span>
                                            )}
                                        </td>
                                        {itemActions && <td className="px-4 py-3.5 text-right">{itemActions(it)}</td>}
                                    </tr>
                                );
                            })}
                        </tbody>
                        <tfoot className="border-t border-border bg-surface-2 text-text">
                            <tr>
                                <td colSpan="3" className="px-4 py-2.5 text-right text-text-muted">Total estimasi (quotation)</td>
                                <td className="px-4 py-2.5 text-right font-medium tabular-nums">{money(payment.totals.estimated)}</td>
                                <td colSpan={itemActions ? 3 : 2}></td>
                            </tr>
                            <tr>
                                <td colSpan="3" className="px-4 py-2.5 text-right font-semibold">Total aktual dibayar</td>
                                <td className="px-4 py-2.5"></td>
                                <td className="px-4 py-2.5 text-right text-base font-bold tabular-nums">{money(payment.totals.actual)}</td>
                                <td colSpan={itemActions ? 2 : 1}></td>
                            </tr>
                            {Math.abs(overBudget) > 0.5 && (
                                <tr>
                                    <td colSpan={itemActions ? 7 : 6} className={`px-4 py-2 text-right text-xs font-semibold ${overBudget > 0 ? 'text-danger' : 'text-success'}`}>
                                        {overBudget > 0 ? `Melebihi estimasi ${money(overBudget)}` : `Di bawah estimasi ${money(Math.abs(overBudget))}`}
                                    </td>
                                </tr>
                            )}
                        </tfoot>
                    </table>
                </div>
            </Card>

            {payment.request_proofs.length > 0 && (
                <Card>
                    <h2 className="mb-2 font-semibold text-text">Bukti Transfer (seluruh pengajuan)</h2>
                    <div className="flex flex-wrap gap-2">
                        {payment.request_proofs.map((p) => (
                            <a key={p.id} href={p.url} target="_blank" rel="noreferrer" className="rounded-lg border border-primary/30 px-3 py-1.5 text-xs font-semibold text-primary">Bukti transfer</a>
                        ))}
                    </div>
                </Card>
            )}
        </>
    );
}
