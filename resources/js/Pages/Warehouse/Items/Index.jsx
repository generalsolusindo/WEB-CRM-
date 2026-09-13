import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { FiPlus, FiPlusCircle, FiMinusCircle } from 'react-icons/fi';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, Toolbar, SearchInput, Button, DataTable, EmptyState, Pagination, Modal, Field, Input } from '../../../Components/ui';

export default function Index({ items, filters, canManage }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [adjusting, setAdjusting] = useState(null);

    function submit(e) {
        e.preventDefault();
        router.get('/warehouse/items', { search }, { preserveState: true, replace: true });
    }

    function destroy(item) {
        if (confirm(`Hapus barang "${item.name}"?`)) {
            router.delete(`/warehouse/items/${item.id}`);
        }
    }

    const columns = [
        {
            key: 'name',
            label: 'Barang',
            render: (i) => (
                <div>
                    <div className="font-medium text-text">{i.name}</div>
                    <div className="text-xs text-text-muted">{i.category || '—'}</div>
                </div>
            ),
        },
        { key: 'unit', label: 'Satuan' },
        {
            key: 'qty_on_hand',
            label: 'Stok',
            align: 'right',
            render: (i) => (
                <span className={`font-bold tabular-nums ${i.qty_on_hand === 0 ? 'text-danger' : 'text-text'}`}>
                    {i.qty_on_hand}
                </span>
            ),
        },
    ];

    if (canManage) {
        columns.push({
            key: 'actions',
            label: 'Aksi',
            align: 'right',
            render: (i) => (
                <span className="flex items-center justify-end gap-3 whitespace-nowrap">
                    <button onClick={() => setAdjusting(i)} className="font-medium text-primary hover:underline">Atur Stok</button>
                    <a href={`/warehouse/items/${i.id}/edit`} className="font-medium text-primary hover:underline">Edit</a>
                    <button onClick={() => destroy(i)} className="font-medium text-danger hover:underline">Hapus</button>
                </span>
            ),
        });
    }

    return (
        <AppLayout>
            <Head title="Stok Barang" />
            <div className="mx-auto max-w-5xl space-y-5">
                <PageHeader
                    title="Stok Barang"
                    subtitle="Stok barang gudang internal (CCTV, kabel LAN, dll) — dipakai Procurement untuk cek ketersediaan sebelum beli baru."
                    actions={canManage && <Button href="/warehouse/items/create" icon={FiPlus}>Tambah Barang</Button>}
                />

                <form onSubmit={submit}>
                    <Toolbar>
                        <SearchInput value={search} onChange={setSearch} placeholder="Cari nama atau kategori barang" />
                        <Button type="submit">Cari</Button>
                    </Toolbar>
                </form>

                <DataTable
                    columns={columns}
                    rows={items.data}
                    rowKey="id"
                    empty={<EmptyState title="Belum ada barang di gudang." />}
                    footer={<Pagination links={items.links} />}
                />
            </div>

            {adjusting && <AdjustStockModal item={adjusting} onClose={() => setAdjusting(null)} />}
        </AppLayout>
    );
}

function AdjustStockModal({ item, onClose }) {
    const { data, setData, post, processing, errors, reset } = useForm({ direction: 'in', qty: '' });

    function submit(e) {
        e.preventDefault();
        post(`/warehouse/items/${item.id}/adjust`, {
            preserveScroll: true,
            onSuccess: () => { reset(); onClose(); },
        });
    }

    return (
        <Modal open onClose={onClose} title={`Atur Stok — ${item.name}`} size="sm">
            <form onSubmit={submit} className="space-y-4">
                <p className="text-sm text-text-muted">Stok saat ini: <span className="font-bold text-text">{item.qty_on_hand} {item.unit}</span></p>
                <div className="grid grid-cols-2 gap-2">
                    <button
                        type="button"
                        onClick={() => setData('direction', 'in')}
                        className={`flex items-center justify-center gap-2 rounded-lg border px-3 py-2 text-sm font-semibold transition ${
                            data.direction === 'in' ? 'border-success bg-success-soft text-success' : 'border-border text-text-muted'
                        }`}
                    >
                        <FiPlusCircle className="h-4 w-4" /> Tambah Stok
                    </button>
                    <button
                        type="button"
                        onClick={() => setData('direction', 'out')}
                        className={`flex items-center justify-center gap-2 rounded-lg border px-3 py-2 text-sm font-semibold transition ${
                            data.direction === 'out' ? 'border-danger bg-danger-soft text-danger' : 'border-border text-text-muted'
                        }`}
                    >
                        <FiMinusCircle className="h-4 w-4" /> Kurangi Stok
                    </button>
                </div>
                <Field label={`Jumlah (${item.unit})`} required error={errors.qty}>
                    <Input type="number" min="1" value={data.qty} onChange={(e) => setData('qty', e.target.value)} autoFocus />
                </Field>
                <div className="flex justify-end gap-2 border-t border-border pt-4">
                    <Button type="button" variant="outline" onClick={onClose}>Batal</Button>
                    <Button type="submit" loading={processing}>Simpan</Button>
                </div>
            </form>
        </Modal>
    );
}
