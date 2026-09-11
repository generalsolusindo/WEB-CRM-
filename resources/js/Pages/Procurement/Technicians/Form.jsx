import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, Card, Field, Input, Select, FormActions } from '../../../Components/ui';

export default function Form({ technician = null, vendorOptions = [] }) {
    const editing = Boolean(technician);
    const { data, setData, post, put, processing, errors } = useForm({
        name: technician?.name ?? '',
        email: technician?.email ?? '',
        phone: technician?.phone ?? '',
        password: '',
        password_confirmation: '',
        vendor_id: technician?.vendor_id ?? '',
        is_active: technician?.is_active ?? true,
    });

    function submit(e) {
        e.preventDefault();
        editing ? put(`/procurement/technicians/${technician.id}`) : post('/procurement/technicians');
    }

    return (
        <AppLayout>
            <Head title={editing ? 'Edit Akun Teknisi' : 'Tambah Akun Teknisi'} />
            <div className="mx-auto max-w-2xl space-y-5">
                <PageHeader
                    title={editing ? 'Edit Akun Surveyor / Teknisi' : 'Tambah Akun Surveyor / Teknisi'}
                    subtitle={<>Role akun otomatis <span className="font-semibold">teknisi</span> (merangkap surveyor).</>}
                    back={{ href: '/procurement/technicians', label: 'Kembali' }}
                />
                <form onSubmit={submit}>
                    <Card className="space-y-5">
                        <div className="grid gap-5 sm:grid-cols-2">
                            <Field label="Nama" required error={errors.name}>
                                <Input value={data.name} onChange={(e) => setData('name', e.target.value)} />
                            </Field>
                            <Field label="Email" required error={errors.email}>
                                <Input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                            </Field>
                            <Field label="Telepon" error={errors.phone}>
                                <Input value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                            </Field>
                            <Field label="Asal" error={errors.vendor_id}>
                                <Select value={data.vendor_id} onChange={(e) => setData('vendor_id', e.target.value)}>
                                    <option value="">Internal / Head Office</option>
                                    {vendorOptions.map((v) => <option key={v.value} value={v.value}>{v.label}</option>)}
                                </Select>
                            </Field>
                            <Field label={editing ? 'Password baru (kosongkan bila tidak diubah)' : 'Password'} required={!editing} error={errors.password}>
                                <Input type="password" value={data.password} onChange={(e) => setData('password', e.target.value)} />
                            </Field>
                            <Field label="Konfirmasi Password">
                                <Input type="password" value={data.password_confirmation} onChange={(e) => setData('password_confirmation', e.target.value)} />
                            </Field>
                        </div>
                        <label className="flex items-center gap-2 text-sm font-medium text-text">
                            <input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} className="accent-navy" />
                            Akun aktif (bisa login &amp; ditugaskan)
                        </label>
                        <FormActions cancelHref="/procurement/technicians" processing={processing} />
                    </Card>
                </form>
            </div>
        </AppLayout>
    );
}
