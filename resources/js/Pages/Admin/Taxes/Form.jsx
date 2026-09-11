import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, Card, Field, Input, FormActions } from '../../../Components/ui';

export default function Form({ tax = null }) {
    const editing = Boolean(tax);
    const { data, setData, post, put, processing, errors } = useForm({
        name: tax?.name ?? '',
        rate: tax?.rate ?? '',
        is_active: tax ? Boolean(tax.is_active) : true,
    });

    function submit(event) {
        event.preventDefault();
        editing ? put(`/admin/taxes/${tax.id}`) : post('/admin/taxes');
    }

    return (
        <AppLayout>
            <Head title={editing ? 'Edit Pajak' : 'Tambah Pajak'} />
            <div className="mx-auto max-w-xl space-y-5">
                <PageHeader
                    title={editing ? 'Edit Pajak' : 'Tambah Pajak'}
                    back={{ href: '/admin/taxes', label: 'Kembali ke Master Pajak' }}
                />
                <form onSubmit={submit}>
                    <Card className="space-y-5">
                        <Field label="Nama" required error={errors.name}>
                            <Input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                        </Field>
                        <Field label="Tarif (%)" required error={errors.rate}>
                            <Input type="number" step="0.01" min="0" max="100" value={data.rate} onChange={(e) => setData('rate', e.target.value)} />
                        </Field>
                        <label className="flex items-center gap-2 text-sm font-medium text-text">
                            <input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} className="accent-navy" />
                            Aktif (bisa dipilih di Quotation)
                        </label>
                        <FormActions cancelHref="/admin/taxes" processing={processing} />
                    </Card>
                </form>
            </div>
        </AppLayout>
    );
}
