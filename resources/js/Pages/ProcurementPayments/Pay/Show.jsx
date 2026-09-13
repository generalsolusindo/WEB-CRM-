import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import ProcurementPaymentDetail from '../../../Components/ProcurementPaymentDetail';
import { pickFile } from '../../../utils/fileValidation';
import { PageHeader, Card, Button, StatusBadge } from '../../../Components/ui';

function money(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(v || 0));
}

/** Kelompokkan item yang belum dibayar (dan bukan stok kantor) per vendor — tiap vendor dibayar & dibuktikan terpisah. */
function unpaidByVendor(items) {
    const groups = [];
    const index = {};

    items
        .filter((it) => !it.from_office_stock && !it.is_paid)
        .forEach((it) => {
            const key = it.vendor_id ?? 'unassigned';
            if (!(key in index)) {
                index[key] = groups.length;
                groups.push({ key, label: it.vendor || 'Belum ada vendor', items: [] });
            }
            groups[index[key]].items.push(it);
        });

    return groups;
}

function VendorPaySection({ payment, group }) {
    const form = useForm({
        item_ids: group.items.map((it) => it.id),
        proof_scope: 'all',
        proof: null,
    });

    const toggle = (id) => form.setData('item_ids', form.data.item_ids.includes(id)
        ? form.data.item_ids.filter((x) => x !== id)
        : [...form.data.item_ids, id]);

    function submit(e) {
        e.preventDefault();
        form.post(`/finance/procurement-payments/${payment.id}/pay`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.reset('proof'),
        });
    }

    const total = group.items
        .filter((it) => form.data.item_ids.includes(it.id))
        .reduce((sum, it) => sum + it.line_total, 0);

    return (
        <Card className="space-y-4">
            <div className="flex items-center justify-between">
                <h2 className="font-semibold text-text">Bayar Vendor: {group.label}</h2>
                <span className="text-sm font-semibold text-text-muted">{money(total)}</span>
            </div>

            <div className="divide-y divide-border rounded-xl border border-border">
                {group.items.map((it) => (
                    <label key={it.id} className="flex items-center justify-between gap-3 px-3 py-2.5 text-sm">
                        <span className="flex items-center gap-2.5">
                            <input type="checkbox" checked={form.data.item_ids.includes(it.id)} onChange={() => toggle(it.id)} className="accent-navy" />
                            <span className="text-text">{it.item_name}<span className="text-text-muted"> · {it.qty} {it.unit}</span></span>
                        </span>
                        <span className="tabular-nums text-text-muted">{money(it.line_total)}</span>
                    </label>
                ))}
            </div>

            <form onSubmit={submit} className="space-y-4">
                {group.items.length > 1 && (
                    <div>
                        <span className="block text-sm font-medium text-text">Bukti transfer berlaku untuk</span>
                        <div className="mt-1 flex gap-4 text-sm">
                            <label className="flex items-center gap-1.5">
                                <input type="radio" name={`scope-${group.key}`} checked={form.data.proof_scope === 'all'} onChange={() => form.setData('proof_scope', 'all')} className="accent-navy" />
                                Semua item terpilih (1 bukti)
                            </label>
                            <label className="flex items-center gap-1.5">
                                <input type="radio" name={`scope-${group.key}`} checked={form.data.proof_scope === 'items'} onChange={() => form.setData('proof_scope', 'items')} className="accent-navy" />
                                Ditautkan ke tiap item
                            </label>
                        </div>
                    </div>
                )}

                <div>
                    <span className="block text-sm font-medium text-text">File bukti transfer ke {group.label} (pdf/jpg/png, maks 5 MB) <span className="text-danger">*</span></span>
                    <input
                        type="file"
                        accept=".pdf,.jpg,.jpeg,.png"
                        onChange={(e) => pickFile(form, 'proof', e.target.files[0], 5)}
                        className="mt-1 block w-full text-sm text-text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-bg file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-text"
                    />
                    {form.errors.proof && <span className="text-xs font-medium text-danger">{form.errors.proof}</span>}
                </div>

                {form.errors.item_ids && <span className="block text-xs font-medium text-danger">{form.errors.item_ids}</span>}

                <div className="flex items-center justify-between">
                    <span className="text-sm text-text-muted">{form.data.item_ids.length} item dipilih</span>
                    <Button type="submit" loading={form.processing} disabled={form.data.item_ids.length === 0 || !form.data.proof}>
                        Catat Pembayaran {group.label}
                    </Button>
                </div>
            </form>
        </Card>
    );
}

export default function Show({ payment, canPay }) {
    const vendorGroups = unpaidByVendor(payment.items);

    return (
        <AppLayout>
            <Head title={payment.number} />
            <div className="mx-auto max-w-4xl space-y-5">
                <PageHeader
                    title={<span className="flex items-center gap-3">{payment.number} <StatusBadge status={payment.status} label={payment.status_label} /></span>}
                    subtitle={`${payment.project.number} · ${payment.project.customer}`}
                    back={{ href: '/finance/procurement-payments', label: 'Kembali' }}
                />

                <ProcurementPaymentDetail payment={payment} />

                {canPay && vendorGroups.length > 0 && (
                    <>
                        <p className="text-sm text-text-muted">
                            Item dikelompokkan per vendor — bayar dan lampirkan bukti transfer satu per satu untuk tiap vendor, sesuai transfer bank yang sebenarnya dilakukan.
                        </p>
                        {vendorGroups.map((group) => (
                            <VendorPaySection key={group.key} payment={payment} group={group} />
                        ))}
                    </>
                )}

                {payment.status === 'paid' && (
                    <div className="rounded-xl border border-success/25 bg-success-soft px-4 py-3 text-sm font-medium text-success">
                        Semua item sudah dibayar. Menunggu konfirmasi dari Procurement.
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
