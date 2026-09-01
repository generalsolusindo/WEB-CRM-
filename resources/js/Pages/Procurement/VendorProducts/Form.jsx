import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';

export default function Form({ vendor, vendorProduct = null, categoryOptions }) {
    const editing = Boolean(vendorProduct);
    const { data, setData, post, put, processing, errors } = useForm({
        vendor_id: vendor.id,
        item_name: vendorProduct?.item_name ?? '',
        category: vendorProduct?.category ?? 'material',
        description: vendorProduct?.description ?? '',
        price: vendorProduct?.price ?? '',
        unit: vendorProduct?.unit ?? '',
        is_active: vendorProduct ? Boolean(vendorProduct.is_active) : true,
    });

    function submit(e) {
        e.preventDefault();
        editing ? put(`/procurement/vendor-products/${vendorProduct.id}`) : post('/procurement/vendor-products');
    }

    return (
        <AppLayout>
            <Head title={editing ? 'Edit Produk' : 'Tambah Produk'} />
            <div className="mx-auto max-w-2xl">
                <div className="mb-5">
                    <Link href={`/procurement/vendors/${vendor.id}`} className="text-sm text-info">← Kembali ke {vendor.name}</Link>
                    <h1 className="mt-2 text-2xl font-bold text-text">{editing ? 'Edit Produk' : 'Tambah Produk'}</h1>
                    <p className="text-sm text-text-muted">Vendor: {vendor.name}</p>
                </div>
                <form onSubmit={submit} className="space-y-5 rounded-xl border border-border bg-surface p-6 shadow-sm">
                    <label className="block text-sm font-medium text-text">
                        Nama Item / Jasa *
                        <input value={data.item_name} onChange={(e) => setData('item_name', e.target.value)} className="input" />
                        {errors.item_name && <span className="mt-1 block text-xs text-danger">{errors.item_name}</span>}
                    </label>
                    <div className="grid gap-5 sm:grid-cols-3">
                        <label className="block text-sm font-medium text-text">
                            Kategori *
                            <select value={data.category} onChange={(e) => setData('category', e.target.value)} className="input">
                                {categoryOptions.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
                            </select>
                            {errors.category && <span className="mt-1 block text-xs text-danger">{errors.category}</span>}
                        </label>
                        <label className="block text-sm font-medium text-text">
                            Harga (Rp) *
                            <input type="number" step="0.01" min="0" value={data.price} onChange={(e) => setData('price', e.target.value)} className="input" />
                            {errors.price && <span className="mt-1 block text-xs text-danger">{errors.price}</span>}
                        </label>
                        <label className="block text-sm font-medium text-text">
                            Unit *
                            <input value={data.unit} onChange={(e) => setData('unit', e.target.value)} placeholder="pcs, unit, lot, hari" className="input" />
                            {errors.unit && <span className="mt-1 block text-xs text-danger">{errors.unit}</span>}
                        </label>
                    </div>
                    <label className="block text-sm font-medium text-text">
                        Deskripsi
                        <textarea rows="3" value={data.description} onChange={(e) => setData('description', e.target.value)} className="input" />
                        {errors.description && <span className="mt-1 block text-xs text-danger">{errors.description}</span>}
                    </label>
                    <label className="flex items-center gap-2 text-sm font-medium text-text">
                        <input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} />
                        Aktif (bisa dipilih saat sourcing Procurement Request)
                    </label>
                    <div className="flex justify-end gap-3">
                        <Link href={`/procurement/vendors/${vendor.id}`} className="rounded-lg border border-border px-4 py-2 text-sm text-text-muted">Batal</Link>
                        <button disabled={processing} className="rounded-lg bg-navy px-4 py-2 text-sm font-semibold text-white disabled:opacity-60">{processing ? 'Menyimpan...' : 'Simpan'}</button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
