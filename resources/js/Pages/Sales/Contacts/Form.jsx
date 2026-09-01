import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';

const fields = [
    ['name', 'Nama', 'text'], ['company_name', 'Perusahaan', 'text'],
    ['email', 'Email', 'email'], ['phone', 'Telepon', 'text'], ['npwp', 'NPWP', 'text'],
];

export default function Form({ contact = null }) {
    const editing = Boolean(contact);
    const { data, setData, post, put, processing, errors } = useForm({
        name: contact?.name ?? '', company_name: contact?.company_name ?? '',
        email: contact?.email ?? '', phone: contact?.phone ?? '', npwp: contact?.npwp ?? '',
        address: contact?.address ?? '', notes: contact?.notes ?? '',
    });

    function submit(event) {
        event.preventDefault();
        editing ? put(`/sales/contacts/${contact.id}`) : post('/sales/contacts');
    }

    return (
        <AppLayout>
            <Head title={editing ? 'Edit Contact' : 'Tambah Contact'} />
            <div className="mx-auto max-w-3xl">
                <div className="mb-5"><h1 className="text-2xl font-bold text-text">{editing ? 'Edit Contact' : 'Tambah Contact'}</h1><p className="text-sm text-text-muted">Isi informasi customer atau PIC.</p></div>
                <form onSubmit={submit} className="space-y-5 rounded-xl border border-border bg-surface p-6 shadow-sm">
                    <div className="grid gap-5 sm:grid-cols-2">
                        {fields.map(([name, label, type]) => <Field key={name} {...{ name, label, type, data, setData, error: errors[name] }} required={name === 'name'} />)}
                    </div>
                    <TextArea name="address" label="Alamat" {...{ data, setData, error: errors.address }} />
                    <TextArea name="notes" label="Catatan" {...{ data, setData, error: errors.notes }} />
                    <div className="flex justify-end gap-3"><Link href={editing ? `/sales/contacts/${contact.id}` : '/sales/contacts'} className="rounded-lg border border-border px-4 py-2 text-sm text-text-muted">Batal</Link><button disabled={processing} className="rounded-lg bg-navy px-4 py-2 text-sm font-semibold text-white disabled:opacity-60">{processing ? 'Menyimpan...' : 'Simpan'}</button></div>
                </form>
            </div>
        </AppLayout>
    );
}

function Field({ name, label, type, data, setData, error, required }) {
    return <label className="block text-sm font-medium text-text">{label}{required && ' *'}<input type={type} value={data[name]} onChange={(e) => setData(name, e.target.value)} className="mt-1 w-full rounded-lg border border-border px-3 py-2 outline-none focus:border-navy" />{error && <span className="mt-1 block text-sm text-danger">{error}</span>}</label>;
}
function TextArea({ name, label, data, setData, error }) {
    return <label className="block text-sm font-medium text-text">{label}<textarea rows="3" value={data[name]} onChange={(e) => setData(name, e.target.value)} className="mt-1 w-full rounded-lg border border-border px-3 py-2 outline-none focus:border-navy" />{error && <span className="mt-1 block text-sm text-danger">{error}</span>}</label>;
}
