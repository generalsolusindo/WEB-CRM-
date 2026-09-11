import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../../Layouts/AppLayout';
import ProcurementPaymentDetail from '../../../Components/ProcurementPaymentDetail';
import { PageHeader, Card, Button, StatusBadge } from '../../../Components/ui';

export default function Show({ payment, canReview }) {
    const form = useForm({ approved: true, notes: '' });
    const [action, setAction] = useState(null);

    function submit(approved) {
        setAction(approved ? 'approve' : 'reject');
        form.transform((data) => ({ ...data, approved }));
        form.post(`/project-manager/procurement-payments/${payment.id}/review`, { preserveScroll: true });
    }

    return (
        <AppLayout>
            <Head title={payment.number} />
            <div className="mx-auto max-w-4xl space-y-5">
                <PageHeader
                    title={<span className="flex items-center gap-3">{payment.number} <StatusBadge status={payment.status} label={payment.status_label} /></span>}
                    subtitle={`${payment.project.number} · ${payment.project.customer}`}
                    back={{ href: '/project-manager/procurement-payments', label: 'Kembali' }}
                />

                <ProcurementPaymentDetail payment={payment} />

                {canReview && (
                    <Card className="space-y-3">
                        <h2 className="font-semibold text-text">Verifikasi Pengadaan</h2>
                        <p className="text-sm text-text-muted">Cek vendor, harga, dan selisih terhadap estimasi quotation. Setujui bila wajar, atau tolak dengan alasan agar Procurement memperbaiki.</p>
                        <textarea
                            value={form.data.notes}
                            onChange={(e) => form.setData('notes', e.target.value)}
                            placeholder="Catatan (wajib bila menolak)"
                            className="input min-h-20 w-full"
                        />
                        {form.errors.notes && <span className="block text-xs font-medium text-danger">{form.errors.notes}</span>}
                        <div className="flex justify-end gap-2">
                            <Button variant="outline" className="border-danger/40 text-danger hover:bg-danger-soft" disabled={form.processing} onClick={() => submit(false)}>
                                {form.processing && action === 'reject' ? 'Memproses…' : 'Tolak'}
                            </Button>
                            <Button className="bg-success text-white hover:bg-success" disabled={form.processing} onClick={() => submit(true)}>
                                {form.processing && action === 'approve' ? 'Memproses…' : 'Setujui & Teruskan ke Finance'}
                            </Button>
                        </div>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
