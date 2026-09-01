import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';

export default function Form({ lead = null, contacts, stageOptions, selectedContactId = null }) {
    const editing = Boolean(lead);
    const { data, setData, post, put, processing, errors } = useForm({
        contact_id: lead?.contact_id ?? selectedContactId ?? '',
        stage: lead?.stage ?? 'new', source: lead?.source ?? '', notes: lead?.notes ?? '',
    });
    function submit(e) { e.preventDefault(); editing ? put(`/sales/leads/${lead.id}`) : post('/sales/leads'); }
    return <AppLayout><Head title={editing ? 'Edit Lead' : 'Tambah Lead'} /><div className="mx-auto max-w-3xl">
        <div className="mb-5"><h1 className="text-2xl font-bold text-text">{editing ? 'Edit Lead' : 'Tambah Lead'}</h1><p className="text-sm text-text-muted">Hubungkan lead dengan contact yang sudah tersedia.</p></div>
        <form onSubmit={submit} className="space-y-5 rounded-xl border border-border bg-surface p-6 shadow-sm">
            {contacts.length === 0 ? <div className="rounded-lg border border-warning/20 bg-warning/10 p-4 text-sm text-warning">Anda belum memiliki contact. <Link href="/sales/contacts/create" className="font-semibold underline">Buat contact terlebih dahulu.</Link></div> : null}
            <label className="block text-sm font-medium text-text">Contact *<select value={data.contact_id} onChange={(e) => setData('contact_id', e.target.value)} className="mt-1 w-full rounded-lg border border-border px-3 py-2"><option value="">Pilih contact</option>{contacts.map((c) => <option key={c.id} value={c.id}>{c.name}{c.company_name ? ` — ${c.company_name}` : ''}</option>)}</select>{errors.contact_id && <span className="mt-1 block text-sm text-danger">{errors.contact_id}</span>}</label>
            <label className="block text-sm font-medium text-text">Stage *<select value={data.stage} onChange={(e) => setData('stage', e.target.value)} className="mt-1 w-full rounded-lg border border-border px-3 py-2">{stageOptions.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}</select>{errors.stage && <span className="mt-1 block text-sm text-danger">{errors.stage}</span>}</label>
            <label className="block text-sm font-medium text-text">Source<input value={data.source} onChange={(e) => setData('source', e.target.value)} placeholder="Referral, Website, Telepon, dll." className="mt-1 w-full rounded-lg border border-border px-3 py-2 outline-none focus:border-navy" />{errors.source && <span className="mt-1 block text-sm text-danger">{errors.source}</span>}</label>
            <label className="block text-sm font-medium text-text">Catatan<textarea rows="4" value={data.notes} onChange={(e) => setData('notes', e.target.value)} className="mt-1 w-full rounded-lg border border-border px-3 py-2 outline-none focus:border-navy" />{errors.notes && <span className="mt-1 block text-sm text-danger">{errors.notes}</span>}</label>
            <div className="flex justify-end gap-3"><Link href={editing ? `/sales/leads/${lead.id}` : '/sales/leads'} className="rounded-lg border border-border px-4 py-2 text-sm text-text-muted">Batal</Link><button disabled={processing || contacts.length === 0} className="rounded-lg bg-navy px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">{processing ? 'Menyimpan...' : 'Simpan'}</button></div>
        </form>
    </div></AppLayout>;
}
