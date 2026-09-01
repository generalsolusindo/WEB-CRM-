import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';

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
            <div className="mx-auto max-w-2xl">
                <div className="mb-5">
                    <Link href="/procurement/technicians" className="text-sm text-info">← Kembali</Link>
                    <h1 className="mt-2 text-2xl font-bold text-text">{editing ? 'Edit Akun Surveyor / Teknisi' : 'Tambah Akun Surveyor / Teknisi'}</h1>
                    <p className="text-sm text-text-muted">Role akun otomatis <span className="font-semibold">teknisi</span> (merangkap surveyor).</p>
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
                        <label className="block text-sm font-medium text-text">Asal
                            <select value={data.vendor_id} onChange={(e) => setData('vendor_id', e.target.value)} className="input">
                                <option value="">Internal / Head Office</option>
                                {vendorOptions.map((v) => <option key={v.value} value={v.value}>{v.label}</option>)}
                            </select>
                            {errors.vendor_id && <span className="mt-1 block text-xs text-danger">{errors.vendor_id}</span>}
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
                        Akun aktif (bisa login &amp; ditugaskan)
                    </label>
                    <div className="flex justify-end gap-3">
                        <Link href="/procurement/technicians" className="rounded-lg border border-border px-4 py-2 text-sm text-text-muted">Batal</Link>
                        <button disabled={processing} className="rounded-lg bg-navy px-4 py-2 text-sm font-semibold text-white disabled:opacity-60">{processing ? 'Menyimpan...' : 'Simpan'}</button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
