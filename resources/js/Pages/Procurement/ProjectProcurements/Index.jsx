import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';

function money(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(v || 0));
}

const badge = {
    pending: 'bg-warning/10 text-warning',
    purchased: 'bg-info/10 text-info',
    received: 'bg-success/10 text-success',
};

export default function Index({ items, statusOptions, catalog }) {
    return (
        <AppLayout>
            <Head title="Pengadaan Project" />
            <div className="mx-auto max-w-6xl space-y-5">
                <div>
                    <h1 className="text-2xl font-bold text-text">Pengadaan Project</h1>
                    <p className="text-sm text-text-muted">
                        Barang/jasa nyata untuk eksekusi project. Dipicu setelah invoice muka lunas.
                        Update status begitu barang dibeli / diterima.
                    </p>
                </div>

                <div className="overflow-hidden rounded-xl border border-border bg-surface shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-bg text-text-muted">
                                <tr>
                                    <th className="px-3 py-3">Project</th>
                                    <th className="px-3 py-3">Item</th>
                                    <th className="px-3 py-3">Qty</th>
                                    <th className="px-3 py-3">Vendor / Katalog</th>
                                    <th className="px-3 py-3">Cost</th>
                                    <th className="px-3 py-3">Status</th>
                                    <th className="px-3 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {items.map((item) => <Row key={item.id} item={item} statusOptions={statusOptions} catalog={catalog} />)}
                                {items.length === 0 && <tr><td colSpan="7" className="px-4 py-12 text-center text-text-muted">Belum ada pengadaan project.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

function Row({ item, statusOptions, catalog }) {
    const form = useForm({
        vendor_product_id: item.vendor_product_id ?? '',
        cost_price: item.cost_price ?? '',
        status: item.status,
        notes: item.notes ?? '',
    });

    function pick(value) {
        const p = catalog.find((c) => String(c.id) === String(value));
        form.setData({ ...form.data, vendor_product_id: value, ...(p ? { cost_price: Number(p.price).toFixed(2) } : {}) });
    }
    function save() {
        form.transform((d) => ({ ...d, vendor_product_id: d.vendor_product_id === '' ? null : Number(d.vendor_product_id) }));
        form.put(`/procurement/project-procurements/${item.id}`, { preserveScroll: true });
    }

    return (
        <tr>
            <td className="px-3 py-3">
                <div className="font-semibold text-text">{item.project_number}</div>
                <div className="text-xs text-text-muted">{item.customer}{item.is_extra ? ' · ekstra' : ''}</div>
            </td>
            <td className="px-3 py-3 text-text">{item.item_name}</td>
            <td className="whitespace-nowrap px-3 py-3 text-text-muted">{item.qty} {item.unit}</td>
            <td className="min-w-48 px-3 py-3">
                <select value={form.data.vendor_product_id} onChange={(e) => pick(e.target.value)} className="w-full rounded-lg border border-border px-2 py-1.5 text-xs">
                    <option value="">{item.vendor || '— pilih —'}</option>
                    {catalog.map((c) => <option key={c.id} value={c.id}>{c.vendor?.name} · {c.item_name}</option>)}
                </select>
            </td>
            <td className="min-w-32 px-3 py-3">
                <input type="number" min="0" step="0.01" value={form.data.cost_price} onChange={(e) => form.setData('cost_price', e.target.value)} className="w-full rounded-lg border border-border px-2 py-1.5 text-right text-xs" />
                <div className="mt-0.5 text-right text-[10px] text-text-muted">{money(form.data.cost_price)}</div>
            </td>
            <td className="min-w-32 px-3 py-3">
                <select value={form.data.status} onChange={(e) => form.setData('status', e.target.value)} className={`w-full rounded-lg border border-border px-2 py-1.5 text-xs ${badge[form.data.status]}`}>
                    {statusOptions.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                </select>
            </td>
            <td className="px-3 py-3 text-right">
                <button onClick={save} disabled={form.processing} className="rounded-lg bg-navy px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-50">Simpan</button>
                {Object.keys(form.errors).length > 0 && <div className="text-[10px] text-danger">{Object.values(form.errors)[0]}</div>}
            </td>
        </tr>
    );
}
