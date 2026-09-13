import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, Card, Field, Input, Textarea, FormActions } from '../../../Components/ui';

export default function Form({ item = null }) {
    const editing = Boolean(item);
    const { data, setData, post, put, processing, errors } = useForm({
        name: item?.name ?? '',
        category: item?.category ?? '',
        unit: item?.unit ?? '',
        qty_on_hand: item?.qty_on_hand ?? 0,
        notes: item?.notes ?? '',
    });

    function submit(event) {
        event.preventDefault();
        editing ? put(`/warehouse/items/${item.id}`) : post('/warehouse/items');
    }

    return (
        <AppLayout>
            <Head title={editing ? 'Edit Barang' : 'Tambah Barang'} />
            <div className="mx-auto max-w-xl space-y-5">
                <PageHeader
                    title={editing ? 'Edit Barang' : 'Tambah Barang'}
                    back={{ href: '/warehouse/items', label: 'Kembali ke Stok Barang' }}
                />
                <form onSubmit={submit}>
                    <Card className="space-y-5">
                        <Field label="Nama Barang" required error={errors.name}>
                            <Input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="Contoh: Kabel LAN Cat6" />
                        </Field>
                        <Field label="Kategori" error={errors.category} hint="Opsional, untuk memudahkan pencarian.">
                            <Input type="text" value={data.category} onChange={(e) => setData('category', e.target.value)} placeholder="Contoh: Networking" />
                        </Field>
                        <Field label="Satuan" required error={errors.unit}>
                            <Input type="text" value={data.unit} onChange={(e) => setData('unit', e.target.value)} placeholder="Contoh: unit, meter, roll" />
                        </Field>
                        {!editing && (
                            <Field label="Jumlah Stok Awal" required error={errors.qty_on_hand}>
                                <Input type="number" min="0" value={data.qty_on_hand} onChange={(e) => setData('qty_on_hand', e.target.value)} />
                            </Field>
                        )}
                        <Field label="Catatan" error={errors.notes}>
                            <Textarea value={data.notes} onChange={(e) => setData('notes', e.target.value)} placeholder="Opsional" />
                        </Field>
                        {editing && (
                            <p className="text-xs text-text-muted">
                                Jumlah stok diubah lewat tombol Tambah/Kurangi Stok di halaman Stok Barang, bukan di sini.
                            </p>
                        )}
                        <FormActions cancelHref="/warehouse/items" processing={processing} />
                    </Card>
                </form>
            </div>
        </AppLayout>
    );
}
