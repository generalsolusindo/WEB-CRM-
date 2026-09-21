import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { FiTruck } from 'react-icons/fi';
import { Card, CardHeader, Button, Field, Input, Select, Textarea, CurrencyInput, Info, InfoGrid, StatusBadge } from '../../../Components/ui';
import { feedback } from '../../../Components/feedback';

function money(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(v || 0));
}

/** Deal jasa vendor luar per project — diisi Procurement, dibayar Finance (DP lalu pelunasan setelah BAST, atau lunas di akhir). */
export default function VendorServicePanel({ project, vendorService, vendorOptions = [], canManage, serviceCostEstimate = 0 }) {
    const flagged = project.needs_outside_vendor;
    const [open, setOpen] = useState(Boolean(vendorService) || flagged);
    const { data, setData, put, processing, errors } = useForm({
        vendor_id: vendorService?.vendor_id ?? '',
        total_fee: vendorService ? String(Number(vendorService.total_fee)) : '',
        terms: vendorService?.terms ?? 'dp_final',
        dp_amount: vendorService?.dp_amount != null ? String(Number(vendorService.dp_amount)) : '',
        bank_name: vendorService?.bank_name ?? '',
        account_number: vendorService?.account_number ?? '',
        account_holder: vendorService?.account_holder ?? '',
        notes: vendorService?.notes ?? '',
    });

    const total = Number(data.total_fee) || 0;
    const dp = data.terms === 'dp_final' ? Number(data.dp_amount) || 0 : 0;
    const dpPercent = total > 0 && dp > 0 ? Math.round((dp / total) * 10000) / 100 : null;
    const finalAmount = Math.max(total - dp, 0);
    const overEstimate = total > 0 && total > serviceCostEstimate ? total - serviceCostEstimate : 0;

    function submit(e) {
        e.preventDefault();
        feedback.expect({
            success: { title: data.terms === 'dp_final' ? 'Deal vendor tersimpan' : 'Deal vendor tersimpan, project dilepas', style: 'popup' },
            error: { title: 'Deal vendor belum tersimpan' },
        });
        put(`/procurement/project-procurements/${project.id}/vendor-service`, { preserveScroll: true });
    }

    if (!open) {
        return (
            <div className="flex justify-end">
                <Button variant="outline" icon={FiTruck} onClick={() => setOpen(true)}>Tambah Deal Vendor Jasa</Button>
            </div>
        );
    }

    return (
        <Card padded={false}>
            <CardHeader
                title={(
                    <span className="flex flex-wrap items-center gap-3">
                        Deal Vendor Jasa
                        {vendorService && <StatusBadge status={vendorService.status} label={vendorService.status_label} />}
                    </span>
                )}
                subtitle="Pekerjaan di luar jangkauan tim sendiri. Setelah disimpan, Finance dan Operasional otomatis diberi tahu sesuai termin."
            />
            {flagged && !vendorService && (
                <div className="border-b border-border bg-warning-soft px-5 py-3 text-sm font-medium text-warning">
                    Sales menandai project ini kemungkinan butuh vendor luar. Carikan vendor, lalu isi deal-nya di sini.
                </div>
            )}

            {!canManage ? (
                <div className="px-5 py-4">
                    {vendorService ? <Summary v={vendorService} /> : <p className="text-sm text-text-muted">Belum ada deal vendor.</p>}
                </div>
            ) : (
                <form onSubmit={submit} className="space-y-4 px-5 py-4">
                    {errors.vendor_service && <p className="text-sm font-medium text-danger">{errors.vendor_service}</p>}
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Vendor" required error={errors.vendor_id}>
                            <Select value={data.vendor_id} onChange={(e) => setData('vendor_id', e.target.value)}>
                                <option value="">— pilih vendor —</option>
                                {vendorOptions.map((v) => <option key={v.id} value={v.id}>{v.name}</option>)}
                            </Select>
                        </Field>
                        <Field label="Total Fee Vendor" required error={errors.total_fee}>
                            <CurrencyInput value={data.total_fee} onChange={(e) => setData('total_fee', e.target.value)} className="input" />
                        </Field>
                    </div>

                    {overEstimate > 0 && (
                        <div className="rounded-xl border border-warning/25 bg-warning-soft px-4 py-3 text-sm font-medium text-warning">
                            {serviceCostEstimate > 0
                                ? <>Fee ini melebihi estimasi biaya jasa di quotation ({money(serviceCostEstimate)}) sebesar <strong>{money(overEstimate)}</strong> — profit project berkurang. Pastikan Manager sudah tahu sebelum deal disepakati.</>
                                : <>Quotation tidak punya estimasi biaya jasa, jadi seluruh fee ini ({money(total)}) langsung mengurangi profit project.</>}
                        </div>
                    )}

                    <div>
                        <div className="mb-2 text-sm font-medium text-text">Termin Pembayaran</div>
                        <div className="flex flex-wrap gap-4 text-sm">
                            <label className="flex items-center gap-1.5">
                                <input type="radio" name="terms" checked={data.terms === 'dp_final'} onChange={() => setData('terms', 'dp_final')} className="accent-navy" /> DP dulu, pelunasan setelah BAST
                            </label>
                            <label className="flex items-center gap-1.5">
                                <input type="radio" name="terms" checked={data.terms === 'pay_at_end'} onChange={() => setData('terms', 'pay_at_end')} className="accent-navy" /> Langsung lunas di akhir (setelah BAST)
                            </label>
                        </div>
                    </div>

                    {data.terms === 'dp_final' && (
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Nominal DP" required error={errors.dp_amount}
                                hint={dpPercent != null ? `${dpPercent}% dari total fee` : 'Sesuai permintaan vendor'}>
                                <CurrencyInput value={data.dp_amount} onChange={(e) => setData('dp_amount', e.target.value)} className="input" />
                            </Field>
                            <div className="rounded-xl bg-bg px-4 py-3 text-sm">
                                <div className="text-xs text-text-muted">Pelunasan setelah BAST</div>
                                <div className="text-lg font-bold text-text">{money(finalAmount)}</div>
                            </div>
                        </div>
                    )}

                    <div className="grid gap-4 sm:grid-cols-3">
                        <Field label="Bank / Jenis Rekening" required error={errors.bank_name}>
                            <Input value={data.bank_name} onChange={(e) => setData('bank_name', e.target.value)} placeholder="mis. BCA, Mandiri" />
                        </Field>
                        <Field label="Nomor Rekening" required error={errors.account_number}>
                            <Input value={data.account_number} onChange={(e) => setData('account_number', e.target.value)} />
                        </Field>
                        <Field label="Atas Nama" required error={errors.account_holder}>
                            <Input value={data.account_holder} onChange={(e) => setData('account_holder', e.target.value)} />
                        </Field>
                    </div>

                    <Field label="Catatan (opsional)" error={errors.notes}>
                        <Textarea rows={2} value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                    </Field>

                    <div className="flex justify-end">
                        <Button type="submit" loading={processing}>{vendorService ? 'Simpan Perubahan' : 'Simpan Deal & Teruskan'}</Button>
                    </div>
                </form>
            )}
        </Card>
    );
}

function Summary({ v }) {
    return (
        <InfoGrid cols={3}>
            <Info label="Nomor" value={v.number} />
            <Info label="Vendor" value={v.vendor_name} />
            <Info label="Total Fee" value={money(v.total_fee)} />
            <Info label="Termin" value={v.terms === 'dp_final' ? `DP ${money(v.dp_amount)} (${v.dp_percent}%) + pelunasan ${money(v.final_amount)}` : 'Lunas setelah BAST'} />
            <Info label="Rekening" value={`${v.bank_name} · ${v.account_number} a.n. ${v.account_holder}`} />
        </InfoGrid>
    );
}
