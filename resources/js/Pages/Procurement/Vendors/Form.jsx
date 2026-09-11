import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, Card, Field, Input, Textarea, FormActions } from '../../../Components/ui';

const fields = [
    ['name', 'Nama Vendor', true],
    ['contact_person', 'PIC / Contact Person', false],
    ['phone', 'Telepon', false],
    ['email', 'Email', false],
    ['city', 'Kota / Wilayah', false],
];

export default function Form({ vendor = null }) {
    const editing = Boolean(vendor);
    const { data, setData, post, put, processing, errors } = useForm({
        name: vendor?.name ?? '',
        contact_person: vendor?.contact_person ?? '',
        phone: vendor?.phone ?? '',
        email: vendor?.email ?? '',
        address: vendor?.address ?? '',
        city: vendor?.city ?? '',
        coverage_area: vendor?.coverage_area ?? '',
        bank_account_note: vendor?.bank_account_note ?? '',
        provides_survey: vendor?.provides_survey ?? false,
        provides_technical: vendor?.provides_technical ?? false,
    });

    function submit(e) {
        e.preventDefault();
        editing ? put(`/procurement/vendors/${vendor.id}`) : post('/procurement/vendors');
    }

    const backHref = editing ? `/procurement/vendors/${vendor.id}` : '/procurement/vendors';

    return (
        <AppLayout>
            <Head title={editing ? 'Edit Vendor' : 'Tambah Vendor'} />
            <div className="mx-auto max-w-2xl space-y-5">
                <PageHeader title={editing ? 'Edit Vendor' : 'Tambah Vendor'} back={{ href: backHref, label: 'Kembali' }} />
                <form onSubmit={submit}>
                    <Card className="space-y-5">
                        <div className="grid gap-5 sm:grid-cols-2">
                            {fields.map(([name, label, required]) => (
                                <Field key={name} label={label} required={required} error={errors[name]}>
                                    <Input value={data[name]} onChange={(e) => setData(name, e.target.value)} />
                                </Field>
                            ))}
                        </div>
                        <Field label="Alamat" error={errors.address}>
                            <Textarea rows={3} value={data.address} onChange={(e) => setData('address', e.target.value)} />
                        </Field>
                        <Field label="Cakupan Area Layanan" error={errors.coverage_area}>
                            <Textarea rows={2} value={data.coverage_area} onChange={(e) => setData('coverage_area', e.target.value)} placeholder="Contoh: Jabodetabek, Jawa Barat bagian utara" />
                        </Field>
                        <Field
                            label="Info Rekening"
                            hint="Rekening default vendor ini — muncul otomatis saat dipilih di pengadaan, masih bisa diubah per transaksi bila beda."
                            error={errors.bank_account_note}
                        >
                            <Textarea rows={2} value={data.bank_account_note} onChange={(e) => setData('bank_account_note', e.target.value)} placeholder="Contoh: BCA 1234567890 a.n. PT Jaya Wijaya" />
                        </Field>
                        <div className="space-y-2 rounded-xl border border-border bg-surface-2 p-4">
                            <p className="text-sm font-semibold text-text">Jenis Jasa yang Bisa Ditugaskan</p>
                            <label className="flex items-center gap-2 text-sm text-text">
                                <input type="checkbox" checked={data.provides_survey} onChange={(e) => setData('provides_survey', e.target.checked)} className="accent-navy" />
                                Bisa melakukan survey lapangan
                            </label>
                            <label className="flex items-center gap-2 text-sm text-text">
                                <input type="checkbox" checked={data.provides_technical} onChange={(e) => setData('provides_technical', e.target.checked)} className="accent-navy" />
                                Bisa melakukan pekerjaan teknis / instalasi
                            </label>
                        </div>
                        <FormActions cancelHref={backHref} processing={processing} />
                    </Card>
                </form>
            </div>
        </AppLayout>
    );
}
