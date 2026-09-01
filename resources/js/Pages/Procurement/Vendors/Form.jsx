import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';

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
        provides_survey: vendor?.provides_survey ?? false,
        provides_technical: vendor?.provides_technical ?? false,
    });

    function submit(e) {
        e.preventDefault();
        editing ? put(`/procurement/vendors/${vendor.id}`) : post('/procurement/vendors');
    }

    return (
        <AppLayout>
            <Head title={editing ? 'Edit Vendor' : 'Tambah Vendor'} />
            <div className="mx-auto max-w-2xl">
                <div className="mb-5">
                    <Link href={editing ? `/procurement/vendors/${vendor.id}` : '/procurement/vendors'} className="text-sm text-info">← Kembali</Link>
                    <h1 className="mt-2 text-2xl font-bold text-text">{editing ? 'Edit Vendor' : 'Tambah Vendor'}</h1>
                </div>
                <form onSubmit={submit} className="space-y-5 rounded-xl border border-border bg-surface p-6 shadow-sm">
                    <div className="grid gap-5 sm:grid-cols-2">
                        {fields.map(([name, label, required]) => (
                            <label key={name} className="block text-sm font-medium text-text">
                                {label}{required && ' *'}
                                <input value={data[name]} onChange={(e) => setData(name, e.target.value)} className="input" />
                                {errors[name] && <span className="mt-1 block text-xs text-danger">{errors[name]}</span>}
                            </label>
                        ))}
                    </div>
                    <label className="block text-sm font-medium text-text">
                        Alamat
                        <textarea rows="3" value={data.address} onChange={(e) => setData('address', e.target.value)} className="input" />
                        {errors.address && <span className="mt-1 block text-xs text-danger">{errors.address}</span>}
                    </label>
                    <label className="block text-sm font-medium text-text">
                        Cakupan Area Layanan
                        <textarea rows="2" value={data.coverage_area} onChange={(e) => setData('coverage_area', e.target.value)} placeholder="Contoh: Jabodetabek, Jawa Barat bagian utara" className="input" />
                        {errors.coverage_area && <span className="mt-1 block text-xs text-danger">{errors.coverage_area}</span>}
                    </label>
                    <div className="space-y-2 rounded-lg border border-border bg-bg/40 p-4">
                        <p className="text-sm font-semibold text-text">Jenis Jasa yang Bisa Ditugaskan</p>
                        <label className="flex items-center gap-2 text-sm text-text">
                            <input type="checkbox" checked={data.provides_survey} onChange={(e) => setData('provides_survey', e.target.checked)} />
                            Bisa melakukan survey lapangan
                        </label>
                        <label className="flex items-center gap-2 text-sm text-text">
                            <input type="checkbox" checked={data.provides_technical} onChange={(e) => setData('provides_technical', e.target.checked)} />
                            Bisa melakukan pekerjaan teknis / instalasi
                        </label>
                    </div>
                    <div className="flex justify-end gap-3">
                        <Link href={editing ? `/procurement/vendors/${vendor.id}` : '/procurement/vendors'} className="rounded-lg border border-border px-4 py-2 text-sm text-text-muted">Batal</Link>
                        <button disabled={processing} className="rounded-lg bg-navy px-4 py-2 text-sm font-semibold text-white disabled:opacity-60">{processing ? 'Menyimpan...' : 'Simpan'}</button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
