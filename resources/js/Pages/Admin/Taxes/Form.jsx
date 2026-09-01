import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';

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
            <div className="mx-auto max-w-xl">
                <div className="mb-5">
                    <Link href="/admin/taxes" className="text-sm text-info">← Kembali ke Master Pajak</Link>
                    <h1 className="mt-2 text-2xl font-bold text-text">{editing ? 'Edit Pajak' : 'Tambah Pajak'}</h1>
                </div>
                <form onSubmit={submit} className="space-y-5 rounded-xl border border-border bg-surface p-6 shadow-sm">
                    <label className="block text-sm font-medium text-text">
                        Nama *
                        <input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} className="mt-1 w-full rounded-lg border border-border px-3 py-2 outline-none focus:border-navy" />
                        {errors.name && <span className="mt-1 block text-sm text-danger">{errors.name}</span>}
                    </label>
                    <label className="block text-sm font-medium text-text">
                        Tarif (%) *
                        <input type="number" step="0.01" min="0" max="100" value={data.rate} onChange={(e) => setData('rate', e.target.value)} className="mt-1 w-full rounded-lg border border-border px-3 py-2 outline-none focus:border-navy" />
                        {errors.rate && <span className="mt-1 block text-sm text-danger">{errors.rate}</span>}
                    </label>
                    <label className="flex items-center gap-2 text-sm font-medium text-text">
                        <input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} />
                        Aktif (bisa dipilih di Quotation)
                    </label>
                    <div className="flex justify-end gap-3">
                        <Link href="/admin/taxes" className="rounded-lg border border-border px-4 py-2 text-sm text-text-muted">Batal</Link>
                        <button disabled={processing} className="rounded-lg bg-navy px-4 py-2 text-sm font-semibold text-white disabled:opacity-60">
                            {processing ? 'Menyimpan...' : 'Simpan'}
                        </button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
