import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, Card, Field, Input, Select, FormActions } from '../../../Components/ui';

export default function Form({ targetUser = null, roleOptions, isSelf = false }) {
    const editing = Boolean(targetUser);
    const { data, setData, post, put, processing, errors } = useForm({
        name: targetUser?.name ?? '',
        username: targetUser?.username ?? '',
        role: targetUser?.role ?? '',
        password: '',
        password_confirmation: '',
        is_active: targetUser?.is_active ?? true,
    });

    function submit(e) {
        e.preventDefault();
        editing ? put(`/admin/users/${targetUser.id}`) : post('/admin/users');
    }

    return (
        <AppLayout>
            <Head title={editing ? 'Edit User' : 'Tambah User'} />
            <div className="mx-auto max-w-2xl space-y-5">
                <PageHeader
                    title={editing ? `Edit User — ${targetUser.name}` : 'Tambah User'}
                    back={{ href: '/admin/users', label: 'Kembali ke Manajemen User' }}
                />
                <form onSubmit={submit}>
                    <Card className="space-y-5">
                        <div className="grid gap-5 sm:grid-cols-2">
                            <Field label="Nama" required error={errors.name}>
                                <Input value={data.name} onChange={(e) => setData('name', e.target.value)} />
                            </Field>
                            <Field label="Username (untuk login)" required error={errors.username} hint="Huruf kecil, dipakai untuk login.">
                                <Input value={data.username} onChange={(e) => setData('username', e.target.value.toLowerCase())} />
                            </Field>
                            {editing ? (
                                <Field label="Role">
                                    <Input value={roleOptions[data.role] ?? data.role} disabled className="bg-surface-2 text-text-muted" />
                                    <span className="mt-1 block text-xs text-text-muted">Role tidak bisa diubah — buat akun baru kalau perlu role berbeda.</span>
                                </Field>
                            ) : (
                                <Field label="Role" required error={errors.role}>
                                    <Select value={data.role} onChange={(e) => setData('role', e.target.value)}>
                                        <option value="">— pilih role —</option>
                                        {Object.entries(roleOptions).map(([value, label]) => (
                                            <option key={value} value={value}>{label}</option>
                                        ))}
                                    </Select>
                                </Field>
                            )}
                            <Field label={editing ? 'Password baru (kosongkan bila tidak diubah)' : 'Password'} required={!editing} error={errors.password}>
                                <Input type="password" value={data.password} onChange={(e) => setData('password', e.target.value)} />
                            </Field>
                            <Field label="Konfirmasi Password">
                                <Input type="password" value={data.password_confirmation} onChange={(e) => setData('password_confirmation', e.target.value)} />
                            </Field>
                        </div>
                        <label className={`flex items-center gap-2 text-sm font-medium ${isSelf ? 'text-text-faint' : 'text-text'}`}>
                            <input
                                type="checkbox"
                                checked={data.is_active}
                                disabled={isSelf}
                                onChange={(e) => setData('is_active', e.target.checked)}
                                className="accent-navy disabled:cursor-not-allowed"
                            />
                            Akun aktif (bisa login)
                            {isSelf && <span className="text-xs text-text-faint">— tidak bisa menonaktifkan akun sendiri</span>}
                        </label>
                        <FormActions cancelHref="/admin/users" processing={processing} />
                    </Card>
                </form>
            </div>
        </AppLayout>
    );
}
