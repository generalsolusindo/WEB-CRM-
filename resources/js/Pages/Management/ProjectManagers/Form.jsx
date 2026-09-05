import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';

export default function Form({ projectManager = null }) {
    const editing = Boolean(projectManager);
    const { data, setData, post, put, processing, errors } = useForm({
        name: projectManager?.name ?? '',
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
            <div className="mx-auto max-w-2xl">
                <div className="mb-5">
                    <Link href="/management/project-managers" className="text-sm text-info">← Kembali</Link>
                    <h1 className="mt-2 text-2xl font-bold text-text">{editing ? 'Edit Akun Project Manager' : 'Tambah Akun Project Manager'}</h1>
                    <p className="text-sm text-text-muted">Role akun otomatis <span className="font-semibold">project manager</span> — bawahan Manager, dipakai untuk delegasi project.</p>
                </div>
                <form onSubmit={submit} className="space-y-5 rounded-xl border border-border bg-surface p-6 shadow-sm">
                    <div className="grid gap-5 sm:grid-cols-2">
                        <label className="block text-sm font-medium text-text">Nama *
                            <input value={data.name} onChange={(e) => setData('name', e.target.value)} className="input" />
                            {errors.name && <span className="mt-1 block text-xs text-danger">{errors.name}</span>}
                        </label>
                        <label className="block text-sm font-medium text-text">Email *
                            <input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} className="input" />
                            {errors.email && <span className="mt-1 block text-xs text-danger">{errors.email}</span>}
                        </label>
                        <label className="block text-sm font-medium text-text">Telepon
                            <input value={data.phone} onChange={(e) => setData('phone', e.target.value)} className="input" />
                            {errors.phone && <span className="mt-1 block text-xs text-danger">{errors.phone}</span>}
                        </label>
                        <label className="block text-sm font-medium text-text">{editing ? 'Password baru (kosongkan bila tidak diubah)' : 'Password *'}
                            <input type="password" value={data.password} onChange={(e) => setData('password', e.target.value)} className="input" />
                            {errors.password && <span className="mt-1 block text-xs text-danger">{errors.password}</span>}
                        </label>
                        <label className="block text-sm font-medium text-text">Konfirmasi Password
                            <input type="password" value={data.password_confirmation} onChange={(e) => setData('password_confirmation', e.target.value)} className="input" />
                        </label>
                    </div>
                    <label className="flex items-center gap-2 text-sm font-medium text-text">
                        <input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} />
                        Akun aktif (bisa login &amp; menerima delegasi project)
                    </label>
                    <div className="flex justify-end gap-3">
                        <Link href="/management/project-managers" className="rounded-lg border border-border px-4 py-2 text-sm text-text-muted">Batal</Link>
                        <button disabled={processing} className="rounded-lg bg-navy px-4 py-2 text-sm font-semibold text-white disabled:opacity-60">{processing ? 'Menyimpan...' : 'Simpan'}</button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
