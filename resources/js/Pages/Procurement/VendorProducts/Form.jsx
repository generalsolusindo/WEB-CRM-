import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, Card, Field, Input, Select, Textarea, FormActions, CurrencyInput } from '../../../Components/ui';

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
            <div className="mx-auto max-w-2xl space-y-5">
                <PageHeader
                    title={editing ? 'Edit Produk' : 'Tambah Produk'}
                    subtitle={`Vendor: ${vendor.name}`}
                    back={{ href: `/procurement/vendors/${vendor.id}`, label: `Kembali ke ${vendor.name}` }}
                />
                <form onSubmit={submit}>
                    <Card className="space-y-5">
                        <Field label="Nama Item / Jasa" required error={errors.item_name}>
                            <Input value={data.item_name} onChange={(e) => setData('item_name', e.target.value)} />
                        </Field>
                        <div className="grid gap-5 sm:grid-cols-3">
                            <Field label="Kategori" required error={errors.category}>
                                <Select value={data.category} onChange={(e) => setData('category', e.target.value)}>
                                    {categoryOptions.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
                                </Select>
                            </Field>
                            <Field label="Harga (Rp)" required error={errors.price}>
                                <CurrencyInput value={data.price} onChange={(e) => setData('price', e.target.value)} className="input" />
                            </Field>
                            <Field label="Unit" required error={errors.unit}>
                                <Input value={data.unit} onChange={(e) => setData('unit', e.target.value)} placeholder="pcs, unit, lot, hari" />
                            </Field>
                        </div>
                        <Field label="Deskripsi" error={errors.description}>
                            <Textarea rows={3} value={data.description} onChange={(e) => setData('description', e.target.value)} />
                        </Field>
                        <label className="flex items-center gap-2 text-sm font-medium text-text">
                            <input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} className="accent-navy" />
                            Aktif (bisa dipilih saat sourcing Procurement Request)
                        </label>
                        <FormActions cancelHref={`/procurement/vendors/${vendor.id}`} processing={processing} />
                    </Card>
                </form>
            </div>
        </AppLayout>
    );
}
