import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';

export default function Show({ contact, leads }) {
    function destroy() {
        if (confirm('Hapus contact ini?')) router.delete(`/sales/contacts/${contact.id}`);
    }
    return (
        <AppLayout><Head title={contact.name} /><div className="mx-auto max-w-5xl space-y-5">
            <div className="flex flex-wrap items-start justify-between gap-3"><div><Link href="/sales/contacts" className="text-sm text-info">← Kembali ke Contacts</Link><h1 className="mt-2 text-2xl font-bold text-text">{contact.name}</h1><p className="text-text-muted">{contact.company_name || 'Tanpa perusahaan'}</p></div><div className="flex gap-2"><Link href={`/sales/leads/create?contact_id=${contact.id}`} className="rounded-lg bg-navy px-4 py-2 text-sm font-semibold text-white">Buat Lead</Link><Link href={`/sales/contacts/${contact.id}/edit`} className="rounded-lg border border-border px-4 py-2 text-sm">Edit</Link><button onClick={destroy} className="rounded-lg border border-danger/30 px-4 py-2 text-sm text-danger">Hapus</button></div></div>
            <section className="grid gap-4 rounded-xl border border-border bg-surface p-6 shadow-sm sm:grid-cols-2"><Info label="Email" value={contact.email} /><Info label="Telepon" value={contact.phone} /><Info label="NPWP" value={contact.npwp} /><Info label="Alamat" value={contact.address} /><Info label="Catatan" value={contact.notes} /></section>
            <section className="rounded-xl border border-border bg-surface p-6 shadow-sm"><h2 className="mb-4 font-semibold text-text">Lead terkait</h2>{leads.length ? <div className="space-y-2">{leads.map((lead) => <Link key={lead.id} href={`/sales/leads/${lead.id}`} className="flex justify-between rounded-lg border border-border p-3 hover:bg-bg"><span className="font-medium capitalize text-text">{lead.type} #{lead.id}</span><span className="text-sm capitalize text-text-muted">{lead.stage}</span></Link>)}</div> : <p className="text-sm text-text-muted">Belum ada lead untuk contact ini.</p>}</section>
        </div></AppLayout>
    );
}
function Info({ label, value }) { return <div><div className="text-xs font-semibold uppercase tracking-wide text-text-muted">{label}</div><div className="mt-1 whitespace-pre-line text-sm text-text">{value || '—'}</div></div>; }
