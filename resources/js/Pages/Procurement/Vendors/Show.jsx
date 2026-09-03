import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';

function money(value) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(value || 0));
}

export default function Show({ vendor }) {
    function destroyVendor() {
        if (confirm('Hapus vendor ini?')) router.delete(`/procurement/vendors/${vendor.id}`);
    }
    function destroyProduct(id) {
        if (confirm('Hapus produk ini?')) router.delete(`/procurement/vendor-products/${id}`, { preserveScroll: true });
    }

    return (
        <AppLayout>
            <Head title={vendor.name} />
            <div className="mx-auto max-w-5xl space-y-5">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <Link href="/procurement/vendors" className="text-sm text-info">← Kembali ke Vendor</Link>
                        <h1 className="mt-2 text-2xl font-bold text-text">{vendor.name}</h1>
                        <p className="text-text-muted">{vendor.contact_person || 'Tanpa PIC'}</p>
                    </div>
                    <div className="flex gap-2">
                        <Link href={`/procurement/vendor-products/create?vendor_id=${vendor.id}`} className="rounded-lg bg-navy px-4 py-2 text-sm font-semibold text-white">Tambah Produk</Link>
                        <Link href={`/procurement/vendors/${vendor.id}/edit`} className="rounded-lg border border-border px-4 py-2 text-sm">Edit</Link>
                        <button onClick={destroyVendor} className="rounded-lg border border-danger/30 px-4 py-2 text-sm text-danger">Hapus</button>
                    </div>
                </div>

                <section className="grid gap-4 rounded-xl border border-border bg-surface p-6 shadow-sm sm:grid-cols-2">
                    <Info label="Email" value={vendor.email} />
                    <Info label="Telepon" value={vendor.phone} />
                    <Info label="Kota / Wilayah" value={vendor.city} />
                    <Info label="Cakupan Area" value={vendor.coverage_area} />
                    <Info label="Alamat" value={vendor.address} />
                    <div>
                        <div className="text-xs font-semibold uppercase tracking-wide text-text-muted">Jenis Jasa</div>
                        <div className="mt-1 flex flex-wrap gap-2">
                            {vendor.provides_survey && <span className="rounded-full bg-info/10 px-2.5 py-1 text-xs font-semibold text-info">Survey</span>}
                            {vendor.provides_technical && <span className="rounded-full bg-navy/10 px-2.5 py-1 text-xs font-semibold text-navy">Teknis</span>}
                            {!vendor.provides_survey && !vendor.provides_technical && <span className="text-sm text-text">—</span>}
                        </div>
                    </div>
                </section>

                <section className="overflow-hidden rounded-xl border border-border bg-surface shadow-sm">
                    <div className="flex items-center justify-between border-b border-border px-5 py-4">
                        <h2 className="font-semibold text-text">Surveyor / Teknisi Vendor</h2>
                        <Link href="/procurement/technicians/create" className="text-sm font-semibold text-info">+ Tambah akun</Link>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-bg text-text-muted"><tr><th className="px-4 py-3">Nama</th><th className="px-4 py-3">Email</th><th className="px-4 py-3">Telepon</th><th className="px-4 py-3">Status</th><th className="px-4 py-3 text-right">Aksi</th></tr></thead>
                            <tbody className="divide-y divide-border">
                                {(vendor.technicians ?? []).map((tech) => (
                                    <tr key={tech.id}>
                                        <td className="px-4 py-3 font-medium text-text">{tech.name}</td>
                                        <td className="px-4 py-3 text-text-muted">{tech.email}</td>
                                        <td className="px-4 py-3 text-text-muted">{tech.phone || '—'}</td>
                                        <td className="px-4 py-3"><span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${tech.is_active ? 'bg-success/10 text-success' : 'bg-text-muted/10 text-text-muted'}`}>{tech.is_active ? 'Aktif' : 'Nonaktif'}</span></td>
                                        <td className="px-4 py-3 text-right"><Link href={`/procurement/technicians/${tech.id}/edit`} className="text-info">Edit</Link></td>
                                    </tr>
                                ))}
                                {(vendor.technicians ?? []).length === 0 && <tr><td colSpan="5" className="px-4 py-8 text-center text-text-muted">Belum ada surveyor/teknisi terdaftar untuk vendor ini.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                </section>

                <section className="overflow-hidden rounded-xl border border-border bg-surface shadow-sm">
                    <div className="border-b border-border px-5 py-4"><h2 className="font-semibold text-text">Katalog Produk / Jasa</h2></div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-bg text-text-muted"><tr><th className="px-4 py-3">Item</th><th className="px-4 py-3">Kategori</th><th className="px-4 py-3">Unit</th><th className="px-4 py-3 text-right">Harga</th><th className="px-4 py-3">Status</th><th className="px-4 py-3 text-right">Aksi</th></tr></thead>
                            <tbody className="divide-y divide-border">
                                {vendor.products.map((product) => (
                                    <tr key={product.id}>
                                        <td className="px-4 py-3"><div className="font-medium text-text">{product.item_name}</div><div className="text-xs text-text-muted">{product.description || '—'}</div></td>
                                        <td className="px-4 py-3 capitalize text-text-muted">{{ service: 'Jasa', reimburse: 'Biaya Reimburse' }[product.category] ?? 'Material'}</td>
                                        <td className="px-4 py-3 text-text-muted">{product.unit}</td>
                                        <td className="px-4 py-3 text-right text-text">{money(product.price)}</td>
                                        <td className="px-4 py-3"><span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${product.is_active ? 'bg-success/10 text-success' : 'bg-text-muted/10 text-text-muted'}`}>{product.is_active ? 'Aktif' : 'Nonaktif'}</span></td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right"><Link href={`/procurement/vendor-products/${product.id}/edit`} className="mr-3 text-info">Edit</Link><button onClick={() => destroyProduct(product.id)} className="text-danger">Hapus</button></td>
                                    </tr>
                                ))}
                                {vendor.products.length === 0 && <tr><td colSpan="6" className="px-4 py-10 text-center text-text-muted">Belum ada produk.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}

function Info({ label, value }) {
    return <div><div className="text-xs font-semibold uppercase tracking-wide text-text-muted">{label}</div><div className="mt-1 whitespace-pre-line text-sm text-text">{value || '—'}</div></div>;
}
