import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, Card, Field, Input, FormActions } from '../../../Components/ui';

export default function Form({ projectManager = null }) {
    const editing = Boolean(projectManager);
    const { data, setData, post, put, processing, errors } = useForm({
        name: projectManager?.name ?? '',
        username: projectManager?.username ?? '',
        email: projectManager?.email ?? '',
        phone: projectManager?.phone ?? '',
        password: '',
        password_confirmation: '',
        is_active: projectManager?.is_active ?? true,
    });

    function submit(e) {
        e.preventDefault();
        editing ? put(`/management/project-managers/${projectManager.id}`) : post('/management/project-managers');
    }

    return (
        <AppLayout>
            <Head title={editing ? 'Edit Akun Project Manager' : 'Tambah Akun Project Manager'} />
            <div className="mx-auto max-w-2xl space-y-5">
                <PageHeader
                    title={editing ? 'Edit Akun Project Manager' : 'Tambah Akun Project Manager'}
                    subtitle={<>Role akun otomatis <span className="font-semibold">project manager</span> — bawahan Manager, dipakai untuk delegasi project.</>}
                    back={{ href: '/management/project-managers', label: 'Kembali' }}
                />
                <form onSubmit={submit}>
                    <Card className="space-y-5">
                        <div className="grid gap-5 sm:grid-cols-2">
                            <Field label="Nama" required error={errors.name}>
                                <Input value={data.name} onChange={(e) => setData('name', e.target.value)} />
                            </Field>
                            <Field label="Username (untuk login)" required error={errors.username} hint="Huruf kecil, dipakai untuk login menggantikan email.">
                                <Input value={data.username} onChange={(e) => setData('username', e.target.value.toLowerCase())} />
                            </Field>
                            <Field label="Email" required error={errors.email}>
                                <Input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                            </Field>
                            <Field label="Telepon" error={errors.phone}>
                                <Input value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
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
                            Akun aktif (bisa login &amp; menerima delegasi project)
                        </label>
                        <FormActions cancelHref="/management/project-managers" processing={processing} />
                    </Card>
                </form>
            </div>
        </AppLayout>
    );
}
