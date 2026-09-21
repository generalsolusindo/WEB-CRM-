import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { PageHeader, Card, Field, Button } from '../../Components/ui';
import { feedback } from '../../Components/feedback';

export default function Signature({ signatureUrl }) {
    const form = useForm({ signature: null });

    function submit(e) {
        e.preventDefault();
        feedback.expect({ success: { title: 'Tanda tangan tersimpan', style: 'popup' } });
        form.post('/admin/signature', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.reset('signature'),
        });
    }

    return (
        <AppLayout>
            <Head title="Tanda Tangan Administrator" />
            <div className="mx-auto max-w-xl space-y-5">
                <PageHeader
                    title="Tanda Tangan Administrator"
                    subtitle="Gambar tanda tangan ini dipakai otomatis untuk slot TTD internal (Operasional & Project Manager) di setiap dokumen SOW — tidak perlu digambar ulang setiap kali."
                />
                <form onSubmit={submit}>
                    <Card className="space-y-5">
                        {signatureUrl && (
                            <div>
                                <span className="block text-sm font-medium text-text">Tanda tangan saat ini</span>
                                <img src={signatureUrl} alt="Tanda tangan Administrator" className="mt-2 h-24 rounded-lg border border-border bg-white object-contain p-2" />
                            </div>
                        )}
                        <Field label={signatureUrl ? 'Ganti dengan gambar baru' : 'Upload gambar tanda tangan'} error={form.errors.signature}>
                            <input
                                type="file" accept=".jpg,.jpeg,.png"
                                onChange={(e) => form.setData('signature', e.target.files[0] ?? null)}
                                className="block w-full text-sm text-text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-primary-soft file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-primary-strong"
                            />
                        </Field>
                        <div className="flex justify-end">
                            <Button type="submit" loading={form.processing} disabled={!form.data.signature}>Simpan Tanda Tangan</Button>
                        </div>
                    </Card>
                </form>
            </div>
        </AppLayout>
    );
}
