import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import CategoryBadge from '../../../Components/CategoryBadge';

const emptyForm = { item_name: '', category: 'material', description: '', qty: '1', unit: '', notes: '' };

export default function RequirementsPanel({ leadId, requirements, editable }) {
    const [editingId, setEditingId] = useState(null);
    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm(emptyForm);

    function beginEdit(item) {
        setEditingId(item.id);
        clearErrors();
        setData({
            item_name: item.item_name,
            category: item.category ?? 'material',
            description: item.description ?? '',
            qty: item.qty,
            unit: item.unit,
            notes: item.notes ?? '',
        });
    }

    function cancel() {
        setEditingId(null);
        reset();
        clearErrors();
    }

    function submit(event) {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: cancel };
        editingId
            ? put(`/sales/leads/${leadId}/requirements/${editingId}`, options)
            : post(`/sales/leads/${leadId}/requirements`, options);
    }

    function destroy(id) {
        if (confirm('Hapus requirement ini?')) {
            router.delete(`/sales/leads/${leadId}/requirements/${id}`, { preserveScroll: true });
        }
    }

    function submitToProcurement() {
        if (confirm('Kirim semua requirement ke Procurement? Setelah dikirim, data akan dikunci.')) {
            router.post(`/sales/leads/${leadId}/submit-procurement`, {}, { preserveScroll: true });
        }
    }

    return (
        <section className="card p-6">
            <div className="mb-5 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="font-semibold text-text">Requirements</h2>
                    <p className="text-sm text-text-muted">
                        {editable ? 'Catat seluruh kebutuhan customer sebelum dikirim ke Procurement.' : 'Requirement terkunci atau Lead belum menjadi Opportunity.'}
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <span className="rounded-full bg-bg px-3 py-1 text-sm text-text-muted">{requirements.length} item</span>
                    {editable && requirements.length > 0 && (
                        <button onClick={submitToProcurement} className="btn btn-primary">
                            Submit ke Procurement
                        </button>
                    )}
                </div>
            </div>

            {requirements.length > 0 ? (
                <div className="mb-5 overflow-x-auto rounded-lg border border-border">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-surface-2 text-[11px] font-bold uppercase tracking-wider text-text-faint"><tr><th className="px-3 py-2">Item</th><th className="px-3 py-2">Qty</th><th className="px-3 py-2">Deskripsi</th>{editable && <th className="px-3 py-2 text-right">Aksi</th>}</tr></thead>
                        <tbody className="divide-y divide-border">
                            {requirements.map((item) => (
                                <tr key={item.id}>
                                    <td className="px-3 py-3"><div className="font-medium text-text">{item.item_name}<CategoryBadge category={item.category} /></div>{item.notes && <div className="text-xs text-text-muted">{item.notes}</div>}</td>
                                    <td className="whitespace-nowrap px-3 py-3 text-text-muted">{item.qty} {item.unit}</td>
                                    <td className="px-3 py-3 text-text-muted">{item.description || '—'}</td>
                                    {editable && <td className="whitespace-nowrap px-3 py-3 text-right"><button onClick={() => beginEdit(item)} className="mr-3 font-medium text-primary hover:underline">Edit</button><button onClick={() => destroy(item.id)} className="font-medium text-danger hover:underline">Hapus</button></td>}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            ) : <div className="mb-5 rounded-xl border border-border bg-surface-2 p-8 text-center text-sm text-text-muted">Belum ada requirement.</div>}

            {editable && (
                <form onSubmit={submit} className="space-y-4 rounded-xl border border-border bg-surface-2 p-4">
                    <h3 className="font-medium text-text">{editingId ? 'Edit Requirement' : 'Tambah Requirement'}</h3>
                    <div className="grid gap-4 md:grid-cols-4">
                        <Field label="Nama item *" error={errors.item_name} className="md:col-span-2"><input value={data.item_name} onChange={(e) => setData('item_name', e.target.value)} className="input" /></Field>
                        <Field label="Kategori" error={errors.category}>
                            <select value={data.category} onChange={(e) => setData('category', e.target.value)} className="input">
                                <option value="material">Material</option>
                                <option value="service">Jasa</option>
                                <option value="reimburse">Biaya Reimburse (transport, akomodasi)</option>
                            </select>
                        </Field>
                        <Field label="Qty *" error={errors.qty}><input type="number" min="0.01" step="0.01" value={data.qty} onChange={(e) => setData('qty', e.target.value)} className="input" /></Field>
                        <Field label="Unit *" error={errors.unit}><input value={data.unit} onChange={(e) => setData('unit', e.target.value)} placeholder="pcs, unit, lot" className="input" /></Field>
                    </div>
                    <Field label="Deskripsi" error={errors.description}><textarea rows="2" value={data.description} onChange={(e) => setData('description', e.target.value)} className="input" /></Field>
                    <Field label="Catatan" error={errors.notes}><textarea rows="2" value={data.notes} onChange={(e) => setData('notes', e.target.value)} className="input" /></Field>
                    <div className="flex justify-end gap-2">{editingId && <button type="button" onClick={cancel} className="btn btn-outline">Batal</button>}<button disabled={processing} className="btn btn-primary">{processing ? 'Menyimpan...' : editingId ? 'Simpan Perubahan' : 'Tambah Item'}</button></div>
                </form>
            )}
        </section>
    );
}

function Field({ label, error, children, className = '' }) {
    return <label className={`block text-sm font-medium text-text ${className}`}>{label}{children}{error && <span className="mt-1 block text-xs text-danger">{error}</span>}</label>;
}
