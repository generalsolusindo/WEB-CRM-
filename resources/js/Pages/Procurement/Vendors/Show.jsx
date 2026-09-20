import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { FiPlus, FiEdit2, FiTrash2 } from 'react-icons/fi';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, Card, CardHeader, Button, ConfirmDialog, Info, InfoGrid } from '../../../Components/ui';

function money(value) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(value || 0));
}

const CATEGORY_LABEL = { service: 'Jasa', reimburse: 'Biaya Reimburse' };

export default function Show({ vendor }) {
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [deleting, setDeleting] = useState(false);

    function destroyVendor() {
        setDeleting(true);
        router.delete(`/procurement/vendors/${vendor.id}`, {
            onSuccess: () => setDeleteTarget(null),
            onFinish: () => setDeleting(false),
        });
    }
    function destroyProduct(id) {
        setDeleting(true);
        router.delete(`/procurement/vendor-products/${id}`, {
            preserveScroll: true,
            onSuccess: () => setDeleteTarget(null),
            onFinish: () => setDeleting(false),
        });
    }
    function confirmDelete() {
        if (deleteTarget?.type === 'vendor') destroyVendor();
        if (deleteTarget?.type === 'product') destroyProduct(deleteTarget.id);
    }

    return (
        <AppLayout>
            <Head title={vendor.name} />
            <div className="mx-auto max-w-5xl space-y-5">
                <PageHeader
                    title={vendor.name}
                    subtitle={vendor.contact_person || 'Tanpa PIC'}
                    back={{ href: '/procurement/vendors', label: 'Kembali ke Vendor' }}
                    actions={(
                        <>
                            <Button href={`/procurement/vendor-products/create?vendor_id=${vendor.id}`} icon={FiPlus}>Tambah Produk</Button>
                            <Button href={`/procurement/vendors/${vendor.id}/edit`} variant="outline" icon={FiEdit2}>Edit</Button>
                            <Button onClick={() => setDeleteTarget({ type: 'vendor' })} variant="ghost" icon={FiTrash2} className="text-danger hover:bg-danger-soft hover:text-danger">Hapus</Button>
                        </>
                    )}
                />

                <Card>
                    <InfoGrid cols={2}>
                        <Info label="Email" value={vendor.email} />
                        <Info label="Telepon" value={vendor.phone} />
                        <Info label="Kota / Wilayah" value={vendor.city} />
                        <Info label="Cakupan Area" value={vendor.coverage_area} />
                        <Info label="Alamat" value={vendor.address} />
                        <Info label="Info Rekening" value={vendor.bank_account_note} />
                        <div>
                            <div className="text-[11px] font-bold uppercase tracking-wider text-text-faint">Jenis Jasa</div>
                            <div className="mt-1 flex flex-wrap gap-2">
                                {vendor.provides_survey && <span className="badge badge-primary">Survey</span>}
                                {vendor.provides_technical && <span className="badge badge-neutral">Teknis</span>}
                                {!vendor.provides_survey && !vendor.provides_technical && <span className="text-sm text-text">—</span>}
                            </div>
                        </div>
                    </InfoGrid>
                </Card>

                <Card padded={false}>
                    <CardHeader
                        title="Surveyor / Teknisi Vendor"
                        actions={<Button href="/procurement/technicians/create" variant="ghost" size="sm" icon={FiPlus}>Tambah akun</Button>}
                    />
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b border-border bg-surface-2 text-[11px] font-bold uppercase tracking-wider text-text-faint">
                                    <th className="px-4 py-3">Nama</th><th className="px-4 py-3">Email</th><th className="px-4 py-3">Telepon</th><th className="px-4 py-3">Status</th><th className="px-4 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {(vendor.technicians ?? []).map((tech) => (
                                    <tr key={tech.id}>
                                        <td className="px-4 py-3.5 font-medium text-text">{tech.name}</td>
                                        <td className="px-4 py-3.5 text-text-muted">{tech.email}</td>
                                        <td className="px-4 py-3.5 text-text-muted">{tech.phone || '—'}</td>
                                        <td className="px-4 py-3.5"><span className={`badge ${tech.is_active ? 'badge-success' : 'badge-neutral'}`}>{tech.is_active ? 'Aktif' : 'Nonaktif'}</span></td>
                                        <td className="px-4 py-3.5 text-right"><a href={`/procurement/technicians/${tech.id}/edit`} className="font-medium text-primary hover:underline">Edit</a></td>
                                    </tr>
                                ))}
                                {(vendor.technicians ?? []).length === 0 && <tr><td colSpan="5" className="px-4 py-8 text-center text-text-muted">Belum ada surveyor/teknisi terdaftar untuk vendor ini.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                </Card>

                <Card padded={false}>
                    <CardHeader title="Katalog Produk / Jasa" />
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b border-border bg-surface-2 text-[11px] font-bold uppercase tracking-wider text-text-faint">
                                    <th className="px-4 py-3">Item</th><th className="px-4 py-3">Kategori</th><th className="px-4 py-3">Unit</th><th className="px-4 py-3 text-right">Harga</th><th className="px-4 py-3">Status</th><th className="px-4 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {vendor.products.map((product) => (
                                    <tr key={product.id}>
                                        <td className="px-4 py-3.5">
                                            <div className="font-medium text-text">{product.item_name}</div>
                                            <div className="text-xs text-text-muted">{product.description || '—'}</div>
                                        </td>
                                        <td className="px-4 py-3.5 text-text-muted">{CATEGORY_LABEL[product.category] ?? 'Material'}</td>
                                        <td className="px-4 py-3.5 text-text-muted">{product.unit}</td>
                                        <td className="px-4 py-3.5 text-right tabular-nums text-text">{money(product.price)}</td>
                                        <td className="px-4 py-3.5"><span className={`badge ${product.is_active ? 'badge-success' : 'badge-neutral'}`}>{product.is_active ? 'Aktif' : 'Nonaktif'}</span></td>
                                        <td className="whitespace-nowrap px-4 py-3.5 text-right">
                                            <a href={`/procurement/vendor-products/${product.id}/edit`} className="mr-3 font-medium text-primary hover:underline">Edit</a>
                                            <button onClick={() => setDeleteTarget({ type: 'product', id: product.id, name: product.item_name })} className="font-medium text-danger hover:underline">Hapus</button>
                                        </td>
                                    </tr>
                                ))}
                                {vendor.products.length === 0 && <tr><td colSpan="6" className="px-4 py-10 text-center text-text-muted">Belum ada produk.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>

            <ConfirmDialog
                open={deleteTarget !== null}
                onClose={() => setDeleteTarget(null)}
                onConfirm={confirmDelete}
                title={deleteTarget?.type === 'vendor' ? 'Hapus vendor?' : 'Hapus produk?'}
                description={deleteTarget?.type === 'vendor'
                    ? `Vendor ${vendor.name} akan dihapus permanen jika belum digunakan oleh transaksi.`
                    : `Produk ${deleteTarget?.name ?? ''} akan dihapus dari katalog vendor.`}
                tone="danger"
                confirmLabel={deleteTarget?.type === 'vendor' ? 'Hapus Vendor' : 'Hapus Produk'}
                processing={deleting}
            />
        </AppLayout>
    );
}
