import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import ProcurementPaymentDetail from '../../../Components/ProcurementPaymentDetail';
import { pickFile } from '../../../utils/fileValidation';
import { PageHeader, Card, Button, StatusBadge } from '../../../Components/ui';

export default function Show({ payment, canPay }) {
    const unpaid = payment.items.filter((it) => !it.from_office_stock && !it.is_paid);

    const form = useForm({
        item_ids: unpaid.map((it) => it.id),
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

    const itemActions = canPay
        ? (it) => (it.from_office_stock || it.is_paid
            ? <span className="text-xs text-text-muted">{it.is_paid ? 'lunas' : '—'}</span>
            : <input type="checkbox" checked={form.data.item_ids.includes(it.id)} onChange={() => toggle(it.id)} className="accent-navy" />)
        : undefined;

    return (
        <AppLayout>
            <Head title={payment.number} />
            <div className="mx-auto max-w-4xl space-y-5">
                <PageHeader
                    title={<span className="flex items-center gap-3">{payment.number} <StatusBadge status={payment.status} label={payment.status_label} /></span>}
                    subtitle={`${payment.project.number} · ${payment.project.customer}`}
                    back={{ href: '/finance/procurement-payments', label: 'Kembali' }}
                />

                <ProcurementPaymentDetail payment={payment} itemActions={itemActions} />

                {canPay && (
                    <Card className="space-y-4">
                        <h2 className="font-semibold text-text">Catat Pembayaran ke Vendor</h2>
                        <p className="text-sm text-text-muted">Centang item yang dibayar, lampirkan bukti transfer. Bisa dibayar sekaligus atau bertahap per vendor.</p>

                        <form onSubmit={submit} className="space-y-4">
                            <div>
                                <span className="block text-sm font-medium text-text">Bukti transfer berlaku untuk</span>
                                <div className="mt-1 flex gap-4 text-sm">
                                    <label className="flex items-center gap-1.5">
                                        <input type="radio" name="scope" checked={form.data.proof_scope === 'all'} onChange={() => form.setData('proof_scope', 'all')} className="accent-navy" />
                                        Semua item terpilih (1 bukti)
                                    </label>
                                    <label className="flex items-center gap-1.5">
                                        <input type="radio" name="scope" checked={form.data.proof_scope === 'items'} onChange={() => form.setData('proof_scope', 'items')} className="accent-navy" />
                                        Ditautkan ke tiap item
                                    </label>
                                </div>
                            </div>

                            <div>
                                <span className="block text-sm font-medium text-text">File bukti transfer (pdf/jpg/png, maks 5 MB) <span className="text-danger">*</span></span>
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
                                <Button type="submit" loading={form.processing} disabled={form.data.item_ids.length === 0 || !form.data.proof}>Catat Pembayaran</Button>
                            </div>
                        </form>
                    </Card>
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
