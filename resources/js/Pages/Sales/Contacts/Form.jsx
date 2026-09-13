import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, Card, Field, Input, Textarea, FormActions } from '../../../Components/ui';

export default function Form({ contact = null, npwpDocumentUrl = null }) {
    const editing = Boolean(contact);
    const { data, setData, post, put, processing, errors } = useForm({
        name: contact?.name ?? '', company_name: contact?.company_name ?? '',
        email: contact?.email ?? '', phone: contact?.phone ?? '', npwp: contact?.npwp ?? '',
        npwp_document: null,
        address: contact?.address ?? '', notes: contact?.notes ?? '',
    });

    function submit(e) {
        e.preventDefault();
        const options = { forceFormData: true };
        editing ? put(`/sales/contacts/${contact.id}`, options) : post('/sales/contacts', options);
    }

    return (
        <AppLayout>
            <Head title={editing ? 'Edit Contact' : 'Tambah Contact'} />
            <div className="mx-auto max-w-3xl space-y-5">
                <PageHeader
                    title={editing ? 'Edit Contact' : 'Tambah Contact'}
                    subtitle="Isi informasi customer atau PIC."
                    back={{ href: editing ? `/sales/contacts/${contact.id}` : '/sales/contacts' }}
                />

                <form onSubmit={submit}>
                    <Card className="space-y-5">
                        <div className="grid gap-5 sm:grid-cols-2">
                            <Field label="Nama" required error={errors.name}>
                                <Input value={data.name} onChange={(e) => setData('name', e.target.value)} />
                            </Field>
                            <Field label="Perusahaan" error={errors.company_name}>
                                <Input value={data.company_name} onChange={(e) => setData('company_name', e.target.value)} />
                            </Field>
                            <Field label="Email" error={errors.email}>
                                <Input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                            </Field>
                            <Field label="Telepon" error={errors.phone}>
                                <Input value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                            </Field>
                            <Field label="NPWP" error={errors.npwp} hint="Isi kalau customer ini punya NPWP — dipakai Procurement sebagai penanda kebutuhan ini perlu PPN.">
                                <Input value={data.npwp} onChange={(e) => setData('npwp', e.target.value)} />
                            </Field>
                            <Field label="Dokumen NPWP (opsional)" error={errors.npwp_document}>
                                <input
                                    type="file" accept=".jpg,.jpeg,.png,.pdf"
                                    onChange={(e) => setData('npwp_document', e.target.files[0] ?? null)}
                                    className="block w-full text-sm text-text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-primary-soft file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-primary-strong"
                                />
                                {npwpDocumentUrl && (
                                    <a href={npwpDocumentUrl} target="_blank" rel="noreferrer" className="mt-1 inline-block text-xs font-medium text-primary hover:underline">
                                        Lihat dokumen NPWP saat ini
                                    </a>
                                )}
                            </Field>
                        </div>
                        <Field label="Alamat" error={errors.address}>
                            <Textarea rows={3} value={data.address} onChange={(e) => setData('address', e.target.value)} />
                        </Field>
                        <Field label="Catatan" error={errors.notes}>
                            <Textarea rows={3} value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                        </Field>

                        <FormActions cancelHref={editing ? `/sales/contacts/${contact.id}` : '/sales/contacts'} processing={processing} />
                    </Card>
                </form>
            </div>
        </AppLayout>
    );
}
